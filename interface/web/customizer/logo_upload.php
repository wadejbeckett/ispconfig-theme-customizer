<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-theme-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * Brand-image upload target for the editor's own fetch() uploader (the button
 * handlers in templates/customizer_edit.htm). Validates MIME + size, writes a
 * data-URI into the slot the request names, and re-renders form.tpl.htm so the
 * response body carries #OKMsg/#errorMsg and refreshed #used_logo /
 * #used_logo_on_dark / #used_favicon previews for the caller to lift out with
 * DOMParser.
 *
 * ALL THREE slots come through here — both logo variants and the favicon — and
 * the screening is shared, not duplicated: one CSRF flow, one MIME sniff, one
 * SVG guard, one storage discipline. What the slot changes is the accepted
 * formats, the size cap and where the accepted bytes are stored; everything
 * else is common code on purpose, because a second upload endpoint would mean a
 * second (and eventually weaker) copy of the checks. See lib/preview.inc.php
 * for the slot model and what each store costs.
 *
 * ISPConfig's stock iframe uploader (ispconfig.js submitUploadForm) is
 * deliberately NOT used here — see the template for why — so this endpoint
 * neither expects nor returns a replacement CSRF pair. The token that
 * authorises the POST is minted by the GET branch below, at click time.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

//* admin-only
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* Token minting for the fetch()-based uploader. ISPConfig's DB session store
//* has no locking (read = SELECT, write = REPLACE, last writer wins), so a
//* single-use CSRF token minted during the page render can be silently erased
//* by any concurrent session-writing request in the page-load burst (capp.php,
//* keepalive, login boot). The template therefore requests a FRESH pair here,
//* alone and immediately before the upload POST, shrinking the race window
//* from minutes to milliseconds. Same-origin JS only: the X-Requested-With
//* header cannot be attached cross-origin without a CORS preflight, and the
//* response is unreadable cross-origin anyway.
if($_SERVER['REQUEST_METHOD'] === 'GET') {
    if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
        die('Bad request.');
    }
    $csrf = $app->auth->csrf_token_get('customizer');
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode(array('csrf_id' => $csrf['csrf_id'], 'csrf_key' => $csrf['csrf_key']));
    exit;
}

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

//* WHICH SLOT this upload targets — validated against the shared allowlist and
//* never used raw. The value picks a STORAGE LOCATION (core's sys_ini.custom_logo
//* column, or one of the [branding] keys inside sys_ini.config), so an
//* unchecked string here would be a request choosing what it gets to overwrite.
//*
//* Absent means on_light, which is what this endpoint did before a second slot
//* existed: a replayed or cached request from an older page keeps working and
//* keeps meaning the same thing.
//* Present but unknown is REFUSED rather than quietly defaulted. Defaulting
//* would silently replace the operator's other logo — a destructive surprise
//* whose cause is invisible, since the banner would happily read "Logo updated."
$slots = customizer_logo_slots();
$slot  = isset($_POST['slot']) ? (string)$_POST['slot'] : 'on_light';
if(!isset($slots[$slot])) die('Unknown logo slot.');

//* Screening is per SLOT, because a favicon is a different kind of asset from a
//* logo — not merely a smaller one.
//*
//* FORMATS. A favicon is painted into a 16px box by the browser's image
//* pipeline, so JPEG (no transparency, artefacts at that size) and GIF/WebP
//* (nothing a PNG does not do here) buy nothing and would only widen what
//* favicon.php has to be willing to re-serve. SVG is the point of the feature —
//* one file, every density — and .ico is the compatibility floor: not every
//* browser takes an SVG icon, and plenty of operators have exactly one .ico and
//* nothing else. Those three, and no more.
//*
//* SIZE. The logo cap is 45 KB because a wordmark is a wide piece of artwork.
//* A favicon is not: a 32x32 PNG is ~1 KB, a tidy SVG mark 1-3 KB, and a
//* multi-resolution .ico (16/32/48, the shape every generator emits) lands
//* around 5-15 KB. 15 KB therefore fits every legitimate icon with room to
//* spare while keeping ~20 KB of base64 — not ~62 KB — as the worst case this
//* adds to the sys_ini.config blob, which is re-read on page loads and
//* journalled by the next form save. Reusing 45 KB here would have been a
//* number copied rather than chosen.
if($slot === 'favicon') {
    $max_raw = 15000;
    $allowed = array(
        'image/png'                  => true,
        'image/x-icon'               => true,
        'image/vnd.microsoft.icon'   => true,
    );
} else {
    $max_raw = 45000; // bytes of raw image; base64 (~x1.37) must fit the ~64 KB TEXT column
    $allowed = array(
        'image/png'  => true,
        'image/jpeg' => true,
        'image/gif'  => true,
        'image/webp' => true,
    );
}
// image/svg+xml is accepted via customizer_svg_ok() below, not the maps above:
// finfo mislabels prolog-less SVGs (text/xml, text/plain, even text/html), so
// SVG detection is by strict XML validation instead of MIME string. That guard
// screens the favicon exactly as it screens a logo — an SVG icon is the same
// active-content format with the same affordances, and the 41-case corpus in
// tests/svg/ covers this path because it is literally the same call.
require_once __DIR__ . '/lib/svg_guard.inc.php';

