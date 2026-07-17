<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * Logo upload target for the iframe uploader (ispconfig.js submitUploadForm).
 * Validates MIME + size, writes a data-URI into sys_ini.custom_logo, and
 * re-renders form.tpl.htm so the response carries #OKMsg/#errorMsg plus a
 * fresh CSRF token pair (which submitUploadForm scrapes back into the page).
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';

//* admin-only
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* reject forged cross-site posts (halts on failure)
$app->auth->csrf_token_check('POST');

$app->uses('tpl');
$app->tpl->newTemplate("form.tpl.htm");
$app->tpl->setInclude('content_tpl', 'templates/customizer_edit.htm');

//* sanitize the session language and fall back to English if that file isn't shipped
$lng = $app->functions->check_language($_SESSION['s']['language']);
$lng_path = '/web/customizer/lib/lang/' . $lng . '_customizer.lng';
if(!file_exists(ISPC_ROOT_PATH . $lng_path)) $lng_path = '/web/customizer/lib/lang/en_customizer.lng';
$app->load_language_file($lng_path);

$msg   = array();
$error = array();

$max_raw = 45000; // bytes of raw image; base64 (~x1.37) must fit the ~64 KB TEXT column
$allowed = array(
    'image/png'     => true,
    'image/jpeg'    => true,
    'image/gif'     => true,
    'image/webp'    => true,
    'image/svg+xml' => true,
);

if(isset($_FILES['file']['name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $data = file_get_contents($_FILES['file']['tmp_name']);
    $size = strlen($data);

    $mime = '';
    if(function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if($finfo) {
            $mime = (string)finfo_file($finfo, $_FILES['file']['tmp_name']);
            finfo_close($finfo);
        }
    }
    //* SVG is often sniffed as text/xml or plain text — confirm by content
    if(($mime === 'text/plain' || $mime === 'text/xml' || $mime === 'application/xml' || $mime === '')
        && preg_match('/<svg[\s>]/i', substr($data, 0, 1024))) {
        $mime = 'image/svg+xml';
    }

    if(!isset($allowed[$mime])) {
        $error[] = $app->lng('logo_bad_type_txt');
    } elseif($size <= 0 || $size > $max_raw) {
        $error[] = $app->lng('logo_too_large_txt');
    } else {
        $data_uri = 'data:' . $mime . ';base64,' . base64_encode($data);
        //* direct UPDATE (not datalogUpdate) — a 48 KB blob has no place in sys_datalog
        if($conf['demo_mode'] != true) {
            $app->db->query("UPDATE sys_ini SET custom_logo = ? WHERE sysini_id = 1", $data_uri);
            $msg[] = $app->lng('logo_uploaded_txt');
        }
    }
} elseif(isset($_FILES['file']['name'])) {
    $error[] = $app->lng('no_file_uploaded_error');
} else {
    $error[] = $app->lng('no_file_uploaded_error');
}

//* refreshed preview — only render a value that is a real image data-URI
$sys_ini = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
$logo_val = (is_array($sys_ini) && isset($sys_ini['custom_logo'])) ? (string)$sys_ini['custom_logo'] : '';
if($logo_val !== '' && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $logo_val)) {
    $app->tpl->setVar('used_logo', '<img src="' . $logo_val . '" alt="" style="max-height:48px;max-width:220px;background:#01243D;padding:6px 12px;border-radius:4px" />');
} else {
    $app->tpl->setVar('used_logo', '<em>' . $app->lng('no_logo_set_txt') . '</em>');
}

$app->tpl->setVar('msg',   count($msg)   > 0 ? implode('<br />', $msg)   : '');
$app->tpl->setVar('error', count($error) > 0 ? implode('<br />', $error) : '');

//* mint a fresh token for the next upload/save (submitUploadForm scrapes these)
$csrf = $app->auth->csrf_token_get('customizer');
$app->tpl->setVar('_csrf_id',  $csrf['csrf_id']);
$app->tpl->setVar('_csrf_key', $csrf['csrf_key']);

$app->tpl_defaults();
$app->tpl->pparse();
