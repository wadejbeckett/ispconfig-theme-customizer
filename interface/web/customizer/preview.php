<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-theme-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * The Branding page's live preview (admin only, READ ONLY).
 *
 * WHY THIS EXISTS. The Branding page previews which logo VARIANT each surface of
 * each installed design will use. That decision lives in three PHP copies —
 * themes/clarity/brand.php, themes/classic/brand.php and lib/preview.inc.php —
 * which tests/brand/run.php proves agree on every input, because the theme
 * endpoints are pre-auth and cannot include the module's code. A JavaScript
 * fourth copy would be outside that guarantee, and a preview that disagrees with
 * the panel is the exact failure the preview exists to prevent. So the page
 * paints colours and rail ink itself, and asks THIS endpoint for anything that
 * depends on variant resolution.
 *
 * WHAT IT DOES. Takes the form's current, unsaved values as POST, overlays the
 * ones that pass their own validation onto the stored [branding], and answers
 * with the rendered preview rows, the surfaces list and the colour blocks —
 * customizer_preview_payload(), which holds every decision and is tested without
 * a database.
 *
 * WHAT IT MUST NEVER DO.
 *   - Write. Not the config blob, not sys_config, not a file. It is called on a
 *     debounce while the operator types; a write path here would be a write per
 *     keystroke, and there is nothing on this page that needs one.
 *   - Mint or check a CSRF token. Minting WRITES the session, and ISPConfig's
 *     session store has no locking (read = SELECT, write = whole-row REPLACE,
 *     last writer wins) — so minting per keystroke would manufacture the exact
 *     race the uploader's click-time mint exists to shrink. There is no state to
 *     protect: nothing is written, and the response is unreadable cross-origin.
 *     The gate is the same X-Requested-With header logo_upload.php's GET branch
 *     uses, which cannot be attached cross-origin without a CORS preflight.
 *   - Decide anything. No function is declared here on purpose; every rule
 *     applied is one of the shared resolvers.
 *
 * POST rather than GET, though it reads nothing: the candidate values are the
 * operator's colours and paths, and a query string lands in access logs and in
 * Referer. The same-origin XHR costs the same either way.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

//* admin-only — the same three checks, in the same order, as customizer_edit.php,
//* logo_upload.php and logo_delete.php. See SECURITY.md for why all three are
//* needed rather than any one of them.
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* Same-origin JS only, and only as a POST. Both gates are cheap and neither is
//* the security boundary — the three checks above are.
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Bad request.');
}
if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    //* 400, not a bare 200: the only caller is the page's own fetch(), and a
    //* refusal that arrives as 200 with a text/html body reaches it as a
    //* JSON.parse error rather than a refusal. The method gate above answers 405
    //* for the same reason. The admin refusal is left as a bare die() on purpose
    //* — byte-identical to the three sibling endpoints, so the auth line stays
    //* one string CI can compare across all four.
    http_response_code(400);
    die('Bad request.');
}

$app->uses('getconf');

//* The two STORED halves the POST cannot carry: core's own logo column, and the
//* [branding] section (which holds the two uploaded images and the favicon).
//* getconf is the right reader HERE and would be wrong in a save path: its
//* stripslashes() has no counterpart on the way back, so a read-modify-write
//* through it eats a backslash level from every value in the file. This request
//* writes nothing, so that asymmetry cannot bite.
$sys_ini  = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
$branding = $app->getconf->get_global_config('branding');
if(!is_array($branding)) $branding = array();

//* $app->lng() reads this module's own wordbook (lib/lang/<lang>.lng), which
//* app.inc.php auto-loads for a request inside the module directory — the same
//* mechanism logo_upload.php relies on for these very keys. They are passed in
//* already localised because lib/preview.inc.php holds no wordbook of its own.
$payload = customizer_preview_payload(
    $branding,
    (is_array($sys_ini) && isset($sys_ini['custom_logo'])) ? $sys_ini['custom_logo'] : '',
    $_POST,
    customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : ''),
    array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt')),
    array(
        'no_logo'             => $app->lng('no_logo_set_txt'),
        'fallback_from_dark'  => $app->lng('logo_fallback_from_dark_txt'),
        'fallback_from_light' => $app->lng('logo_fallback_from_light_txt'),
        'no_favicon'          => $app->lng('no_favicon_set_txt'),
        'favicon_url_wins'    => $app->lng('favicon_url_wins_txt'),
        //* The legend's status facts. They live in the MODULE wordbook rather
        //* than the tform one for the same reason the five above do: this
        //* endpoint has no tform and cannot address that wordbook by key.
        'summary_design'       => $app->lng('summary_design_txt'),
        'summary_marks_none'   => $app->lng('summary_marks_none_txt'),
        'summary_marks_one'    => $app->lng('summary_marks_one_txt'),
        'summary_marks'        => $app->lng('summary_marks_txt'),
        'summary_favicon'      => $app->lng('summary_favicon_txt'),
        'summary_favicon_none' => $app->lng('summary_favicon_none_txt'),
    )
);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
//* The four HEX flags for the same reason themes/*/title.php uses them: the
//* result is handed to a page, and a value that cannot carry <, &, ' or " cannot
//* change the meaning of whatever it lands in.
$json = json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
if ($json === false) {
    //* A payload that cannot be encoded (e.g. malformed UTF-8 from an upstream
    //* value) must not fall through to echoing `false`, which prints nothing and
    //* still answers 200 — the caller's JSON.parse() would then fail on an empty
    //* body with no indication anything went wrong server-side.
    http_response_code(500);
    die('{}');
}
echo $json;
