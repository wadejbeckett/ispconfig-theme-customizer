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
 * The success #OKMsg also embeds the new thumbnail, because the uploader
 * driver only re-applies #OKMsg/#errorMsg — never the #used_logo element.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

//* admin-only
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* A POST body larger than post_max_size arrives with $_POST and $_FILES both
//* empty (only Content-Length survives). The CSRF token then can't be present,
//* so csrf_token_check() would die with a misleading "CSRF blocked" message —
//* detect the overflow first and report it as an oversize file instead.
$post_overflow = ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && empty($_FILES)
    && isset($_SERVER['CONTENT_LENGTH']) && (int)$_SERVER['CONTENT_LENGTH'] > 0);

//* reject forged cross-site posts (halts on failure)
if(!$post_overflow) {
    $app->auth->csrf_token_check('POST');
}

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
    'image/png'  => true,
    'image/jpeg' => true,
    'image/gif'  => true,
    'image/webp' => true,
    // NOTE: SVG is intentionally NOT accepted. Core login/index.php feeds
    // custom_logo to getimagesizefromstring(), which returns false for SVG and
    // then warns + mis-sizes the stock-theme logo on every login render.
);

$data_uri  = null; // set to the stored value on a successful upload
$upload_ok = false;

if($post_overflow) {
    $error[] = $app->lng('logo_too_large_txt');
} else {
    //* map PHP's own upload error before touching the file
    $err = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;

    if($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $error[] = $app->lng('logo_too_large_txt');
    } elseif($err === UPLOAD_ERR_NO_FILE || !isset($_FILES['file']['name']) || $_FILES['file']['name'] === '') {
        $error[] = $app->lng('no_file_uploaded_error');
    } elseif($err !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES['file']['tmp_name'])) {
        $error[] = $app->lng('upload_failed_txt');
    } else {
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

        if(!isset($allowed[$mime])) {
            $error[] = $app->lng('logo_bad_type_txt');
        } elseif($size <= 0 || $size > $max_raw) {
            $error[] = $app->lng('logo_too_large_txt');
        } elseif($conf['demo_mode'] == true) {
            $error[] = $app->lng('demo_mode_txt');
        } else {
            $data_uri = 'data:' . $mime . ';base64,' . base64_encode($data);
            //* direct UPDATE (not datalogUpdate) — a 48 KB blob has no place in sys_datalog
            $app->db->query("UPDATE sys_ini SET custom_logo = ? WHERE sysini_id = 1", $data_uri);
            $upload_ok = true;
        }
    }
}

//* Preview: on success use the value we just wrote (no redundant re-read);
//* otherwise reflect whatever is currently stored.
if($data_uri !== null) {
    $current_logo = $data_uri;
} else {
    $row = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
    $current_logo = (is_array($row) && isset($row['custom_logo'])) ? (string)$row['custom_logo'] : '';
}
$preview = customizer_logo_preview_html($current_logo, $app->lng('no_logo_set_txt'));
$app->tpl->setVar('used_logo', $preview);

//* The iframe driver re-applies only #OKMsg/#errorMsg, so put the confirmation
//* thumbnail inside the success message — that's what the admin actually sees.
if($upload_ok) {
    $msg[] = $app->lng('logo_uploaded_txt') . '<br />' . $preview;
}

//* upload_msg/upload_error, NOT msg/error: the content template keys its
//* #OKMsg/#errorMsg blocks on these so the tabbed_form wrapper (which renders
//* msg/error itself) can't double-display banners on the interactive page
$app->tpl->setVar('upload_msg',   count($msg)   > 0 ? implode('<br />', $msg)   : '');
$app->tpl->setVar('upload_error', count($error) > 0 ? implode('<br />', $error) : '');

//* mint a fresh token for the next upload/save (submitUploadForm scrapes these)
$csrf = $app->auth->csrf_token_get('customizer');
$app->tpl->setVar('_csrf_id',  $csrf['csrf_id']);
$app->tpl->setVar('_csrf_key', $csrf['csrf_key']);

$app->tpl_defaults();
$app->tpl->pparse();
