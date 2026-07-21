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
                            $parsed = $parser->parse_ini_string((string)$row['config']);
                            if (isset($parsed['misc']['company_name']) && is_string($parsed['misc']['company_name'])) {
                                $company = trim($parsed['misc']['company_name']);
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

if ($company !== '') {
    $name_js = json_encode($company, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    echo 'document.title=' . $name_js . ';' . "\n";
    // Brand-slot failover: the wordmark <img> elements get the panel name as
    // alt text (assistive tech + broken-image state say the BRAND, never the
    // product), and if the slot's own src fails to load it is replaced by a
    // styled text wordmark (.nz-wordmark-text, themed in app.css/login.css).
    echo '(function(){var n=' . $name_js . ';'
       . 'function arm(){document.querySelectorAll("#logo img,.nz-topbar-brand img,.nzl-brand img").forEach(function(img){'
       . 'img.alt=n;'
       . 'img.addEventListener("error",function(){var s=document.createElement("span");s.className="nz-wordmark-text";s.textContent=n;img.replaceWith(s);});'
       . 'if(img.complete&&img.naturalWidth===0){var s=document.createElement("span");s.className="nz-wordmark-text";s.textContent=n;img.replaceWith(s);}'
       . '});}'
       . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",arm);}else{arm();}'
       . '})();';
} else {
    echo '/* no panel name set — stock title kept */';
}
