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

//* Both empty ON PURPOSE: tform_base builds form_hint from title + description
//* and the stock tabbed_form wrapper renders that INSIDE a <h1> above the tab
//* strip (the description at heading size was the "text size is weird" bug).
//* With an empty form_hint the wrapper header is suppressed and the page header
//* in templates/customizer_edit.htm is the single source of title + description.
$form["title"]        = "";
$form["description"]  = "";
$form["name"]         = "customizer";
$form["action"]       = "customizer_edit.php";
$form["db_table"]     = "sys_ini";
$form["db_table_idx"] = "sysini_id";
$form["db_history"]   = "no";
$form["tab_default"]  = "branding";
//* after a save the framework redirects here; msg=saved makes the page show a
//* confirmation banner (there is no list view for this singleton form)
$form["list_default"] = "customizer_edit.php?id=1&msg=saved";
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

        'logo_url' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            //* consumed inside a CSS url("...") by brand-aware themes: forbid
            //* every character that could break out of that context. Only a
            //* root-relative path or an https URL (no http: an https panel
            //* would hit mixed-content blocking anyway).
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(https:\/\/[^\s"\'<>()\\\\]+|\/[^\s"\'<>()\\\\]+)?$/', 'errmsg' => 'logo_url_error_regex'),
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
