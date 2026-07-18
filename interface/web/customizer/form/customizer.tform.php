<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * The single global row sys_ini (sysini_id = 1) is the store. The controller
 * (customizer_edit.php) reads/writes two INI sections inside sys_ini.config:
 *   [branding]  accent_hex, rail_hex, login_bg, show_ispconfig_credit, show_theme_credit
 *   [misc]      company_name, custom_login_text, custom_login_link  (existing core keys)
 * The logo lives in the sys_ini.custom_logo column (handled by logo_upload.php).
 */

$form["title"]        = "customizer_title";
$form["description"]  = "customizer_desc_txt";
$form["name"]         = "customizer";
$form["action"]       = "customizer_edit.php";
$form["db_table"]     = "sys_ini";
$form["db_table_idx"] = "sysini_id";
$form["db_history"]   = "no";
$form["tab_default"]  = "branding";
$form["list_default"] = "customizer_edit.php?id=1";
$form["auth"]         = 'yes';

$form["auth_preset"]["userid"]     = 0;
$form["auth_preset"]["groupid"]    = 0;
$form["auth_preset"]["perm_user"]  = 'riud';
$form["auth_preset"]["perm_group"] = 'riud';
$form["auth_preset"]["perm_other"] = '';

$form["tabs"]['branding'] = array(
    'title'    => "customizer_title",
    'width'    => 100,
    'template' => "templates/customizer_edit.htm",
    'fields'   => array(

        'company_name' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'STRIPTAGS'),
                1 => array('event' => 'SAVE', 'type' => 'STRIPNL'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'accent_hex' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/', 'errmsg' => 'accent_hex_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'rail_hex' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/', 'errmsg' => 'rail_hex_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'login_bg' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/', 'errmsg' => 'login_bg_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'custom_login_text' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'STRIPTAGS'),
                1 => array('event' => 'SAVE', 'type' => 'STRIPNL'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'custom_login_link' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'STRIPTAGS'),
                1 => array('event' => 'SAVE', 'type' => 'STRIPNL'),
            ),
            //* core login/index.php renders this unescaped inside <a href="...">, so the
            //* value must not contain a quote/space/angle-bracket that could break out of
            //* the attribute. Anchored, and no attribute-breaking chars allowed.
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(https?:\/\/[^\s"\'<>]+)?$/', 'errmsg' => 'login_link_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'show_ispconfig_credit' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default'  => '1',
            'value'    => array(0 => '0', 1 => '1')
        ),

        'show_theme_credit' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'CHECKBOX',
            'default'  => '1',
            'value'    => array(0 => '0', 1 => '1')
        ),

    )
);
