<?php
/* ============================================================
 * Clarity Theme for ISPConfig — brand.php  (the brand READER)
 * ------------------------------------------------------------
 * Emits a small stylesheet that realizes the neutral, theme-
 * agnostic "brand-token contract" as Clarity --nz-* overrides.
 * Any customizer (e.g. ispconfig-customizer) WRITES the contract
 * into ISPConfig's sys_ini table; this file READS it. The two
 * are decoupled — they share only the DB keys documented in the
 * README ("Brand-token contract"), never code.
 *
 * Contract consumed (sys_ini, global row sysini_id = 1):
 *   custom_logo  column           -> logo (data URI), via CSS content:
 *   config [branding] logo_url    -> logo by reference (root-relative path or
 *                                    https URL); wins over custom_logo
 *   config [branding] accent_hex  -> re-hues the blue ramp + accents
 *   config [branding] rail_hex    -> the navy brand rail
 *   config [branding] login_bg    -> login-screen background base
 *   config [branding] show_ispconfig_credit (0/1) -> footer courtesy line
 *   config [branding] show_theme_credit     (0/1) -> footer courtesy line
 *
 * Design constraints:
 *   - Pre-auth safe: the login screen links this file, so it must
 *     work with no session. It does a single, side-effect-free,
 *     read-only query of one sys_ini row (no ISPConfig app bootstrap,
 *     no maintenance-mode redirects, no session start).
 *   - Always HTTP 200 with valid CSS. When nothing is set it emits an
 *     empty sheet and the theme falls back to its shipped tokens/logo —
 *     so it is a no-op both without the customizer and before first use.
 *   - Injection-safe: every value is validated (hex regex / data-URI
 *     regex / 0|1) before it reaches the output.
 * ============================================================ */

header('Content-Type: text/css; charset=utf-8');

/* ---- read the contract (direct, minimal, side-effect-free) ---- */
$branding    = array();
$custom_logo = '';
$read_ok     = false; // true only when the sys_ini read actually succeeded

$config_inc = __DIR__ . '/../../../lib/config.inc.php'; // interface/lib/config.inc.php
if (is_readable($config_inc)) {
    // config.inc.php only defines $conf + constants — but on a web request it also
    // emits `Content-Type: text/html`, which we re-assert away from below.
    require $config_inc;
    if (isset($conf) && is_array($conf) && !empty($conf['db_host'])) {
        // PHP 8.1+ makes mysqli throw by default; keep the graceful errno idiom working.
        if (function_exists('mysqli_report')) {
            mysqli_report(MYSQLI_REPORT_OFF);
        }
        try {
            $port   = isset($conf['db_port']) ? (int)$conf['db_port'] : 3306;
            $mysqli = @new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database'], $port);
            if ($mysqli && !$mysqli->connect_errno) {
                @$mysqli->set_charset('utf8mb4');
                if ($res = @$mysqli->query('SELECT config, custom_logo FROM sys_ini WHERE sysini_id = 1')) {
                    $read_ok = true;
                    if ($row = $res->fetch_assoc()) {
                        $parsed      = brand_parse_config((string)$row['config']);
                        $branding    = (isset($parsed['branding']) && is_array($parsed['branding'])) ? $parsed['branding'] : array();
                        $custom_logo = (string)$row['custom_logo'];
                    }
                }
                if ($mysqli instanceof mysqli) {
                    $mysqli->close();
                }
            }
        } catch (\Throwable $e) {
            // any DB fault -> emit empty CSS; never leak an error on this pre-auth route
            $branding    = array();
            $custom_logo = '';
            $read_ok     = false;
        }
    }
}

// Re-assert the stylesheet MIME: config.inc.php sends text/html on web requests,
// and browsers reject a standards-mode stylesheet whose type isn't text/css.
header('Content-Type: text/css; charset=utf-8');

/* ---- caching ----------------------------------------------------------------
 * On a good read, cache briefly (with an ETag) so this isn't a blocking round-
 * trip on every full page load; the branding is global, so a saved change
 * appears within max-age or on a hard refresh. 'private' keeps it out of shared
 * / reverse-proxy caches. On a DB fault we still emit an (empty) sheet but must
 * NOT cache it — otherwise a transient outage would blank the host's branding
 * for the whole max-age window even after the DB recovers. */
if ($read_ok) {
    $etag = '"' . md5(serialize($branding) . '|' . md5($custom_logo)) . '"';
    header('ETag: ' . $etag);
    header('Cache-Control: private, max-age=30');
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }
} else {
    header('Cache-Control: no-store');
}

/* ---- resolve + validate the contract values ---- */
$accent   = brand_hex($branding, 'accent_hex');
$rail     = brand_hex($branding, 'rail_hex');
$login_bg = brand_hex($branding, 'login_bg');

