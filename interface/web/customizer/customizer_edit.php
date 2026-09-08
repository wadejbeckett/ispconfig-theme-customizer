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
    private $branding_keys = array('logo_url', 'logo_url_on_dark', 'logo_variant_nav', 'logo_variant_login', 'favicon_url', 'accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg', 'show_ispconfig_credit', 'show_theme_credit', 'show_version', 'show_design_picker');
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
                'rail_hex_light'        => isset($branding['rail_hex_light']) ? $branding['rail_hex_light'] : '',
                'login_bg'              => isset($branding['login_bg']) ? $branding['login_bg'] : '',
                'custom_login_text'     => isset($misc['custom_login_text']) ? $misc['custom_login_text'] : '',
                'custom_login_link'     => isset($misc['custom_login_link']) ? $misc['custom_login_link'] : '',
                // default ON: only an explicit '0' means hidden
                'show_ispconfig_credit' => (isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0') ? '0' : '1',
                'show_theme_credit'     => (isset($branding['show_theme_credit']) && $branding['show_theme_credit'] === '0') ? '0' : '1',
                'show_version'          => (isset($branding['show_version']) && $branding['show_version'] === '0') ? '0' : '1',
                'show_design_picker'    => (isset($branding['show_design_picker']) && $branding['show_design_picker'] === '0') ? '0' : '1',
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
        $this->publish_field_error_map();
        $this->publish_hint_labels();
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

        //* Guarantee a STRING reaches the framework for every field it is about
        //* to filter and validate. CHECKBOX is excluded on purpose: _encode
        //* already converts arrays to strings for RADIO and CHECKBOX
        //* (tform_base.inc.php:819-823), and the two CHECKBOX-only loops
        //* elsewhere in this file must stay CHECKBOX-only for the reasons
        //* written beside them. This loop is their exact complement.
        //*
        //* $this->active_tab is NOT reliable here: tform_actions only sets it in
        //* onShow() (the display path). The save path is
        //* onLoad()->onSubmit()->onUpdate()->onBeforeUpdate(), which never touches
        //* active_tab, so on a real save this method's copy of it is unset/null and
        //* formDef['tabs'][null] does not exist -> the foreach would iterate
        //* nothing and every field below would reach trim()/preg_match()
        //* unguarded. getCurrentTab() reads the session tab pin set at the top of
        //* this file (and is what onUpdateSave() already keys its own formDef
        //* lookup on), so use that instead. If it is ever empty, or names a tab
        //* this form doesn't have, fall back to every tab's fields so the guard
        //* can never be silently skipped.
        //*
        //* A STRING is left untouched on purpose: the validator must stay the
        //* thing that rejects a bad token, so a wrong value is reported rather
        //* than silently healed. '' is a legitimate posted value for several of
        //* these fields and must survive unchanged.
        $tab = $app->tform->getCurrentTab();
        if($tab !== '' && isset($app->tform->formDef['tabs'][$tab])) {
            $tabs_to_guard = array($tab => $app->tform->formDef['tabs'][$tab]);
        } else {
            $tabs_to_guard = $app->tform->formDef['tabs'];
        }
        foreach($tabs_to_guard as $tab_def) {
            foreach($tab_def['fields'] as $key => $field) {
                if($field['formtype'] === 'CHECKBOX') continue;
                $this->dataRecord[$key] = customizer_posted_string(
                    isset($this->dataRecord[$key]) ? $this->dataRecord[$key] : null
                );
            }
        }

        //* Users paste colours without the leading '#', and colour pickers hand
        //* back lowercase — normalise those into what the REGEX validators
        //* accept. /D on both patterns for the same reason the validators carry
        //* it: without it "$" also matches before a final newline, so a pasted
        //* trailing LF would be '#'-prefixed and upper-cased here and then pass
        //* a validator that should have rejected it. The SAVE-time TRIM filter
        //* runs later, inside encode(); this runs on the raw POST, so it does
        //* its own trim() first.
        foreach(array('accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg') as $k) {
            $v = trim($this->dataRecord[$k]);
            if(preg_match('/^[0-9A-Fa-f]{6}$/D', $v)) $v = '#' . $v;
            if(preg_match('/^#[0-9A-Fa-f]{6}$/D', $v)) $v = strtoupper($v);
            $this->dataRecord[$k] = $v;
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

        //* News feed toggle -> the three stock per-role [misc] atom keys, and
        //* the module-owned stash that makes the round trip lossless. The whole
        //* decision is customizer_news_feed_apply() in lib/dashlets.inc.php,
        //* where it can be tested without a database; this is the plumbing.
        $atom_keys = customizer_news_feed_keys();
        $misc_in   = array();
        $stash_in  = array();
        foreach($atom_keys as $k => $stash_key) {
            $misc_in[$k]        = isset($config['misc'][$k]) ? $config['misc'][$k] : '';
            $stash_in[$stash_key] = isset($config['branding'][$stash_key]) ? $config['branding'][$stash_key] : '';
        }
        $news = customizer_news_feed_apply($misc_in, $stash_in,
            isset($clean['show_news_feed']) ? $clean['show_news_feed'] : '1');

        foreach($atom_keys as $k => $stash_key) {
            $config['misc'][$k] = $news['misc'][$k];
            //* An empty stash is an ABSENT key, not a key with an empty value:
            //* the stash is ours and it should not appear in the blob at all
            //* once it has been consumed.
            if($news['stash'][$stash_key] === '') {
                unset($config['branding'][$stash_key]);
            } else {
                $config['branding'][$stash_key] = $news['stash'][$stash_key];
            }
        }

        $config_str = $app->ini_parser->get_ini_string($config);
        if($conf['demo_mode'] != true) {
            $app->db->datalogUpdate('sys_ini', array("config" => $config_str), 'sysini_id', 1);

            //* datalogUpdate() CANNOT report a failure: it discards the return
            //* value of its own query() and returns true unconditionally
            //* (db_mysql.inc.php:811-843). And tform_actions::onUpdate()
            //* redirects to list_default — "customizer_edit.php?id=1&msg=saved"
            //* — unconditionally at its line 167, so a write that never landed
            //* printed "Changes saved." over values that were never stored.
            //*
            //* Read the column back and compare it byte for byte. Raising
            //* errorMessage here would not help — the framework tested it
            //* BEFORE calling this hook, which is the same trap the demo-mode
            //* refusal in onBeforeUpdate is placed early to avoid. $app->error()
            //* renders core's error template and die()s, which is the only thing
            //* at this point in the flow that can stop the page claiming
            //* success. It also stops before save_donation_dashlet(), which is
            //* correct: the config write is the one that failed.
            $after = $app->db->queryOneRecord("SELECT config FROM sys_ini WHERE sysini_id = 1");
            if(!is_array($after) || !isset($after['config']) || (string)$after['config'] !== $config_str) {
                $app->error($app->tform->lng('save_failed_txt'));
            }

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
        //* Hoisted because the legend's first status fact names the design the
        //* operator is looking at, and customizer_installed_designs() is what
        //* decides which that is — it puts the active design first.
        $designs  = customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : '');
        $surfaces = customizer_logo_surfaces_all(
            $designs,
            $branding,
            array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt'))
        );

        //* $app->lng(), not $app->tform->lng(): these six live in the module
        //* wordbook (lib/lang/<lang>.lng) rather than the tform one, because
        //* logo_upload.php renders the same previews and has no tform at all.
        $no_logo_txt = $app->lng('no_logo_set_txt');
        //* Each logo row is rendered in TWO halves into two slots: the 108px
        //* mark column beside the uploader takes the first surface's swatch, and
        //* the full-width strip under the block takes every other surface's. One
        //* container held all of them and, on a panel with both designs
        //* installed, stacked three swatches down over the path field below.
        $fb_dark  = $app->lng('logo_fallback_from_dark_txt');
        $fb_light = $app->lng('logo_fallback_from_light_txt');
        $app->tpl->setVar('used_logo', customizer_logo_preview_html($resolved['on_light'], 'on_light', $no_logo_txt, $fb_dark, $surfaces, 'first'));
        $app->tpl->setVar('used_logo_more', customizer_logo_preview_html($resolved['on_light'], 'on_light', $no_logo_txt, $fb_dark, $surfaces, 'more'));
        $app->tpl->setVar('used_logo_on_dark', customizer_logo_preview_html($resolved['on_dark'], 'on_dark', $no_logo_txt, $fb_light, $surfaces, 'first'));
        $app->tpl->setVar('used_logo_on_dark_more', customizer_logo_preview_html($resolved['on_dark'], 'on_dark', $no_logo_txt, $fb_light, $surfaces, 'more'));

        //* getconf's blob is fine to read the favicon values from — this is a
        //* pure READ path, so its stripslashes has no missing counterpart to
        //* damage anything. (The write paths must not use it; see onUpdateSave.)
        //* Both favicon values are backslash-free by construction anyway: the
        //* base64 alphabet has none, and the reference validator rejects them.
        $favicon = customizer_favicon_resolve(array(
            'favicon'     => isset($branding['favicon']) ? $branding['favicon'] : '',
            'favicon_url' => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
        ));
        $app->tpl->setVar('used_favicon', customizer_favicon_preview_html(
            $favicon,
            $app->lng('no_favicon_set_txt'),
            $app->lng('favicon_url_wins_txt')
        ));

        $this->publish_brand_summary($designs, $resolved, $favicon);
    }

    /**
     * The three status facts in the legend under the proof.
     *
     * The wording is customizer_brand_summary()'s, not this page's, because the
     * legend is refreshed after an upload through customizer/preview.php — an
     * upload replaces three slots in place and never reloads the page — and two
     * copies of the sentence would eventually disagree. $app->lng(), not
     * $app->tform->lng(): these six live in the MODULE wordbook so the endpoint,
     * which has no tform at all, can reach them.
     *
     * Every value is already resolved by the caller and passed in rather than
     * re-read here: the fact and the swatch beside it describe one object, and
     * a second read is how they would come to describe two.
     */
    private function publish_brand_summary($designs, $resolved, $favicon) {
        global $app;
        $facts = customizer_brand_summary(
            //* is_array first, for the same reason the payload does it:
            //* isset($x[0]) is TRUE for a non-empty string.
            (is_array($designs) && isset($designs[0]) && is_string($designs[0])) ? $designs[0] : '',
            $resolved, $favicon,
            array(
                'design'       => $app->lng('summary_design_txt'),
                'marks_none'   => $app->lng('summary_marks_none_txt'),
                'marks_one'    => $app->lng('summary_marks_one_txt'),
                'marks'        => $app->lng('summary_marks_txt'),
                'favicon'      => $app->lng('summary_favicon_txt'),
                'favicon_none' => $app->lng('summary_favicon_none_txt'),
            )
        );
        $app->tpl->setVar('summary_fact_design',  $facts['design']);
        $app->tpl->setVar('summary_fact_marks',   $facts['marks']);
        $app->tpl->setVar('summary_fact_favicon', $facts['favicon']);
    }

    /**
     * The accessible name of every "?" disclosure on the page.
     *
     * Each "?" is a <summary>, which announces as a button; without a name it
     * announces as the character "?" and nothing else. The name is
     * hint_more_txt ("More about %s") with the label of the thing the
     * disclosure explains substituted in, so a screen-reader user hears "More
     * about Placement" rather than fifteen identical buttons.
     *
     * It is built here rather than in the template because vlibTemplate cannot
     * compose two strings, and it is str_replace('%s', …) rather than a printf
     * family call because both halves are values a translator edits: one
     * carrying a stray '%' would make such a call raise a ValueError on PHP 8,
     * which on this page is a fatal caused by a wordbook file. A translation
     * that drops the placeholder simply loses the label and keeps the sentence.
     *
     * BOTH halves land inside a double-quoted aria-label with no escaping at
     * the call site, so hint_more_txt and all fifteen labels are on
     * lang_check.php's $HTML_ATTR_WB_KEYS list — see the docblock there.
     *
     * The fifteen keys come from customizer_hint_label_keys() rather than a
     * second literal here, so Task 7's lang_check.php cross-reference and this
     * page can never name a different fifteen.
     *
     * logo_upload.php renders this same template with no tform, so none of
     * these vars is set there and vlibTemplate removes them. That response
     * carries empty aria-labels; harmless, because the uploader's driver reads
     * only #OKMsg/#errorMsg and the three mark slots out of it.
     */
    private function publish_hint_labels() {
        global $app;
        $pattern = $app->tform->lng('hint_more_txt');
        foreach(customizer_hint_label_keys() as $key) {
            $app->tpl->setVar('hint_' . $key, str_replace('%s', $app->tform->lng($key), $pattern));
        }
    }

    /**
     * Which field each validation message belongs to, as JSON for the page.
     *
     * tform reports validation failures as one banner of translated SENTENCES
     * with no field names in it. On a one-column form the offending control was
     * a short scroll away; on a two-column one a banner at the top is a message
     * about a control the operator may not even be able to see. The page marks
     * the field itself as well — and to do that it has to be able to tell which
     * message is whose.
     *
     * The map is built from the form definition, so it cannot drift from the
     * errmsg keys the validators actually name: field => the message that field
     * would produce. Entity-decoded because the banner is read back as
     * textContent, where "&times;" has already become "×".
     *
     * The four JSON_HEX_* flags escape every character that could end the HTML
     * attribute this lands in FROM INSIDE a message — but they do not touch
     * json_encode's own structural quotes, which is why the attribute in
     * customizer_edit.htm is single-quoted and JSON_HEX_APOS is the one of the
     * four that is load-bearing. Changing either without the other reopens the
     * hole; the template says so at the attribute too.
     *
     * A field whose errmsg key is missing from the wordbook is skipped rather
     * than mapped to the raw key: tform prints the key itself in that case, and
     * matching on it would mark a field on the strength of a bug elsewhere.
     */
    private function publish_field_error_map() {
        global $app;
        $map = array();
        foreach($app->tform->formDef['tabs'][$this->active_tab]['fields'] as $key => $field) {
            if(!isset($field['validators']) || !is_array($field['validators'])) continue;
            foreach($field['validators'] as $v) {
                if(!isset($v['errmsg'])) continue;
                $txt = $app->tform->lng($v['errmsg']);
                if(!is_string($txt) || $txt === '' || $txt === $v['errmsg']) continue;
                $map[$key] = html_entity_decode($txt, ENT_QUOTES, 'UTF-8');
            }
        }
        $app->tpl->setVar('field_errors_json',
            json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();
