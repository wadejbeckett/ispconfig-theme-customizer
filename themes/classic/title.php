<?php
/**
 * classic — branded document.title (companion to brand.php).
 * Copyright (c) 2026 Wade Beckett. MIT License — see the repo LICENSE.
 *
 * Why this exists: core composes the tab title as "company_name :: app_title"
 * — except the OTP page, which never receives company_name at all, so a
 * branded panel leaked a bare "ISPConfig" tab there. The template engine has
 * no string ops and phpinclude is disabled in core, so the design resolves the
 * title itself: this endpoint reads the panel name straight from sys_ini and
 * emits one line of JS. Linked from BOTH shell templates, it makes every page
 * (frame, login, OTP, password reset, forced change) show the panel name when
 * one is set, and the stock product title when not.
 *
 * NOT a verbatim copy of themes/clarity/title.php, and the difference is worth
 * knowing. The read half — query, stripslashes, control-char strip, trim,
 * json_encode guard — is identical character for character and must stay that
 * way. Clarity's SECOND half is not carried over: it arms an error handler on
 * its own wordmark <img> elements and swaps in a styled .nz-wordmark-text span
 * when one fails to load. On classic that machinery would be dead code.
 *   - The app frame has no wordmark <img> at all. Core renders the logo as a
 *     background-image on #logo (index.php:100-109 writes it into the style
 *     attribute), and a background that fails to load fires no error event and
 *     shows no broken-image icon — there is nothing to catch and nothing to
 *     repair.
 *   - The login shell does have an <img>, but its src is the data URI core
 *     inlined, which cannot 404. When brand.php re-points it via `content:
 *     url(…)` the element's own src has still loaded successfully, so a failing
 *     override fires no error there either.
 * What IS worth carrying over is the alt text: core emits that <img> with no
 * alt attribute at all, so assistive tech announces nothing and a broken image
 * shows an empty box on the one page every customer sees.
 *
 * Same design constraints as brand.php: pre-auth safe (no app bootstrap, no
 * session), a single read-only query, always HTTP 200 with valid JS, and the
 * value reaches the output only through json_encode (script-context safe).
 *
 * company_name is normalised identically here and in brand.php (stripslashes
 * on the blob, control-char strip, trim) so the tab title and the CSS wordmark
 * — which co-render on the login screen — never disagree about the panel's own
 * name. Change one, change the other.
 */

$company = '';
$read_ok = false;

$config_inc = __DIR__ . '/../../../lib/config.inc.php'; // interface/lib/config.inc.php
if (is_readable($config_inc)) {
    require $config_inc;
    if (isset($conf) && is_array($conf) && !empty($conf['db_host'])) {
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        try {
            $port   = isset($conf['db_port']) ? (int)$conf['db_port'] : 3306;
            $mysqli = @new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database'], $port);
            if ($mysqli && !$mysqli->connect_errno) {
                @$mysqli->set_charset('utf8mb4');
                if ($res = @$mysqli->query('SELECT config FROM sys_ini WHERE sysini_id = 1')) {
                    $read_ok = true;
                    if ($row = $res->fetch_assoc()) {
                        $ini_parser_inc = __DIR__ . '/../../../lib/classes/ini_parser.inc.php';
                        if (is_readable($ini_parser_inc)) {
                            require_once $ini_parser_inc;
                            $parser = new ini_parser();
                            // stripslashes before parsing is mandatory for sys_ini.config,
                            // not a nicety: the write path escapes the field on its way in
                            // (tform_base::_encode() runs db->quote() over every value
                            // before the blob is serialised), and ini_parser is a plain
                            // line splitter that unescapes nothing. So core ALWAYS unquotes
                            // on read — app.inc.php:108, getconf.inc.php:54,
                            // server_config_edit.php:190 — and so does brand.php's
                            // brand_parse_config(). Parsing the raw blob here painted
                            // O\'Brien Hosting in the tab (and in every <img alt>) while
                            // the CSS wordmark from brand.php read O'Brien Hosting: two
                            // brand surfaces on the same pre-auth login page disagreeing
                            // about the customer's own name.
                            $parsed = $parser->parse_ini_string(stripslashes((string)$row['config']));
                            if (isset($parsed['misc']['company_name']) && is_string($parsed['misc']['company_name'])) {
                                // Same normalisation as brand.php, character for
                                // character, so both endpoints derive an identical
                                // string from an identical row. Control characters go
                                // because brand.php cannot represent them (a raw CR/LF
                                // would terminate its CSS string); everything printable
                                // stays and is escaped per output context — json_encode
                                // here, a CSS-string escape there. Byte-wise and without
                                // /u on purpose: with /u one malformed UTF-8 byte makes
                                // preg_replace return NULL and the brand vanishes, while
                                // 0x00-0x1F and 0x7F never occur inside a valid UTF-8
                                // sequence, so a byte filter cannot split a codepoint.
                                $company = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $parsed['misc']['company_name']));
                            }
                        }
                    }
                }
                @$mysqli->close();
            }
        } catch (\Throwable $e) {
            $company = '';
            $read_ok = false;
        }
    }
}