$show_ispc  = !(isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0');
$show_theme = !(isset($branding['show_theme_credit'])     && $branding['show_theme_credit']     === '0');

$css = "/* Clarity brand overrides — generated by themes/clarity/brand.php */\n";

/* ---- accent: re-hue the blue ramp onto Clarity's tuned L ladder ---- */
if ($accent !== '') {
    // Clarity's blue ramp lightness ladder (H,S taken from the brand accent).
    $ladder = array(
        100 => 87, 200 => 78, 300 => 70, 400 => 59, 500 => 48,
        600 => 40, 700 => 34, 800 => 27, 900 => 21, 1000 => 15,
    );
    $root = '';
    foreach ($ladder as $step => $l) {
        $root .= sprintf("  --nz-blue-%d: %s;\n", $step, brand_shade($accent, $l));
    }

    // The rgba literals that carry the hue (tokens.css lines ~114-149).
    // Dark scope uses the bright accent (blue-400 role); light uses the base (blue-700 role).
    $bright = brand_shade($accent, 59); // ~ blue-400
    $base   = brand_shade($accent, 34); // ~ blue-700

    if ($rail !== '') {
        $root .= brand_rail_vars($rail);
    }
    $root .= '  --nz-focus-ring: '   . brand_rgba($bright, 0.40) . ";\n";
    $root .= '  --nz-selection-bg: ' . brand_rgba($bright, 0.35) . ";\n";
    $root .= '  --nz-row-hover: '    . brand_rgba($bright, 0.045) . ";\n";
    $root .= '  --nz-accent-edge: '  . brand_rgba($bright, 0.45) . ";\n";
    $root .= '  --nz-info-edge: '    . brand_rgba($bright, 0.35) . ";\n";
    $root .= '  --nz-info-tint: '    . brand_rgba($bright, 0.10) . ";\n";
    $root .= '  --nz-pulse-ring: '   . brand_rgba($bright, 0.45) . ";\n";
    $css  .= ":root {\n{$root}}\n";

    // Light scope redeclares the same literals + a few selection tints.
    $light  = '';
    $light .= '  --nz-focus-ring: '     . brand_rgba($base, 0.45) . ";\n";
    $light .= '  --nz-selection-bg: '   . brand_shade($accent, 82) . ";\n";
    $light .= '  --nz-selected: '       . brand_shade($accent, 93) . ";\n";
    $light .= '  --nz-selected-strong: ' . brand_shade($accent, 88) . ";\n";
    $light .= '  --nz-row-hover: '      . brand_rgba($base, 0.05) . ";\n";
    $light .= '  --nz-accent-edge: '    . brand_rgba($base, 0.40) . ";\n";
    $light .= '  --nz-info-edge: '      . brand_rgba($base, 0.30) . ";\n";
    $light .= '  --nz-info-tint: '      . brand_rgba($base, 0.08) . ";\n";
    $light .= '  --nz-pulse-ring: '     . brand_rgba($base, 0.40) . ";\n";
    // light info-alert surface is a hardcoded pale-blue literal in tokens.css — re-hue it too
    $light .= '  --nz-info-surface: '   . brand_shade($accent, 94) . ";\n";
    $css   .= ":root[data-nz-theme='light'] {\n{$light}}\n";
} elseif ($rail !== '') {
    // rail set without an accent — just the navy band
    $css .= ":root {\n" . brand_rail_vars($rail) . "}\n";
}

/* ---- login background ---- */
if ($accent !== '' || $login_bg !== '') {
    $g1   = $accent !== '' ? brand_shade($accent, 34) : '#0065AB';
    $g2   = $accent !== '' ? brand_shade($accent, 48) : '#0090F5';
    $base = $login_bg !== '' ? $login_bg : 'var(--nz-page)';
    $css .= "body.nz-login {\n  background:\n" .
            '    radial-gradient(640px 420px at 50% -8%, ' . brand_rgba($g1, 0.38) . ", transparent 68%),\n" .
            '    radial-gradient(900px 600px at 88% 112%, ' . brand_rgba($g2, 0.10) . ", transparent 60%),\n" .
            "    {$base};\n}\n";
    $css .= ":root[data-nz-theme='light'] body.nz-login {\n  background:\n" .
            '    radial-gradient(640px 420px at 50% -8%, ' . brand_rgba($g1, 0.14) . ", transparent 68%),\n" .
            '    radial-gradient(900px 600px at 88% 112%, ' . brand_rgba($g2, 0.05) . ", transparent 60%),\n" .
            "    {$base};\n}\n";
}

/* ---- logo: override the shipped wordmark ---- */
// Source precedence: [branding] logo_url (a file the admin references — a
// root-relative path, or an https URL) wins over the uploaded custom_logo
// data URI. Both are validated with anchored character-class regexes so no
// value can break out of the CSS url("...") context (no quotes, parens,
// whitespace, angle brackets, or backslashes can pass).
$logo_src = '';
if (isset($branding['logo_url']) && is_string($branding['logo_url'])
    && preg_match('#^(https://[^\s"\'<>()\\\\]+|/[^\s"\'<>()\\\\]+)$#', $branding['logo_url'])) {
    $logo_src = $branding['logo_url'];
} elseif ($custom_logo !== '' && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $custom_logo)) {
    $logo_src = $custom_logo;
}
if ($logo_src !== '') {
    // both dimensions auto + a max box -> the logo keeps its aspect ratio and fits,
    // for any width (the base rules pin a fixed height, which would distort wide logos).
    $css .= "#logo img { content: url(\"{$logo_src}\"); height: auto; width: auto; max-height: 26px; max-width: 180px; }\n";
    $css .= ".nz-topbar-brand img { content: url(\"{$logo_src}\"); height: auto; width: auto; max-height: 18px; max-width: 120px; }\n";
    $css .= ".nzl-brand img { content: url(\"{$logo_src}\"); height: auto; width: auto; max-height: 36px; max-width: 100%; }\n";
}