//* Which wording a rejection gets. The favicon's limits differ from the logo's,
//* so a shared "keep it under 45 KB" message would send the operator hunting
//* for a problem that is not theirs.
$bad_type_txt  = ($slot === 'favicon') ? 'favicon_bad_type_txt'  : 'logo_bad_type_txt';
$too_large_txt = ($slot === 'favicon') ? 'favicon_too_large_txt' : 'logo_too_large_txt';

$data_uri  = null; // set to the stored value on a successful upload
$upload_ok = false;

if($post_overflow) {
    //* $slot is 'on_light' here by construction — the whole POST was discarded,
    //* so the slot field never arrived — which is why this one message is not
    //* slot-aware. It cannot be: nothing in the request survived to say which
    //* upload it was.
    $error[] = $app->lng($too_large_txt);
} else {
    //* map PHP's own upload error before touching the file
    $err = isset($_FILES['file']['error']) ? $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;

    if($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        $error[] = $app->lng($too_large_txt);
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

        //* SVG lane: finfo's label is unreliable for SVG, so any XML-ish/texty
        //* verdict gets one chance to prove itself against the strict validator
        if(!isset($allowed[$mime])
            && in_array($mime, array('image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'), true)
            && customizer_svg_ok($data)) {
            $mime = 'image/svg+xml';
            $allowed[$mime] = true;
        }

        //* ICO lane, same shape as the SVG one and for the same reason: finfo's
        //* verdict is build-dependent. libmagic has said image/x-icon,
        //* image/vnd.microsoft.icon and — where the magic file is old or the
        //* distro ships a stripped one — application/octet-stream for the very
        //* same file. So an otherwise-unclassified upload into the favicon slot
        //* gets one chance to prove it really is an icon, by structure rather
        //* than by label (see customizer_ico_ok()). Nothing weaker is happening
        //* here than for the other raster formats: ICO carries no scripting
        //* affordance, so identifying the container IS the whole check, unlike
        //* SVG where the format itself is the risk.
        if($slot === 'favicon' && !isset($allowed[$mime])
            && in_array($mime, array('', 'application/octet-stream', 'application/x-ico', 'image/ico'), true)
            && customizer_ico_ok($data)) {
            $mime = 'image/x-icon';
            $allowed[$mime] = true;
        }

        //* One spelling reaches storage. The two ICO media types are the same
        //* format, and favicon.php picks the response Content-Type straight out
        //* of the stored prefix — so normalising here means one canonical value
        //* in sys_ini and one fewer case for every reader.
        if($mime === 'image/vnd.microsoft.icon') $mime = 'image/x-icon';

        if(!isset($allowed[$mime])) {
            $error[] = $app->lng($bad_type_txt);
        } elseif($size <= 0 || $size > $max_raw) {
            $error[] = $app->lng($too_large_txt);
        } elseif($conf['demo_mode'] == true) {
            $error[] = $app->lng('demo_mode_txt');
        } else {
            $candidate = 'data:' . $mime . ';base64,' . base64_encode($data);

            //* Every write here is a direct UPDATE, never datalogUpdate — a
            //* ~60 KB base64 blob has no place in sys_datalog. (Saving the
            //* Branding FORM later does journal the config blob, and with it
            //* whatever on_dark logo and favicon are stored there; that
            //* unavoidable cost is spelled out in lib/preview.inc.php.)
            //*
            //* Where this slot's bytes live: on_light is core's own
            //* sys_ini.custom_logo column; the other two are config keys, and
            //* they share the one code path below.
            $config_key = customizer_slot_config_key($slot);

            if($config_key !== '') {
                //* No core column exists for these (the dark-background logo and
                //* the favicon) and we may not add one, so each goes into
                //* sys_ini.config as [branding] <key>.
                //*
                //* Read-modify-write, with the RAW column parsed and NO
                //* stripslashes — identical discipline to customizer_edit.php's
                //* onUpdateSave and bin/purge_branding.php, and for the same
                //* reason: getconf::get_global_config() unescapes on read while
                //* nothing re-escapes on write, so a round trip through it would
                //* silently eat one backslash level from EVERY value in the file
                //* on every upload, [mail] smtp_pass included. This module must
                //* not be able to alter settings it does not own.
                //*
                //* The value itself is immune to that asymmetry either way: the
                //* base64 alphabet and the "data:image/…;base64," prefix contain
                //* no backslash, so the stripslashes every reader applies is a
                //* no-op on it.
                //*
                //* Blast radius, stated plainly: this rewrites the whole config
                //* blob through ini_parser, so anything that parser does not
                //* model (comments, keys outside a section) is dropped — the
                //* same round trip the Save button and the purge script already
                //* perform, now also on upload.
                $app->uses('ini_parser');
                $raw    = $app->db->queryOneRecord("SELECT config FROM sys_ini WHERE sysini_id = 1");
                $config = $app->ini_parser->parse_ini_string(isset($raw['config']) ? (string)$raw['config'] : '');
                if(!is_array($config)) $config = array();
                if(!isset($config['branding']) || !is_array($config['branding'])) $config['branding'] = array();
                $config['branding'][$config_key] = $candidate;
                $written = $app->db->query("UPDATE sys_ini SET config = ? WHERE sysini_id = 1", $app->ini_parser->get_ini_string($config));
            } else {
                $written = $app->db->query("UPDATE sys_ini SET custom_logo = ? WHERE sysini_id = 1", $candidate);
            }

            //* Check the write. db::query() returns false both on a failed query
            //* and on a securityScan refusal, and reporting success over a value
            //* that was never stored is the worst outcome available here: the
            //* operator sees "Logo updated.", the panel keeps showing the old
            //* mark, and nothing anywhere says why. An InnoDB lock-wait timeout
            //* on sys_ini row 1 is not hypothetical — an admin saving System >
            //* Interface Config writes the same row.
            if($written === false) {
                $error[] = $app->lng('upload_failed_txt');
            } else {
                $data_uri  = $candidate;
                $upload_ok = true;
            }
        }
    }
}

