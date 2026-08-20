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
require_once __DIR__ . '/lib/dashlets.inc.php';

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
     * fields belong here. [branding] logo_on_dark and [branding] favicon are
     * deliberately absent: they are the UPLOADED images, written by
     * logo_upload.php, and listing either would blank the operator's artwork on
     * the next click of Save. They survive every save because onUpdateSave
     * re-parses the stored blob and only assigns the keys named below. Same
     * reasoning as sys_ini.custom_logo, which is a column and was never a
     * candidate for this list. */
    private $branding_keys = array('logo_url', 'logo_url_on_dark', 'logo_variant_nav', 'logo_variant_login', 'favicon_url', 'accent_hex', 'rail_hex', 'login_bg', 'show_ispconfig_credit', 'show_theme_credit', 'show_version');
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
                //* Whitelisted rather than passed through, for two reasons that are not
                //* the usual paranoia. (1) These two MUST be present in this array:
                //* tform_base::_decode reads $record[$key] unguarded at
                //* tform_base.inc.php:196 and only pre-seeds the index when the field
                //* carries 'filters' (:190-191) — these carry none, so an omitted key is
                //* a PHP 8 "Undefined array key" warning printed into the settings page.
                //* (2) An unrecognised stored value would match no option key at
                //* tform_base.inc.php:504, select nothing, and leave the browser showing
                //* the first option anyway — collapsing it to '' here means the field the
                //* admin is looking at agrees with what the next Save will write.
                'logo_variant_nav'      => (isset($branding['logo_variant_nav'])   && ($branding['logo_variant_nav']   === 'on_light' || $branding['logo_variant_nav']   === 'on_dark')) ? $branding['logo_variant_nav']   : '',
                'logo_variant_login'    => (isset($branding['logo_variant_login']) && ($branding['logo_variant_login'] === 'on_light' || $branding['logo_variant_login'] === 'on_dark')) ? $branding['logo_variant_login'] : '',
                'favicon_url'           => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
                'accent_hex'            => isset($branding['accent_hex']) ? $branding['accent_hex'] : '',
                'rail_hex'              => isset($branding['rail_hex']) ? $branding['rail_hex'] : '',
                'login_bg'              => isset($branding['login_bg']) ? $branding['login_bg'] : '',
                'custom_login_text'     => isset($misc['custom_login_text']) ? $misc['custom_login_text'] : '',
                'custom_login_link'     => isset($misc['custom_login_link']) ? $misc['custom_login_link'] : '',
                // default ON: only an explicit '0' means hidden
                'show_ispconfig_credit' => (isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0') ? '0' : '1',
                'show_theme_credit'     => (isset($branding['show_theme_credit']) && $branding['show_theme_credit'] === '0') ? '0' : '1',
                'show_version'          => (isset($branding['show_version']) && $branding['show_version'] === '0') ? '0' : '1',
                //* derived, not stored: this switch owns a row in sys_config, not
                //* a key in the INI blob, because that row is what ISPConfig
                //* itself consults before it builds the dashlet. See
                //* lib/dashlets.inc.php.
                'show_donation_dashlet' => $this->donation_dashlet_shown() ? '1' : '0',
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
            //*
            //* Deliberately CHECKBOX only — do not widen this test to the SELECT
            //* fields. A <select> is a successful control, so it posts on every real
            //* submit including when the chosen value is the empty "automatic" one,
            //* and ISPConfig submits with jQuery .serialize() (ispconfig.js:164),
            //* which includes it; there is nothing here to repair. Widening it would
            //* break something instead: $field['value'][0] is the checkbox "off"
            //* value, and a variant field has no index 0 at all (its option keys are
            //* '', 'on_light', 'on_dark'), so a SELECT swept into this loop would get
            //* an undefined-index warning and a null. The one case a real POST cannot
            //* produce — a crafted POST that omits the field — is handled earlier in
            //* onBeforeUpdate, which runs before this branch can be reached. Its twin
            //* in onUpdateSave also tests `== ''`, which is a second reason to keep
            //* the two loops CHECKBOX-only: '' is what "automatic" is STORED as.
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
        $this->render_image_previews();
        //* the post-save redirect appends msg=saved (see list_default in the form
        //* definition) — without this banner a successful save is indistinguishable
        //* from a silently failed one
        if(isset($_GET['msg']) && $_GET['msg'] === 'saved' && $app->tform->errorMessage == '') {
            $app->tpl->setVar('msg', $app->tform->lng('settings_saved_txt'));
        }
        parent::onShowEnd();
    }

    //* Runs before the framework validates the POST, which is the only place a
    //* value can still be repaired. Two jobs: users paste colours without the
    //* leading '#' (and colour pickers hand back lowercase), so normalise those
    //* into what the REGEX validators accept; and make sure every field the
    //* validators are about to run on is actually a string, which a POST is not
    //* obliged to be.
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

        //* The two logo-variant selects: guarantee a STRING is present before the
        //* framework touches the POST. Neither case below is reachable from a browser
        //* — a <select> always posts, and only ever one of its own option values — but
        //* both are one crafted POST away from an authenticated admin, and neither
        //* fails softly:
        //*   absent  -> tform_base::_decode reads $record[$key] unguarded
        //*              (tform_base.inc.php:196; the pre-seed at :190-191 needs
        //*              'filters', which these fields deliberately do not have), so
        //*              the error-redisplay render prints a PHP 8 warning into the page;
        //*   an array (logo_variant_nav[]=x) -> tform_base::_encode converts arrays to
        //*              strings for RADIO and CHECKBOX only (:819-823) and hands the
        //*              array straight to validateField, where preg_match() with a
        //*              non-string subject is a TypeError on PHP 8 — a fatal on an
        //*              admin page instead of a validation error.
        //* A STRING is left untouched on purpose: the REGEX validator must stay the
        //* thing that rejects a bad token, so a wrong value is reported rather than
        //* silently healed into "automatic". '' is a legitimate posted value here (it
        //* IS automatic) and must survive this method unchanged.
        //*
        //* The array case is why this goes through a helper rather than assigning ''.
        //* Rewriting an array to '' satisfied the string requirement by writing a
        //* VALID value: the validator then passed, onUpdateSave wrote it, and the page
        //* redirected to "Settings saved." having quietly reset the operator's stored
        //* choice to automatic. customizer_logo_variant_posted() yields a token the
        //* validator rejects instead, so a malformed POST is reported like every other
        //* bad value on this form. Only a genuinely ABSENT field still becomes ''.
        foreach(array('logo_variant_nav', 'logo_variant_login') as $k) {
            $this->dataRecord[$k] = customizer_logo_variant_posted(
                isset($this->dataRecord[$k]) ? $this->dataRecord[$k] : null
            );
        }

        parent::onBeforeUpdate();
    }

    function onUpdateSave($sql) {
        global $app, $conf;
        if($_SESSION["s"]["user"]["typ"] != 'admin') die('This function needs admin privileges');
        $app->uses('ini_parser,getconf');

        $tab = $app->tform->getCurrentTab();

        //* unchecked checkboxes are absent from POST -> force their "off" value.
        //* CHECKBOX only, and the `== ''` half of the test is why it must stay that
        //* way: '' is the STORED value of an "automatic" logo variant, so extending
        //* this loop to SELECT would rewrite the admin's Automatic choice on every
        //* save and make the setting impossible to turn back off. A SELECT needs no
        //* equivalent here — it always posts, and if a crafted POST omits it,
        //* _encode already yields '' for a missing VARCHAR (tform_base.inc.php:830)
        //* and the $branding_keys loop below writes '' for a missing $clean key.
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
            //* Not part of the INI blob: this one lives in sys_config, because
            //* that row is what ISPConfig reads before it builds the dashlet.
            $this->save_donation_dashlet(isset($clean['show_donation_dashlet']) ? $clean['show_donation_dashlet'] : '1');
        }
    }

    /**
     * Is ISPConfig currently showing its donation dashlet?
     *
     * $app->conf() is core's own accessor for the sys_config table
     * (app.inc.php:171-185) and returns null when the row does not exist, which
     * is exactly the state customizer_donation_shown() is documented to treat as
     * "shown". Going through it rather than hand-writing the SELECT keeps this
     * on a supported interface and keeps the reserved word `group` core's
     * problem rather than ours.
     */
    private function donation_dashlet_shown() {
        global $app;
        $stored = $app->conf('interface', 'hide_donation_dashlet');
        return customizer_donation_shown(($stored === null) ? null : (string)$stored, time());
    }

    /**
     * Write the operator's choice into core's own row.
     *
     * Same accessor, which issues REPLACE INTO — correct and atomic here because
     * sys_config's PRIMARY KEY is (`group`, `name`) (install/sql/ispconfig3.sql:
     * 1657-1662), so the replace matches the existing row rather than adding a
     * second one.
     *
     * "Shown" writes '0' rather than deleting the row. Core reads a missing row
     * and an expired one identically (dashboard.php:225), so both express the
     * same thing, and an explicit past timestamp says "this operator chose to
     * show it" where a missing row cannot be told apart from a panel that has
     * never been touched.
     *
     * Deliberately NOT datalogUpdate(): sys_config is interface-local state that
     * no server-side plugin consumes, and both of core's own writers — the
     * dashboard's Hide button and $app->conf() itself — use a plain query. A
     * datalog entry here would queue a job on every server in the installation
     * because an admin ticked a checkbox about their own dashboard.
     */
    private function save_donation_dashlet($show) {
        global $app;
        $app->conf('interface', 'hide_donation_dashlet',
            customizer_donation_hide_value($show === '1', time()));
    }

    /**
     * All three preview rows — the two logo variants and the favicon — each
     * resolved and drawn exactly as the live panel will resolve and draw it.
     *
     * Every stored value is handed to the shared resolvers, not just the
     * uploaded ones: a valid logo_url is what the panel actually renders, so
     * previewing custom_logo alone made this page contradict the panel. The same
     * trap exists for every slot (favicon_url beats favicon in exactly the same
     * way) — hence one resolver per model, used here and by logo_upload.php and
     * mirrored by the designs' brand.php / favicon.php readers.
     */
    private function render_image_previews() {
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

        //* On a validation-error redisplay tform re-renders the RAW POST, so the
        //* two selects show what the operator just chose while this method reads
        //* the STORED blob — the swatches would describe the OLD choice under a
        //* control showing the new one, one page answering the same question two
        //* ways, which is the contradiction the preview exists to remove.
        //*
        //* Applied here rather than above $resolved because the two are different
        //* questions: $resolved is which ARTWORK exists in each slot, which no
        //* variant preference can change, while $surfaces is which slot each
        //* surface asks for — the only thing these keys decide.
        //*
        //* On a normal render $this->dataRecord is the stored config re-read by
        //* onShowEdit, so this is a no-op with one useful exception: onShowEdit
        //* collapses an unrecognised stored value to '' for the select, and
        //* taking the value from there makes the preview agree with the control
        //* about that too.
        $branding = customizer_branding_with_posted_variants($branding, $this->dataRecord);

        //* Which surfaces of EVERY INSTALLED design use which mark, so each
        //* preview is drawn on the colour that will really be behind it. It has
        //* to be every design, not just the active one: logo_variant_* is stored
        //* once and obeyed by all of them, while "nav" is navy on clarity and
        //* stock's #F2F5F7 on classic — so a choice made to rescue one design can
        //* make the other's logo invisible, and the admin needs to see that on
        //* this page rather than hear it from a client. The active design leads
        //* because it is the one they are looking at. A design this extension
        //* does not ship contributes nothing and every preview falls back to its
        //* pre-surface swatch, so a third-party theme degrades instead of being
        //* described wrongly.
        $surfaces = customizer_logo_surfaces_all(
            customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : ''),
            $branding,
            array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt'))
        );

        //* $app->lng(), not $app->tform->lng(): these six live in the module
        //* wordbook (lib/lang/<lang>.lng) rather than the tform one, because
        //* logo_upload.php renders the same previews and has no tform at all.
        $no_logo_txt = $app->lng('no_logo_set_txt');
        $app->tpl->setVar('used_logo', customizer_logo_preview_html($resolved['on_light'], 'on_light', $no_logo_txt, $app->lng('logo_fallback_from_dark_txt'), $surfaces));
        $app->tpl->setVar('used_logo_on_dark', customizer_logo_preview_html($resolved['on_dark'], 'on_dark', $no_logo_txt, $app->lng('logo_fallback_from_light_txt'), $surfaces));

        //* getconf's blob is fine to read the favicon values from — this is a
        //* pure READ path, so its stripslashes has no missing counterpart to
        //* damage anything. (The write paths must not use it; see onUpdateSave.)
        //* Both favicon values are backslash-free by construction anyway: the
        //* base64 alphabet has none, and the reference validator rejects them.
        $app->tpl->setVar('used_favicon', customizer_favicon_preview_html(
            customizer_favicon_resolve(array(
                'favicon'     => isset($branding['favicon']) ? $branding['favicon'] : '',
                'favicon_url' => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
            )),
            $app->lng('no_favicon_set_txt'),
            $app->lng('favicon_url_wins_txt')
        ));
    }
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();
