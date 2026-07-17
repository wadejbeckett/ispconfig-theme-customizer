<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-customizer
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

class page_action extends tform_actions {

    /* the keys this module owns in each INI section */
    private $branding_keys = array('accent_hex', 'rail_hex', 'login_bg', 'show_ispconfig_credit', 'show_theme_credit');
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
                'accent_hex'            => isset($branding['accent_hex']) ? $branding['accent_hex'] : '',
                'rail_hex'              => isset($branding['rail_hex']) ? $branding['rail_hex'] : '',
                'login_bg'              => isset($branding['login_bg']) ? $branding['login_bg'] : '',
                'custom_login_text'     => isset($misc['custom_login_text']) ? $misc['custom_login_text'] : '',
                'custom_login_link'     => isset($misc['custom_login_link']) ? $misc['custom_login_link'] : '',
                // default ON: only an explicit '0' means hidden
                'show_ispconfig_credit' => (isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0') ? '0' : '1',
                'show_theme_credit'     => (isset($branding['show_theme_credit']) && $branding['show_theme_credit'] === '0') ? '0' : '1',
            );
        }

        $record = $app->tform->getHTML($this->dataRecord, $this->active_tab, 'EDIT');
        $record['id'] = $this->id;
        $app->tpl->setVar($record);
    }

    function onShowEnd() {
        global $app;
        $app->tpl->setVar('used_logo', $this->render_logo_preview());
        parent::onShowEnd();
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

        //* read the WHOLE config, then set only our keys -> everything else survives
        $config = $app->getconf->get_global_config();
        if(!is_array($config)) $config = array();
        if(!isset($config['branding']) || !is_array($config['branding'])) $config['branding'] = array();
        if(!isset($config['misc']) || !is_array($config['misc']))         $config['misc'] = array();

        foreach($this->branding_keys as $k) {
            $config['branding'][$k] = isset($clean[$k]) ? $clean[$k] : '';
        }
        foreach($this->misc_keys as $k) {
            $config['misc'][$k] = isset($clean[$k]) ? $clean[$k] : '';
        }

        $config_str = $app->ini_parser->get_ini_string($config);
        if($conf['demo_mode'] != true) {
            $app->db->datalogUpdate('sys_ini', array("config" => $config_str), 'sysini_id', 1);
        }
    }

    private function render_logo_preview() {
        global $app;
        $sys_ini = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
        $logo = (is_array($sys_ini) && isset($sys_ini['custom_logo'])) ? (string)$sys_ini['custom_logo'] : '';
        //* only render a value that is a real image data-URI (defence-in-depth vs a tampered column)
        if($logo !== '' && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $logo)) {
            return '<img src="' . $logo . '" alt="" style="max-height:48px;max-width:220px;background:#01243D;padding:6px 12px;border-radius:4px" />';
        }
        return '<em>' . $app->lng('no_logo_set_txt') . '</em>';
    }
}

$app->tform_actions = new page_action;
$app->tform_actions->onLoad();
