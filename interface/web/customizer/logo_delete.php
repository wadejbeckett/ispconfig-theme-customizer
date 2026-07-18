<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * Clears sys_ini.custom_logo (the theme then falls back to its own default).
 * Reached via data-load-content (an XHR GET); we require the XHR header so a
 * cross-site <img>/<form> cannot trigger it, and re-load the editor after.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* only accept same-origin AJAX (ispconfig.js loadContent sets this header)
if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    die('Direct access not allowed.');
}

//* require a valid CSRF token too (defence in depth; matches ISPConfig's own
//* delete flow, which enforces csrf_token_check('GET')). The editor's Remove
//* control passes the page's token as _csrf_id/_csrf_key query params.
$app->auth->csrf_token_check('GET');

if($conf['demo_mode'] != true) {
    $app->db->query("UPDATE sys_ini SET custom_logo = '' WHERE sysini_id = 1");
}

header('Content-Type: text/plain; charset=utf-8');
echo "HEADER_REDIRECT:customizer/customizer_edit.php?id=1";
