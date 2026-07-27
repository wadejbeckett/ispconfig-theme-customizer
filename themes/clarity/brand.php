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
 *   THE LOGO — two variants, each named by the BACKGROUND it sits on:
 *     custom_logo  column              -> LIGHT-background mark, uploaded
 *     config [branding] logo_url       -> LIGHT-background mark, by reference
 *                                         (root-relative path or https URL)
 *     config [branding] logo_on_dark   -> DARK-background mark, uploaded
 *                                         (a data URI; core has no column for
 *                                         it and we may not add one)
 *     config [branding] logo_url_on_dark -> DARK-background mark, by reference
 *   Resolution, which must stay identical to themes/classic/brand.php and to
 *   the customizer's lib/preview.inc.php:
 *     1. within a variant, a valid reference beats an uploaded data URI (the
 *        precedence logo_url has always had over custom_logo);
 *     2. this design asks for the variant matching ITS OWN background and falls
 *        back to the other when that variant is empty.
 *   Clarity's rail and topbar are navy in both colour modes, so the wanted
 *   variant here is the DARK-background one. Rule 2 is what keeps this change
 *   non-breaking: a panel with only the historical custom_logo still renders it
 *   everywhere, exactly as before.
 *
 *   config [branding] accent_hex  -> re-hues the blue ramp + accents
 *   config [branding] rail_hex    -> the navy brand rail
 *   config [branding] login_bg    -> login-screen background base
 *   config [branding] show_ispconfig_credit (0/1) -> footer courtesy line
 *   config [branding] show_theme_credit     (0/1) -> footer courtesy line
 *   config [branding] show_version (0/1)    -> 0 hides Help's version surfaces
 *   config [misc] company_name              -> text wordmark when no logo set;
 *                                              alt/failover text via title.php
 *
 * Design constraints:
 *   - Pre-auth safe: the login screen links this file, so it must
 *     work with no session. It does a single, side-effect-free,
 *     read-only query of one sys_ini row (no ISPConfig app bootstrap,
 *     no maintenance-mode redirects, no session start).
 *   - Always HTTP 200 with valid CSS. When nothing is set it emits an
 *     empty sheet and the theme falls back to its shipped tokens/logo —
 *     so it is a no-op both without the customizer and before first use.
 *   - Injection-safe: every value is validated or escaped before it
 *     reaches the output (hex regex / data-URI regex / url allowlist
 *     regex / 0|1 / control-char strip + CSS-string escape + length
 *     cap for the text wordmark).
 *   - company_name is normalised identically here and in title.php
 *     (stripslashes on the blob, control-char strip, trim) so the CSS
 *     wordmark and the tab title / alt text never disagree about the
 *     panel's own name. Change one, change the other.
 *   - The 40-char cap is NOT part of that parity and must not be made
 *     part of it. It exists only for the nowrap rail/topbar slot, so it
 *     applies to this CSS wordmark and to title.php's visible failover
 *     wordmark — the two things that land in that slot. document.title
 *     and img alt text stay UNCAPPED on purpose: a tab elides on its own,
 *     and an accessible name should be the real brand, not one trimmed to
 *     fit a layout.
 * ============================================================ */

header('Content-Type: text/css; charset=utf-8');

