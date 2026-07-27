<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-theme-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * Settings editor (admin only). Reads/writes the [branding] + [misc] sections of
 * sys_ini.config. Merges only the keys it owns, so every other interface setting
 * in [misc] (and every other section) is preserved untouched.
 */

$tform_def_file = "form/customizer.tform.php";

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

//* admin-only
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

$app->uses('tpl,tform,tform_actions');
$app->load('tform_actions');

//* Single-tab form: pin the active tab in the session BEFORE onLoad(). On a save
//* POST the framework calls getSQL(getCurrentTab()) before our onUpdateSave(), and
//* getCurrentTab() reads $_SESSION['s']['form']['tab']; if that is empty (fresh
//* session, or set by another form), tform_base hits count(null) and fatals on PHP 8.
$_SESSION['s']['form']['tab'] = 'branding';

//* Singleton settings form backed by sys_ini row 1. Force id=1 so a request
//* without ?id=1 (a bookmark, a manual URL) is always treated as an EDIT, never
//* an INSERT — the framework's insert path would build a bogus INSERT into
//* sys_ini using form-field names as columns and die with a raw SQL error.
$_GET['id'] = $_POST['id'] = $_REQUEST['id'] = 1;

class page_action extends tform_actions {

    /* The keys this module owns in each INI section.
     *
     * These are overwritten wholesale from the POST on every save, so ONLY form
     * fields belong here. [branding] logo_on_dark is deliberately absent: it is
     * the uploaded dark-background logo, written by logo_upload.php, and listing
     * it would blank the operator's logo on the next click of Save. It survives
     * every save because onUpdateSave re-parses the stored blob and only assigns
     * the keys named below. Same reasoning as sys_ini.custom_logo, which is a
     * column and was never a candidate for this list. */
    private $branding_keys = array('logo_url', 'logo_url_on_dark', 'accent_hex', 'rail_hex', 'login_bg', 'show_ispconfig_credit', 'show_theme_credit', 'show_version');
    private $misc_keys      = array('company_name', 'custom_login_text', 'custom_login_link');