//* Preview: re-read the stored values so EVERY row is correct. Refreshing only
//* the row that was just written would be wrong — the two logo rows fall back to
//* each other, so uploading into an empty panel's dark slot also changes what
//* the LIGHT row displays (from "nothing set" to "borrowing the dark mark"). The
//* favicon row stands alone, but it is rendered on every response anyway so the
//* caller never has to know which rows a given upload could have changed. On
//* success the value we just wrote is substituted in rather than re-read.
$row = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
$app->uses('getconf');
$branding = $app->getconf->get_global_config('branding');
if(!is_array($branding)) $branding = array();

$stored = array(
    'custom_logo'      => (is_array($row) && isset($row['custom_logo'])) ? (string)$row['custom_logo'] : '',
    'logo_on_dark'     => isset($branding['logo_on_dark']) ? $branding['logo_on_dark'] : '',
    'logo_url'         => isset($branding['logo_url']) ? $branding['logo_url'] : '',
    'logo_url_on_dark' => isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    'favicon'          => isset($branding['favicon']) ? $branding['favicon'] : '',
    'favicon_url'      => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
);
if($data_uri !== null) {
    //* Substitute the value we know we just stored rather than trusting the
    //* read above. getconf caches the config blob for the whole request on its
    //* FIRST call, so whether its snapshot predates our config-key UPDATE
    //* depends on whether anything earlier in the request already asked for a
    //* global setting — a detail no display path should have to reason about.
    //* The substitution makes the answer irrelevant.
    $written_key = customizer_slot_config_key($slot);
    $stored[($written_key !== '') ? $written_key : 'custom_logo'] = $data_uri;
}