/* ---- attribution courtesy lines (source license notices are untouched) ---- */
// hide the ' · ' separator together with the ISPConfig credit, so the theme
// credit never renders with an orphaned leading middot
if (!$show_ispc)  { $css .= ".nz-credit-ispconfig, .nz-credit-sep { display: none; }\n"; }
if (!$show_theme) { $css .= ".nz-credit-theme { display: none; }\n"; }

echo $css;

/* ============================================================
 * Helpers — pure, dependency-free.
 * ============================================================ */

/**
 * Parse the whole sys_ini config blob with ISPConfig's own INI reader, so this
 * reader can never drift from the customizer's writer (which serialises with the
 * same framework class). ini_parser.inc.php is a pure, dependency-free class —
 * safe to require on this pre-auth path. Falls back to '' if it's ever missing.
 */
function brand_parse_config($config)
{
    $parser_file = __DIR__ . '/../../../lib/classes/ini_parser.inc.php';
    if (is_readable($parser_file)) {
        require_once $parser_file;
        if (class_exists('ini_parser')) {
            $p   = new ini_parser();
            $out = $p->parse_ini_string(stripslashes($config));
            return is_array($out) ? $out : array();
        }
    }
    return array();
}

/** The two rail custom-properties, emitted identically wherever rail is set. */
function brand_rail_vars($rail)
{
    return "  --nz-rail: {$rail};\n" .
           '  --nz-rail-active: ' . brand_shade($rail, 15) . ";\n";
}

/** Return a validated #rrggbb value from the branding array, or '' if absent/invalid. */
function brand_hex($branding, $key)
{
    if (isset($branding[$key]) && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding[$key])) {
        return $branding[$key];
    }
    return '';
}

/** Keep a hex colour's hue + saturation, set its lightness to $l (0-100). Returns #rrggbb. */
function brand_shade($hex, $l)
{
    list($h, $s, ) = brand_hex_to_hsl($hex);
    return brand_hsl_to_hex($h, $s, max(0.0, min(100.0, (float)$l)));
}

/** "rgba(r, g, b, a)" from a hex colour. */
function brand_rgba($hex, $alpha)
{
    $c = ltrim($hex, '#');
    $r = hexdec(substr($c, 0, 2));
    $g = hexdec(substr($c, 2, 2));
    $b = hexdec(substr($c, 4, 2));
    return sprintf('rgba(%d, %d, %d, %s)', $r, $g, $b, rtrim(rtrim(sprintf('%.3f', $alpha), '0'), '.'));
}

/** #rrggbb -> array(h[0-360], s[0-100], l[0-100]). */
function brand_hex_to_hsl($hex)
{
    $c = ltrim($hex, '#');
    $r = hexdec(substr($c, 0, 2)) / 255;
    $g = hexdec(substr($c, 2, 2)) / 255;
    $b = hexdec(substr($c, 4, 2)) / 255;
    $max = max($r, $g, $b);
    $min = min($r, $g, $b);
    $l = ($max + $min) / 2;
    $d = $max - $min;
    if ($d == 0) {
        return array(0, 0, $l * 100);
    }
    $s = $d / (1 - abs(2 * $l - 1));
    if ($max == $r) {
        $h = 60 * fmod((($g - $b) / $d), 6);
    } elseif ($max == $g) {
        $h = 60 * ((($b - $r) / $d) + 2);
    } else {
        $h = 60 * ((($r - $g) / $d) + 4);
    }
    if ($h < 0) {
        $h += 360;
    }
    return array($h, $s * 100, $l * 100);
}

/** h[0-360], s[0-100], l[0-100] -> #rrggbb. */
function brand_hsl_to_hex($h, $s, $l)
{
    $s /= 100;
    $l /= 100;
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;
    if ($h < 60)       { $rp = $c; $gp = $x; $bp = 0; }
    elseif ($h < 120)  { $rp = $x; $gp = $c; $bp = 0; }
    elseif ($h < 180)  { $rp = 0; $gp = $c; $bp = $x; }
    elseif ($h < 240)  { $rp = 0; $gp = $x; $bp = $c; }
    elseif ($h < 300)  { $rp = $x; $gp = 0; $bp = $c; }
    else               { $rp = $c; $gp = 0; $bp = $x; }
    return sprintf('#%02X%02X%02X',
        (int)round(($rp + $m) * 255),
        (int)round(($gp + $m) * 255),
        (int)round(($bp + $m) * 255));
}