    function onShowEdit() {
        global $app;
        if($_SESSION["s"]["user"]["typ"] != 'admin') die('This function needs admin privileges');

        if($app->tform->errorMessage == '') {
            $app->uses('getconf');
            $branding = $app->getconf->get_global_config('branding');
            $misc     = $app->getconf->get_global_config('misc');
            if(!is_array($branding)) $branding = array();
            if(!is_array($misc))     $misc = array();

            $this->dataRecord = array(
                'company_name'          => isset($misc['company_name']) ? $misc['company_name'] : '',
                'logo_url'              => isset($branding['logo_url']) ? $branding['logo_url'] : '',
                'logo_url_on_dark'      => isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
                'accent_hex'            => isset($branding['accent_hex']) ? $branding['accent_hex'] : '',
                'rail_hex'              => isset($branding['rail_hex']) ? $branding['rail_hex'] : '',
                'login_bg'              => isset($branding['login_bg']) ? $branding['login_bg'] : '',
                'custom_login_text'     => isset($misc['custom_login_text']) ? $misc['custom_login_text'] : '',
                'custom_login_link'     => isset($misc['custom_login_link']) ? $misc['custom_login_link'] : '',
                // default ON: only an explicit '0' means hidden
                'show_ispconfig_credit' => (isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0') ? '0' : '1',
                'show_theme_credit'     => (isset($branding['show_theme_credit']) && $branding['show_theme_credit'] === '0') ? '0' : '1',
                'show_version'          => (isset($branding['show_version']) && $branding['show_version'] === '0') ? '0' : '1',
                // derived, not stored: checked while ANY per-role news feed URL is set
                'show_news_feed'        => ((isset($misc['dashboard_atom_url_admin']) && $misc['dashboard_atom_url_admin'] !== '')
                                         || (isset($misc['dashboard_atom_url_reseller']) && $misc['dashboard_atom_url_reseller'] !== '')
                                         || (isset($misc['dashboard_atom_url_client']) && $misc['dashboard_atom_url_client'] !== '')) ? '1' : '0',
            );
        } else {
            //* Redisplay after a validation error: dataRecord IS the raw POST, and an
            //* unchecked checkbox is simply absent from a POST. tform_base::getHTML then
            //* falls back to each field's 'default' ('1'), so every white-label toggle the
            //* admin had just switched OFF re-renders as ON — and the next save writes
            //* those '1's back, silently un-white-labelling the panel. Normalise absent
            //* checkboxes to their explicit "off" value, exactly as onUpdateSave does.
            foreach($app->tform->formDef['tabs'][$this->active_tab]['fields'] as $key => $field) {
                if($field['formtype'] == 'CHECKBOX' && !isset($this->dataRecord[$key])) {
                    $this->dataRecord[$key] = $field['value'][0];
                }
            }
        }

        $record = $app->tform->getHTML($this->dataRecord, $this->active_tab, 'EDIT');
        $record['id'] = $this->id;
        $app->tpl->setVar($record);
    }

    function onShowEnd() {
        global $app;
        $this->render_logo_previews();
        //* the post-save redirect appends msg=saved (see list_default in the form
        //* definition) — without this banner a successful save is indistinguishable
        //* from a silently failed one
        if(isset($_GET['msg']) && $_GET['msg'] === 'saved' && $app->tform->errorMessage == '') {
            $app->tpl->setVar('msg', $app->tform->lng('settings_saved_txt'));
        }
        parent::onShowEnd();
    }

    //* Runs before the framework validates the POST. Users paste colours without
    //* the leading '#' (and colour pickers hand back lowercase) — normalise here
    //* so the REGEX validators accept what any reasonable person types.
    function onBeforeUpdate() {
        global $app, $conf;

        //* Demo mode writes nothing — refuse HERE rather than in onUpdateSave.
        //* tform_actions::onUpdate() tests errorMessage at line 118, BEFORE it calls
        //* onUpdateSave at line 123, and its redirect to list_default (which is
        //* "customizer_edit.php?id=1&msg=saved") at line 167 is unconditional. A message
        //* raised any later therefore cannot stop the panel cheerfully printing
        //* "Settings saved." over values that were never written, which makes a
        //* deliberately disabled demo panel look like a broken one.
        if($conf['demo_mode'] == true) {
            $app->tform->errorMessage .= $app->tform->lng('demo_mode_txt');
        }

        foreach(array('accent_hex', 'rail_hex', 'login_bg') as $k) {
            if(isset($this->dataRecord[$k]) && is_string($this->dataRecord[$k])) {
                $v = trim($this->dataRecord[$k]);
                if(preg_match('/^[0-9A-Fa-f]{6}$/', $v)) $v = '#' . $v;
                if(preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) $v = strtoupper($v);
                $this->dataRecord[$k] = $v;
            }
        }
        parent::onBeforeUpdate();
    }

    function onUpdateSave($sql) {
        global $app, $conf;
        if($_SESSION["s"]["user"]["typ"] != 'admin') die('This function needs admin privileges');
        $app->uses('ini_parser,getconf');

        $tab = $app->tform->getCurrentTab();

        //* unchecked checkboxes are absent from POST -> force their "off" value
        foreach($app->tform->formDef['tabs'][$tab]['fields'] as $key => $field) {
            if($field['formtype'] == 'CHECKBOX' && (!isset($this->dataRecord[$key]) || $this->dataRecord[$key] == '')) {
                $this->dataRecord[$key] = $field['value'][0];
            }
        }

        //* filter/validate the edited fields
        $clean = $app->tform->encode($this->dataRecord, $tab);

        //* Read the WHOLE config, then set only our keys -> everything else survives.
        //*
        //* Read the RAW column and parse it WITHOUT stripslashes — do NOT use
        //* getconf::get_global_config() here. That method does
        //* parse_ini_string(stripslashes($row['config'])), and NOTHING re-applies the
        //* escaping on the way back: ini_parser::get_ini_string() writes values verbatim
        //* and db::datalogUpdate() binds the blob as a query parameter. So a
        //* read-modify-write through getconf silently eats one backslash level from
        //* EVERY value in the file on EVERY save — including sections this module has no
        //* business touching, e.g. [mail] smtp_pass, where 'pa\ss' degrades to 'pass'
        //* and outbound mail authentication starts failing with nothing to explain it.
        //*
        //* Core's own admin/system_config_edit.php:143-188 has the identical shape, but
        //* that page is saved once in a blue moon while a branding page invites a dozen
        //* colour tweaks in a sitting — so we must not inherit it. Parsing the raw string
        //* means every value we do not own is carried through byte-identical, which is
        //* the only way to guarantee this module cannot alter ISPConfig's behaviour.
        //* bin/purge_branding.php already reads the column this way.
        $raw = $app->db->queryOneRecord("SELECT config FROM sys_ini WHERE sysini_id = 1");
        $config = $app->ini_parser->parse_ini_string(isset($raw['config']) ? (string)$raw['config'] : '');
        if(!is_array($config)) $config = array();
        if(!isset($config['branding']) || !is_array($config['branding'])) $config['branding'] = array();
        if(!isset($config['misc']) || !is_array($config['misc']))         $config['misc'] = array();

        foreach($this->branding_keys as $k) {
            $config['branding'][$k] = isset($clean[$k]) ? $clean[$k] : '';
        }
        foreach($this->misc_keys as $k) {
            $config['misc'][$k] = isset($clean[$k]) ? $clean[$k] : '';
        }

        //* News feed toggle -> the three stock per-role [misc] atom keys.
        //*
        //* Core hides the dashboard feed for a role whose URL is empty, so "off" has to
        //* blank all three. Those keys are CORE-owned though — an admin may have set a
        //* private feed under System > Interface Config, and may deliberately have left
        //* reseller/client blank so those roles see nothing at all. Blanking without a
        //* copy destroys both choices, and refilling all three with the ISPConfig default
        //* on the way back re-leaks ISPConfig branding to exactly the roles a white-label
        //* panel must not show it to.
        //*
        //* So: stash each non-empty URL into module-owned [branding] keys before blanking,
        //* and restore from that stash on the off->on transition. A role that had no URL
        //* stays empty. The ISPConfig default is written only when there is nothing to
        //* restore at all (i.e. a first-ever enable). This makes the round trip lossless,
        //* which is what the field's hint text has always promised.
        $atom_keys = array(
            'dashboard_atom_url_admin'    => 'news_url_admin',
            'dashboard_atom_url_reseller' => 'news_url_reseller',
            'dashboard_atom_url_client'   => 'news_url_client',
        );
        $show_news = isset($clean['show_news_feed']) ? $clean['show_news_feed'] : '1';
        if($show_news === '0') {
            foreach($atom_keys as $k => $stash) {
                if(isset($config['misc'][$k]) && $config['misc'][$k] !== '') {
                    $config['branding'][$stash] = $config['misc'][$k];
                }
                $config['misc'][$k] = '';
            }
        } else {
            //* Only act on the off->on transition (all three empty). While the feed is
            //* already on, leave every key untouched — an admin may have deliberately
            //* blanked a single role's URL, and refilling it on unrelated saves would
            //* silently clobber that choice.
            $any_set = false;
            foreach($atom_keys as $k => $stash) {
                if(isset($config['misc'][$k]) && $config['misc'][$k] !== '') { $any_set = true; break; }
            }
            if(!$any_set) {
                $restored = false;
                foreach($atom_keys as $k => $stash) {
                    if(isset($config['branding'][$stash]) && $config['branding'][$stash] !== '') {
                        $config['misc'][$k] = $config['branding'][$stash];
                        $restored = true;
                    }
                }
                //* nothing was ever stashed -> first-ever enable, seed the stock feed
                if(!$restored) {
                    foreach($atom_keys as $k => $stash) {
                        $config['misc'][$k] = 'https://www.ispconfig.org/atom';
                    }
                }
            }
            //* the stash has served its purpose (or is stale) — drop it so a later manual
            //* edit under System > Interface Config is never resurrected by a future toggle
            foreach($atom_keys as $k => $stash) {
                unset($config['branding'][$stash]);
            }
        }

        $config_str = $app->ini_parser->get_ini_string($config);
        if($conf['demo_mode'] != true) {
            $app->db->datalogUpdate('sys_ini', array("config" => $config_str), 'sysini_id', 1);
        }
    }

    /**
     * Both preview rows, each resolved and drawn exactly as the live panel will
     * resolve and draw it.
     *
     * All four stored values are handed to the shared resolver, not just the
     * uploaded ones: a valid logo_url is what the panel actually renders, so
     * previewing custom_logo alone made this page contradict the panel. The same
     * trap now exists twice over, plus the cross-variant fallback — hence one
     * resolver, used here and by logo_upload.php and mirrored by both readers.
     */
    private function render_logo_previews() {
        global $app;
        $sys_ini = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
        $app->uses('getconf');
        $branding = $app->getconf->get_global_config('branding');
        if(!is_array($branding)) $branding = array();

        $resolved = customizer_logo_resolve(array(
            'custom_logo'      => (is_array($sys_ini) && isset($sys_ini['custom_logo'])) ? $sys_ini['custom_logo'] : '',
            'logo_on_dark'     => isset($branding['logo_on_dark']) ? $branding['logo_on_dark'] : '',
            'logo_url'         => isset($branding['logo_url']) ? $branding['logo_url'] : '',
            'logo_url_on_dark' => isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
        ));

        //* $app->lng(), not $app->tform->lng(): these three live in the module
        //* wordbook (lib/lang/<lang>.lng) rather than the tform one, because
        //* logo_upload.php renders the same previews and has no tform at all.
        $no_logo_txt = $app->lng('no_logo_set_txt');
        $app->tpl->setVar('used_logo', customizer_logo_preview_html($resolved['on_light'], 'on_light', $no_logo_txt, $app->lng('logo_fallback_from_dark_txt')));
        $app->tpl->setVar('used_logo_on_dark', customizer_logo_preview_html($resolved['on_dark'], 'on_dark', $no_logo_txt, $app->lng('logo_fallback_from_light_txt')));
    }
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();