// Re-assert the MIME: config.inc.php sends text/html on web requests.
header('Content-Type: application/javascript; charset=utf-8');
if ($read_ok) {
    // Short private cache — a renamed panel updates within 30s / on hard refresh.
    header('Cache-Control: private, max-age=30');
} else {
    // DB fault: emit a no-op and don't let caches pin the failure.
    header('Cache-Control: no-store');
}

$name_js = ($company !== '')
    ? json_encode($company, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)
    : false;

// json_encode() returns FALSE on invalid UTF-8, and false concatenates as ''.
// Emitting it would produce `document.title=;` — a PARSE error, and a classic
// script that fails to parse runs NOTHING, so even the statements before it are
// lost. This endpoint is pre-auth and unconditional, so that would blank the
// brand on every page of the panel including login. Treat unencodable input
// exactly like "no panel name set": emit the documented no-op and let the panel
// fall back to its stock title.
if ($name_js !== false) {
    echo 'document.title=' . $name_js . ';' . "\n";
    // Name the login logo. Core renders it as <img src="{base64_logo_txt}"> with
    // no alt attribute (main_login.tpl.htm), so screen readers announce nothing
    // and a logo that fails to render leaves an empty box on the one page every
    // customer sees. The name is uncapped on purpose — brand.php caps its
    // VISIBLE wordmark at 40 characters to fit a nowrap slot, but an accessible
    // name should be the real brand, not one trimmed to fit a layout.
    //
    // #main-wrapper exists only in the app frame (main.tpl.htm), never in the
    // login shell, so its absence is what identifies this page as a login-family
    // screen. The guard matters because .panel-heading is a plain Bootstrap
    // class: no ISPConfig 3.3.1p1 page puts an <img> in one, but a third-party
    // module could, and renaming someone else's image after the panel is a bug
    // that would be very hard to trace back to here.
    // The #main-wrapper test MUST run inside arm(), not here. install.sh inserts
    // this script immediately before </head>, and a classic non-deferred script
    // in <head> executes while the head is still parsing — before <body> exists.
    // Tested at the IIFE level the element is therefore null on EVERY page,
    // including the app frame, so the guard would never fire and the alt-text
    // rename would run on login and app alike. By the time arm() runs (either
    // DOMContentLoaded or a readyState past 'loading') the body is parsed and
    // the test means what it says.
    echo '(function(){'
       . 'var n=' . $name_js . ';'
       . 'function arm(){'
       . 'if(document.getElementById("main-wrapper"))return;'
       . 'document.querySelectorAll(".panel-heading img").forEach(function(img){img.alt=n;});}'
       . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",arm);}else{arm();}'
       . '})();';
} else {
    // No panel name set, or a stored name that is not encodable as JSON.
    // Either way this endpoint is a documented no-op: valid JavaScript that
    // does nothing, so the panel keeps its shipped title.
    echo '/* no panel name set — stock title kept */';
}
