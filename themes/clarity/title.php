<?php
/**
 * Clarity Theme for ISPConfig — branded document.title (companion to brand.php).
 * Copyright (c) 2026 Wade Beckett. MIT License — see the repo LICENSE.
 *
 * Why this exists: core composes the tab title as "company_name :: app_title"
 * — except the OTP page, which never receives company_name at all, so a
 * branded panel leaked a bare "ISPConfig" tab there. The template engine has
 * no string ops and phpinclude is disabled in core, so the theme resolves the
 * title itself: this endpoint reads the panel name straight from sys_ini and
 * emits one line of JS. Linked from BOTH shell templates, it makes every page
 * (frame, login, OTP, password reset, forced change) show the panel name when
 * one is set, and the stock product title when not.
 *
 * Same design constraints as brand.php: pre-auth safe (no app bootstrap, no
 * session), a single read-only query, always HTTP 200 with valid JS, and the
 * value reaches the output only through json_encode (script-context safe).
 *
 * company_name is normalised identically here and in brand.php (stripslashes
 * on the blob, control-char strip, trim, and a 40-char cap on the *visible*
 * wordmark) so the tab title and the CSS wordmark — which co-render on the
 * login screen — never disagree about the panel's own name. Change one,
 * change the other.
 */

$company = '';
$read_ok = false;

// Locate interface/lib/config.inc.php WITHOUT trusting __DIR__ alone.
// install.sh's DEFAULT mode is a SYMLINK, and PHP resolves __DIR__ through
// symlinks — so on a symlinked design __DIR__ is the git clone, where
// ../../../lib/config.inc.php does not exist. This endpoint would then fail to
// find the panel, take its DB-failure path, and emit its empty/no-op response:
// branding silently doing nothing on the documented default install, with no
// error anywhere. SCRIPT_FILENAME is the path the web server actually
// requested and is NOT symlink-resolved, so try that first; __DIR__ still
// covers CLI use and copy installs, and DOCUMENT_ROOT is a last resort.
$config_inc = '';
$_cfg_candidates = array();
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $_cfg_candidates[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/../../../lib/config.inc.php';
}
$_cfg_candidates[] = __DIR__ . '/../../../lib/config.inc.php';
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $_cfg_candidates[] = $_SERVER['DOCUMENT_ROOT'] . '/../lib/config.inc.php';
}
foreach ($_cfg_candidates as $_cand) {
    if (is_readable($_cand)) { $config_inc = $_cand; break; }
}
unset($_cfg_candidates, $_cand);
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
                        // Derive this from the config path already resolved above, NOT from a
                        // second __DIR__ walk — on a symlink install that walk lands in
                        // the clone and the parser silently is not found, which would
                        // leave the panel name unread even though the DB read succeeded.
                        $ini_parser_inc = dirname($config_inc) . '/classes/ini_parser.inc.php';
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
// Emitting it would produce `document.title=;` or `var n="x",w=;` — a PARSE
// error, and a classic script that fails to parse runs NOTHING, so even the
// statements before it are lost. This endpoint is pre-auth and unconditional,
// so that would blank the brand on every page of the panel including login.
// Treat unencodable input exactly like "no panel name set": emit the documented
// no-op and let the theme fall back to its shipped wordmark.
if ($name_js !== false) {
    echo 'document.title=' . $name_js . ';' . "\n";
    // The VISIBLE failover wordmark lands in the same nowrap rail/topbar slot as
    // brand.php's `content:` wordmark, so it must truncate at the same point —
    // brand.php caps at 40 (multibyte-safe: a byte substr would cut a UTF-8
    // codepoint in half and corrupt the glyph), and so does this. document.title
    // and the alt text stay uncapped on purpose: a browser tab elides on its own
    // and an accessible name should be the real brand, not a layout-fitted one.
    $wordmark = $company;
    if (function_exists('mb_substr')) {
        if (mb_strlen($wordmark, 'UTF-8') > 40) $wordmark = mb_substr($wordmark, 0, 40, 'UTF-8');
    } elseif (strlen($wordmark) > 40) {
        $wordmark = substr($wordmark, 0, 40);
    }
    $wordmark_js = json_encode($wordmark, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    // The byte-substr fallback above is precisely how invalid UTF-8 reaches this
    // line: with mbstring absent it can cut a multibyte codepoint in half. Rather
    // than emit `w=;` and kill the script, fall back to the uncapped name — it is
    // already known-encodable, and an over-long failover wordmark is a cosmetic
    // overflow, not a dead brand.
    if ($wordmark_js === false) $wordmark_js = $name_js;
    // Brand-slot failover: the wordmark <img> elements get the panel name as
    // alt text (assistive tech + broken-image state say the BRAND, never the
    // product), and if the slot's own src fails to load it is replaced by a
    // styled text wordmark (.nz-wordmark-text, themed in app.css/login.css).
    echo '(function(){var n=' . $name_js . ',w=' . $wordmark_js . ';'
       . 'function swap(img){var s=document.createElement("span");s.className="nz-wordmark-text";s.textContent=w;img.replaceWith(s);}'
       . 'function arm(){document.querySelectorAll("#logo img,.nz-topbar-brand img,.nzl-brand img").forEach(function(img){'
       . 'img.alt=n;'
       . 'img.addEventListener("error",function(){swap(img);});'
       . 'if(img.complete&&img.naturalWidth===0){swap(img);}'
       . '});}'
       . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",arm);}else{arm();}'
       . '})();';
} else {
    // No panel name set, or a stored name that is not encodable as JSON.
    // Either way this endpoint is a documented no-op: valid JavaScript that
    // does nothing, so the theme keeps its shipped title and wordmark.
    echo '/* no panel name set — stock title kept */';
}