/* ---- read the contract (direct, minimal, side-effect-free) ---- */
$branding     = array();
$custom_logo  = '';
$company_name = '';
$read_ok      = false; // true only when the sys_ini read actually succeeded

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
                        if (isset($parsed['misc']['company_name']) && is_string($parsed['misc']['company_name'])) {
                            // Normalise EXACTLY as title.php does, so the two brand
                            // endpoints can never derive different strings from the
                            // same sys_ini row (they co-render on the login page: the
                            // CSS wordmark here, the tab title / <img alt> there).
                            // Only control characters are removed, and only because
                            // they cannot be represented: a raw CR/LF terminates a CSS
                            // string, after which the parser's error recovery would
                            // read the remainder of the name as further declarations.
                            // Everything printable survives and is escaped at the
                            // emit site instead — see the wordmark block below.
                            // The class is applied byte-wise and deliberately WITHOUT
                            // /u: with /u a single malformed UTF-8 byte makes
                            // preg_replace return NULL and the brand silently vanishes,
                            // while 0x00-0x1F and 0x7F never occur inside a valid UTF-8
                            // sequence (continuation bytes are 0x80-0xBF), so a byte
                            // filter cannot damage a multibyte character.
                            $company_name = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $parsed['misc']['company_name']));
                            // multibyte-safe cap: byte-substr would cut a UTF-8
                            // codepoint in half and corrupt the rendered wordmark
                            if (function_exists('mb_substr')) {
                                if (mb_strlen($company_name, 'UTF-8') > 40) $company_name = mb_substr($company_name, 0, 40, 'UTF-8');
                            } elseif (strlen($company_name) > 40) {
                                $company_name = substr($company_name, 0, 40);
                            }
                        }
                        $custom_logo = (string)$row['custom_logo'];
                    }
                }
                if ($mysqli instanceof mysqli) {
                    $mysqli->close();
                }
            }
        } catch (\Throwable $e) {
            // any DB fault -> emit empty CSS; never leak an error on this pre-auth route
            $branding     = array();
            $custom_logo  = '';
            $company_name = '';
            $read_ok      = false;
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
    $etag = '"' . md5(serialize($branding) . '|' . md5($custom_logo) . '|' . $company_name) . '"';
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
// Two variants, resolved by brand_logo_variant() below: within each, a
// reference (a file the admin points at) beats the uploaded data URI. Every
// value is validated with an anchored character-class regex so none can break
// out of the CSS url("...") context — no quotes, parens, whitespace, angle
// brackets or backslashes can pass.
$logo_on_dark = brand_logo_variant(
    isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    isset($branding['logo_on_dark'])     ? $branding['logo_on_dark']     : ''
);
$logo_on_light = brand_logo_variant(
    isset($branding['logo_url']) ? $branding['logo_url'] : '',
    $custom_logo
);
// Clarity's brand slots sit on the navy rail, the navy topbar and the login
// band, so this design wants the DARK-background mark — and falls back to the
// light-background one when the operator has only set that. One logo in
// custom_logo, which is every panel in the field today, therefore renders
// exactly as it did before this second variant existed.
$logo_src = ($logo_on_dark !== '') ? $logo_on_dark : $logo_on_light;

if ($logo_src !== '') {
    // Emit the value ONCE into a custom property and reference it from each slot.
    // An uploaded logo is a base64 data URI up to the 45 KB cap, so repeating it
    // inline per selector multiplied the stylesheet by the number of slots — with
    // both variants supplied that was ~240 KB of CSS, served UNAUTHENTICATED on
    // every login page render. A custom property costs one copy and the browser
    // resolves it at each use site.
    $css .= ":root { --nz-brand-logo: url(\"{$logo_src}\"); }\n";
    // both dimensions auto + a max box -> the logo keeps its aspect ratio and fits,
    // for any width (the base rules pin a fixed height, which would distort wide logos).
    $css .= "#logo img { content: var(--nz-brand-logo); height: auto; width: auto; max-height: 26px; max-width: 180px; }\n";
    $css .= ".nz-topbar-brand img { content: var(--nz-brand-logo); height: auto; width: auto; max-height: 18px; max-width: 120px; }\n";
    $css .= ".nzl-brand img { content: var(--nz-brand-logo); height: auto; width: auto; max-height: 36px; max-width: 100%; }\n";
    // The rail and topbar are navy in BOTH colour modes, so the mark above is
    // right for them everywhere. The LOGIN slot is the one exception: in light
    // mode .nzl-brand sits on a light page, which is the same background/artwork
    // mismatch this two-variant model exists to fix, one slot further down.
    //
    // So when the operator has genuinely supplied both variants, give the light
    // mode's login slot the light-background mark — and drop the halo with it,
    // because a mark that suits its background does not need rescuing. When only
    // one variant is set the two resolve to the same value, the condition is
    // false, and the halo is emitted exactly as it always was.
    if ($logo_on_light !== '' && $logo_on_dark !== '' && $logo_on_light !== $logo_src) {
        $css .= ":root { --nz-brand-logo-light: url(\"{$logo_on_light}\"); }\n";
        $css .= ":root[data-nz-theme='light'] .nzl-brand img { content: var(--nz-brand-logo-light); filter: none; }\n";
    } else {
        // custom logos keep their own colours in light mode: undo the theme's
        // ink-darkening of the shipped wordmark, add a soft halo for legibility
        $css .= ":root[data-nz-theme='light'] .nzl-brand img { filter: drop-shadow(0 1px 6px rgba(2, 26, 43, 0.35)); }\n";
    }
    // mask the content swap: on a hard refresh the SHIPPED wordmark paints for
    // a frame or two before the custom image decodes — a white-label leak. The
    // brand slots start invisible and fade in once the swap has had its beat.
    $css .= "@keyframes nzBrandIn { to { opacity: 1; } }\n";
    $css .= "#logo img, .nz-topbar-brand img, .nzl-brand img { opacity: 0; animation: nzBrandIn 0.18s ease 0.05s forwards; }\n";
} elseif ($company_name !== '') {
    // no logo uploaded/referenced, but the panel is named: the NAME becomes the
    // wordmark (CSS text content on the brand slots). Rail/topbar are navy in
    // both modes -> white text; the login slot inherits the theme's light-mode
    // ink filter, which correctly darkens this text too.
    //
    // Escape for the CSS string context rather than deleting: inside content:"…"
    // only " (which would close the string) and \ (which would start an escape
    // sequence and swallow the next character) have any meaning, and both have a
    // lossless CSS escape. CR/LF — the one thing with no escape worth using —
    // were already removed on read. Order matters: backslashes are doubled first,
    // so the backslash this adds in front of a quote is not itself doubled.
    // < and > are deliberately left untouched: brand.php is only ever fetched
    // through <link rel='stylesheet'> (main.tpl.htm:53, main_login.tpl.htm:45),
    // never inlined into a <style> block, so angle brackets have no HTML context
    // to break out of — and deleting them silently rewrote legitimate panel names
    // ("Host > Cloud" lost its arrow, '"Acme" Hosting' lost its quotes) while
    // title.php's tab title on the same screen rendered them intact.
    $wordmark_css = str_replace(array('\\', '"'), array('\\\\', '\\"'), $company_name);
    $css .= "#logo img, .nz-topbar-brand img, .nzl-brand img { content: \"{$wordmark_css}\"; "
          . "font: 600 15px/1.3 'Inter', -apple-system, sans-serif; color: #fff; "
          . "white-space: nowrap; letter-spacing: 0.01em; }\n";
    $css .= ".nzl-brand img { font-size: 19px; }\n";
}

/* ---- attribution courtesy lines (source license notices are untouched) ---- */
// hide the ' · ' separator together with the ISPConfig credit, so the theme
// credit never renders with an orphaned leading middot
if (!$show_ispc)  { $css .= ".nz-credit-ispconfig, .nz-credit-sep { display: none; }\n"; }
if (!$show_theme) { $css .= ".nz-credit-theme { display: none; }\n"; }

/* ---- software version visibility (demo/white-label mode) ---- */
// [branding] show_version = 0 hides Help's version surfaces for EVERY user,
// including the operator (CSS cannot see roles): the sidebar "About ISPConfig"
// section with its Version item, and the version line itself — p.frmTextHead
// is used by help/version.php alone across the whole 3.3.1p1 interface.
if (isset($branding['show_version']) && $branding['show_version'] === '0') {
    $css .= "#sidebar li#help_version, #sidebar ul:has(> li#help_version), "
          . "#sidebar header:has(+ ul > li#help_version) { display: none; }\n";
    $css .= "#pageContent p.frmTextHead { display: none; }\n";
}

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
    // Same reasoning as the config path above: a second __DIR__ walk lands in
    // the clone on a symlink install. Derive it from the config path that was
    // actually found, so the two can never disagree about where the panel is.
    $parser_file = ($config_inc !== '')
        ? dirname($config_inc) . '/classes/ini_parser.inc.php'
        : __DIR__ . '/../../../lib/classes/ini_parser.inc.php';
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

/**
 * Resolve ONE logo variant to the value this sheet may emit, or '' when the
 * variant is unset or holds something we will not print.
 *
 * $ref  the by-reference slot   (logo_url / logo_url_on_dark)
 * $data the uploaded data URI   (sys_ini.custom_logo / [branding] logo_on_dark)
 *
 * The reference wins, which is the precedence logo_url has always had over
 * custom_logo — unchanged, now simply applied to each variant in turn.
 *
 * Both patterns are anchored and both are duplicated in themes/classic/brand.php
 * and in the customizer's lib/preview.inc.php. The duplication is deliberate:
 * this is a pre-authentication endpoint that must keep working with the
 * customizer uninstalled, so it cannot include the module's code. All copies
 * must be changed together.
 *
 * (?!/) rejects protocol-relative "//host/..." — a REMOTE url in disguise, which
 * would defeat the local-path privacy contract; writer-side validation matches.
 *
 * The D modifier is load-bearing, not decoration: without it PCRE's `$` also
 * matches BEFORE a final newline, so "/img/logo.png\n" would validate and the
 * raw LF would be emitted inside content: url("...") — a literal newline
 * terminates a double-quoted CSS string, breaking this sheet for every visitor
 * including pre-auth on the login screen. The writer-side validator in the
 * module's tform carries /D for the same reason.
 */
function brand_logo_variant($ref, $data)
{
    if (is_string($ref) && $ref !== ''
        && preg_match('#^(https://[^\s"\'<>()\\\\]+|/(?!/)[^\s"\'<>()\\\\]+)$#D', $ref)) {
        return $ref;
    }
    if (is_string($data) && $data !== ''
        && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $data)) {
        return $data;
    }
    return '';
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