$resolved      = customizer_logo_resolve($stored);
$no_logo_txt   = $app->lng('no_logo_set_txt');

//* Same surface list the settings page builds, over the same installed designs,
//* so an upload redraws the previews exactly as a full page load would — this
//* endpoint replaces those two nodes in place and any disagreement between the
//* two renderers would show up as a swatch that changes colour on upload. That
//* is also why this must not stay on the ACTIVE design alone while the settings
//* page describes them all: the mismatch would appear the moment anything was
//* uploaded. $branding is the blob read above; the operator's colour choices are
//* already in it, and the two logo_variant_* keys with them. There is no POST to
//* overlay here — this endpoint saves an image, not the variant selects.
$surfaces      = customizer_logo_surfaces_all(
    customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : ''),
    $branding,
    array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt'))
);
$preview_light = customizer_logo_preview_html($resolved['on_light'], 'on_light', $no_logo_txt, $app->lng('logo_fallback_from_dark_txt'), $surfaces);
$preview_dark  = customizer_logo_preview_html($resolved['on_dark'],  'on_dark',  $no_logo_txt, $app->lng('logo_fallback_from_light_txt'), $surfaces);
$app->tpl->setVar('used_logo', $preview_light);
$app->tpl->setVar('used_logo_on_dark', $preview_dark);

//* The favicon preview is refreshed on EVERY upload, not only a favicon one.
//* It costs one more render and it keeps the response body a complete, current
//* picture of the page — the caller lifts whichever previews it finds, and a
//* stale row it did not think to refresh is exactly how a preview starts lying.
$preview_favicon = customizer_favicon_preview_html(
    customizer_favicon_resolve($stored),
    $app->lng('no_favicon_set_txt'),
    $app->lng('favicon_url_wins_txt')
);
$app->tpl->setVar('used_favicon', $preview_favicon);

//* The caller relocates the banner into the message slot at the top of the
//* editor while the previews stay down in the form, so the confirmation embeds
//* the new thumbnail too — otherwise the admin has to scroll to see the result.
//* It embeds the row that was just uploaded into, drawn on that row's own
//* background, so the banner itself shows whether the artwork suits the slot.
if($upload_ok) {
    if($slot === 'favicon') {
        //* The favicon banner carries the true-size preview for the same reason
        //* the logo one carries a thumbnail — except here the preview is the
        //* actual test. "Favicon updated." over a 16px render is the moment an
        //* operator finds out their wordmark is unreadable in a tab.
        $msg[] = $app->lng('favicon_uploaded_txt') . '<br />' . $preview_favicon;
    } else {
        $msg[] = $app->lng('logo_uploaded_txt') . '<br />' . (($slot === 'on_dark') ? $preview_dark : $preview_light);
    }
}

//* upload_msg/upload_error, NOT msg/error: the content template keys its
//* #OKMsg/#errorMsg blocks on these so the tabbed_form wrapper (which renders
//* msg/error itself) can't double-display banners on the interactive page
$app->tpl->setVar('upload_msg',   count($msg)   > 0 ? implode('<br />', $msg)   : '');
$app->tpl->setVar('upload_error', count($error) > 0 ? implode('<br />', $error) : '');

//* No CSRF pair is minted for the response. form.tpl.htm carries hidden
//* _csrf_id/_csrf_key inputs, but nothing consumes them here: the caller reads
//* only #OKMsg/#errorMsg/#used_logo out of this body, and the editor page's own
//* token — the one Save and Remove submit — is untouched by this request.
//* Minting one anyway used to leave a dead entry in $_SESSION['_csrf'] after
//* every upload, lingering until csrf_token_check()'s hourly expiry sweep.
//* Unset template vars render empty, which is correct for a fragment nobody
//* submits.

$app->tpl_defaults();
$app->tpl->pparse();
