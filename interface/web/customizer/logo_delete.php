<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-theme-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * Clears ONE uploaded logo slot — sys_ini.custom_logo for the light-background
 * variant, [branding] logo_on_dark for the dark one. The other variant then
 * covers for it through the documented fallback, and with neither left the theme
 * shows its own default. See lib/preview.inc.php for the model.
 *
 * Reached via data-load-content (an XHR GET); we require the XHR header so a
 * cross-site <img>/<form> cannot trigger it, and re-load the editor after.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

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

//* WHICH SLOT to clear — validated against the same shared allowlist the
//* uploader uses, and never taken raw: this value selects what gets destroyed.
//* Absent means on_light, which is what this endpoint did before a second slot
//* existed, so an older cached page's Remove link still means what it meant.
//* Present but unknown is refused: quietly falling back to on_light would delete
//* the wrong logo and then cheerfully re-render the editor as if all were well.
//* Placed after the CSRF check, like the demo-mode refusal below, so a request
//* we have not authenticated never reaches any of our behaviour.
$slots = customizer_logo_slots();
$slot  = isset($_GET['slot']) ? (string)$_GET['slot'] : 'on_light';
if(!isset($slots[$slot])) die('Unknown logo slot.');

//* Demo mode has to REPORT the refusal, not swallow it. Skipping the UPDATE and
//* still emitting HEADER_REDIRECT would re-render the editor showing the same
//* logo, which reads as "Remove is broken". $app->error() dies with the theme's
//* error.tpl.htm, and because that body carries no HEADER_REDIRECT marker
//* loadContent() drops it straight into #pageContent — the same way core's own
//* delete endpoints (admin/server_del.php, help/faq_delete.php) refuse.
//* The refusal is deliberately placed after the CSRF check so a request we have
//* not authenticated never gets a rendered response.
if($conf['demo_mode'] == true) {
    //* $app->lng() would otherwise fall back to the global wordbook, which has
    //* no demo_mode_txt; load the module's own file (sanitised session language,
    //* English if that translation is not shipped) exactly as the uploader does.
    $lng = $app->functions->check_language($_SESSION['s']['language']);
    $lng_path = '/web/customizer/lib/lang/' . $lng . '_customizer.lng';
    if(!file_exists(ISPC_ROOT_PATH . $lng_path)) $lng_path = '/web/customizer/lib/lang/en_customizer.lng';
    $app->load_language_file($lng_path);
    $app->error($app->lng('demo_mode_txt'));
}

if($slot === 'on_dark') {
    //* Read-modify-write of sys_ini.config with the RAW column parsed and NO
    //* stripslashes — the same discipline as the uploader, customizer_edit.php's
    //* onUpdateSave and bin/purge_branding.php. Reading through
    //* getconf::get_global_config() would unescape every value in the file with
    //* nothing re-escaping on write, so removing our logo would quietly damage
    //* settings this module has no business touching.
    //*
    //* Only the key is removed, never the [branding] section: the section holds
    //* the colours, the credit toggles and the news-feed stash, and Remove means
    //* "remove this logo", not "reset the branding". Dropping the section is
    //* bin/purge_branding.php's job, on uninstall.
    //*
    //* logo_url_on_dark is deliberately left alone too. It is a form field, so it
    //* is cleared by blanking it and pressing Save; Remove has always been the
    //* control for the UPLOADED logo alone, and it would be a poor surprise for
    //* it to also discard a path the operator typed.
    $app->uses('ini_parser');
    $raw    = $app->db->queryOneRecord("SELECT config FROM sys_ini WHERE sysini_id = 1");
    $config = $app->ini_parser->parse_ini_string(isset($raw['config']) ? (string)$raw['config'] : '');
    if(!is_array($config)) $config = array();
    if(isset($config['branding']['logo_on_dark'])) {
        unset($config['branding']['logo_on_dark']);
        // Check the return, exactly as the upload path does. db::query() returns
        // false both on a failed query AND on a securityScan refusal, so a
        // silent ignore here reports "removed" while the logo is still stored —
        // and the operator only finds out by reloading a page that still shows
        // it. Deleting a brand asset is precisely where a false success is
        // worst: it looks like the white-label worked when it did not.
        $ok = $app->db->query("UPDATE sys_ini SET config = ? WHERE sysini_id = 1", $app->ini_parser->get_ini_string($config));
        if(!$ok) $app->error($app->lng('logo_delete_failed_txt'));
    }
} else {
    $ok = $app->db->query("UPDATE sys_ini SET custom_logo = '' WHERE sysini_id = 1");
    if(!$ok) $app->error($app->lng('logo_delete_failed_txt'));
}

header('Content-Type: text/plain; charset=utf-8');
echo "HEADER_REDIRECT:customizer/customizer_edit.php?id=1";
