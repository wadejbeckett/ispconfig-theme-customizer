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
 *     config [branding] logo_variant_nav   -> ''|'on_light'|'on_dark'
 *     config [branding] logo_variant_login -> ''|'on_light'|'on_dark'
 *                                         the operator's override of WHICH mark
 *                                         a SURFACE uses; '' (and anything
 *                                         unrecognised) means automatic
 *   Resolution, which must stay identical to themes/classic/brand.php and to
 *   the customizer's lib/preview.inc.php:
 *     1. within a variant, a valid reference beats an uploaded data URI (the
 *        precedence logo_url has always had over custom_logo);
 *     2. each SURFACE asks for the variant matching the background IT actually
 *        has: the operator's explicit choice first, else the luminance of the
 *        colour they set for that surface, else this design's default;
 *     3. it falls back to the other variant when the wanted one is empty.
 *   Rule 2 used to be a constant here ("clarity is navy, so always the
 *   dark-background mark"), which the operator's own rail_hex / login_bg could
 *   falsify — a white rail_hex painted the white mark onto a white rail. Rule 3
 *   is what keeps the two-variant model non-breaking: a panel with only the
 *   historical custom_logo still renders it on every surface, exactly as before.
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
 * for the whole max-age window even after the DB recovers.
 *
 * serialize($branding) covers the WHOLE [branding] section, so every key this
 * file reads from it — including logo_variant_nav / logo_variant_login — already
 * invalidates the ETag when it changes. That stays true only while the variant a
 * surface uses is DERIVED from stored keys rather than stored itself: a derived
 * value that leaked into a column this hash does not cover would let a changed
 * preference serve a stale 304 for the whole max-age window. */
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
// Gate the branches below on the SLOTS, not on a resolved value. A surface
// preference chooses between the two marks; it must never be able to decide
// there is no mark at all and drop the panel into the company-name branch,
// whose rules would replace a logo the operator does have with their panel's
// name. See brand_logo_for_pref() for the other half of that guarantee.
$has_logo = ($logo_on_light !== '' || $logo_on_dark !== '');

// WHICH variant a slot wants is a property of the SURFACE, not of the design —
// so a third design inherits this without adding a case. Both surfaces are
// resolved on EVERY request because clarity's shells link this file with no
// ?scene= (main.tpl.htm:59 and main_login.tpl.htm:47 emit the identical URL) and
// the ETag above carries no scene either: one cached response body serves the
// app frame and the login screen, so the split can only be expressed as
// selectors sitting side by side in one sheet. (themes/classic/brand.php does
// have ?scene= and resolves one surface per request; do not copy that here.)
//
// NAV — #logo img on the rail and .nz-topbar-brand img on the mobile header
// chip. Both are painted var(--nz-rail): app.css:121 and app.css:710, which
// rail_hex overrides through brand_rail_vars() above. --nz-rail is declared
// exactly once, at tokens.css:88, and the light scope (tokens.css:215-302)
// never redeclares it — the rail is navy in both colour modes by design
// (tokens.css:210) — so this surface is MODE-INVARIANT and one fixed rule is
// always right. Note it is the CHIP that is navy in the header, not the bar:
// .nz-topbar reads --nz-topbar-bg (app.css:326), an ink-derived translucent
// that rail_hex never touches.
$nav_pref = brand_logo_variant_pref(
    isset($branding['logo_variant_nav']) ? $branding['logo_variant_nav'] : '',
    $rail,
    'on_dark'
);

// LOGIN — .nzl-brand img. main_login.tpl.htm:54-57 makes .nzl-brand a SIBLING
// above .nzl-card, and it and .nzl-scene are both transparent (login.css:67-83),
// so the mark sits directly on body.nz-login — the surface login_bg repaints in
// the block above. login_bg is therefore a real luminance input here (the accent
// radial gradients laid over it are alpha 0.38/0.10 dark and 0.14/0.05 light, a
// tint too faint to move the decision). That is clarity-specific: on classic the
// login mark is inside a Bootstrap card carrying its own inline light gradient,
// which login_bg cannot reach, so that design passes '' instead.
//
// '' is passed as the design default as a SENTINEL, not as a variant: getting it
// back means neither the operator nor login_bg spoke, and that is the one case
// with no single answer — $base falls back to var(--nz-page), which is #17252B
// dark and #F1F6F8 light (tokens.css:80, tokens.css:219). Every other case pins
// one backdrop across both colour modes and gets one fixed rule.
$login_pref = brand_logo_variant_pref(
    isset($branding['logo_variant_login']) ? $branding['logo_variant_login'] : '',
    $login_bg,
    ''
);
$login_follows_mode = ($login_pref === '');
if ($login_follows_mode) {
    $login_pref = 'on_dark'; // the base rule is the dark mode; light gets the override below
}

if ($has_logo) {
    $nav_src   = brand_logo_for_pref($nav_pref,   $logo_on_light, $logo_on_dark);
    $login_src = brand_logo_for_pref($login_pref, $logo_on_light, $logo_on_dark);
    // What LIGHT colour mode puts in the login slot. Where the backdrop is
    // pinned this is the same mark as the base rule and only the filter below
    // depends on it; where the backdrop follows the colour mode, light mode is a
    // light page and asks for the light-background mark.
    $light_want = $login_follows_mode ? 'on_light' : $login_pref;
    $light_src  = brand_logo_for_pref($light_want, $logo_on_light, $logo_on_dark);

    // Emit each DISTINCT value ONCE into a custom property and reference it from
    // the use sites. An uploaded logo is a base64 data URI up to the 45 KB cap,
    // so repeating it inline per selector multiplied the stylesheet by the number
    // of slots — with both variants supplied that was ~240 KB of CSS, served
    // UNAUTHENTICATED on every login page render. Resolving per surface adds use
    // sites but no artworks: there are only ever the two variant slots, so the
    // map below holds at most two entries and nav+login resolving alike still
    // costs one copy. Custom properties resolve order-independently, so this
    // block can precede the rules that use it.
    $logo_vars = array();
    $nav_var   = brand_logo_var($nav_src,   $logo_vars);
    $login_var = brand_logo_var($login_src, $logo_vars);
    $light_var = brand_logo_var($light_src, $logo_vars);
    $logo_root = '';
    foreach ($logo_vars as $src => $prop) {
        $logo_root .= "  {$prop}: url(\"{$src}\");\n";
    }
    $css .= ":root {\n{$logo_root}}\n";

    // both dimensions auto + a max box -> the logo keeps its aspect ratio and fits,
    // for any width (the base rules pin a fixed height, which would distort wide logos).
    $css .= "#logo img { content: var({$nav_var}); height: auto; width: auto; max-height: 26px; max-width: 180px; }\n";
    $css .= ".nz-topbar-brand img { content: var({$nav_var}); height: auto; width: auto; max-height: 18px; max-width: 120px; }\n";
    $css .= ".nzl-brand img { content: var({$login_var}); height: auto; width: auto; max-height: 36px; max-width: 100%; }\n";

    // The light-mode login rule is NOT optional once any logo exists.
    // login.css:95 applies filter: brightness(0.22) saturate(0.9) to
    // :root[data-nz-theme='light'] .nzl-brand img unconditionally, to ink-darken
    // the SHIPPED white wordmark; left standing it crushes a custom coloured mark
    // to near-black on the light login page. The selector is identical and this
    // sheet is linked after login.css (main_login.tpl.htm:43 then :47), so the
    // win is on source order — every path through here must set `filter`.
    //
    // The halo is a rescue, not decoration: it is what makes a mark readable on a
    // background it was not drawn for, so it belongs with the FALLBACK — the case
    // where the wanted slot is empty and the other variant is standing in. A mark
    // that is the one this backdrop asked for keeps its own colours and needs
    // nothing beyond cancelling the ink filter.
    //
    // A panel that stores only ONE variant keeps the halo, which is why the
    // second clause below exists. It is tempting to read a filled
    // light-background slot as the operator asserting the artwork suits a light
    // background — but with one logo stored they asserted nothing, because
    // sys_ini.custom_logo is the ONLY logo column ISPConfig has ever had and
    // every panel branded before this extension shipped put its mark there with
    // no variant to choose between. What that single mark was drawn for depends
    // entirely on the design it was uploaded against: on stock, whose header is
    // #F2F5F7, it is almost certainly dark; on clarity, whose rail is navy, it is
    // almost certainly WHITE — and a white mark with `filter: none` on clarity's
    // #F1F6F8 light-mode login page is an invisible logo. That is the population
    // this endpoint exists to serve, so one stored variant is treated as no
    // information and the rescue stays on.
    //
    // With BOTH variants stored the assertion is real — the operator filled two
    // slots labelled by background — so a mark matched to its backdrop keeps its
    // own colours and only needs the ink filter cancelled. That also holds when
    // the operator has FORCED a variant here: an "Always X" control that
    // second-guessed itself by luminance would have no function, and the halo
    // would not rescue it anyway (drop-shadow leaves white artwork white on
    // #F1F6F8, adding an aura rather than legibility).
    $light_decl = '';
    if ($light_src !== $login_src) {
        $light_decl .= "content: var({$light_var}); ";
    }
    $light_slot   = ($light_want === 'on_dark') ? $logo_on_dark : $logo_on_light;
    $one_variant  = ($logo_on_light === '' || $logo_on_dark === '');
    $light_decl  .= ($light_slot !== '' && !$one_variant)
        ? 'filter: none;'
        : 'filter: drop-shadow(0 1px 6px rgba(2, 26, 43, 0.35));';
    $css .= ":root[data-nz-theme='light'] .nzl-brand img { {$light_decl} }\n";

    // mask the content swap: on a hard refresh the SHIPPED wordmark paints for
    // a frame or two before the custom image decodes — a white-label leak. The
    // brand slots start invisible and fade in once the swap has had its beat.
    //
    // Gated on prefers-reduced-motion: NO-PREFERENCE — the idiom at
    // login.css:157 — and that gate is a fix, not tidying. app.css:779-786 and
    // login.css:366-371 both emit `animation: none !important` under
    // (prefers-reduced-motion: reduce); an !important author declaration beats
    // this rule's normal `animation` at any specificity and from any source
    // order, while its `opacity: 0` had nothing competing with it anywhere. Every
    // reduced-motion visitor to a panel with a custom logo was therefore served
    // opacity: 0 with nothing left to animate it back — no logo at all, on the
    // rail, the mobile chip and the login screen. Outside the gate they now get
    // the mark immediately, paying the one-frame flash the fade exists to hide,
    // which is the right way round: a masked swap is a nicety, a missing brand
    // is the failure this endpoint exists to prevent.
    $css .= "@media (prefers-reduced-motion: no-preference) {\n";
    $css .= "  @keyframes nzBrandIn { to { opacity: 1; } }\n";
    $css .= "  #logo img, .nz-topbar-brand img, .nzl-brand img { opacity: 0; animation: nzBrandIn 0.18s ease 0.05s forwards; }\n";
    $css .= "}\n";
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
    // Same reasoning as the config path resolved at top level: a bare __DIR__
    // walk lands in the git clone on a symlink install, so prefer the path the
    // web server actually requested. $config_inc is NOT usable here — this is a
    // function, and PHP has no implicit access to the enclosing file's scope;
    // referencing it emitted "Undefined variable" warnings straight into this
    // endpoint's CSS response, which corrupted the stylesheet.
    $parser_file = '';
    $_pf = array();
    if (!empty($_SERVER['SCRIPT_FILENAME'])) {
        $_pf[] = dirname($_SERVER['SCRIPT_FILENAME']) . '/../../../lib/classes/ini_parser.inc.php';
    }
    $_pf[] = __DIR__ . '/../../../lib/classes/ini_parser.inc.php';
    if (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $_pf[] = $_SERVER['DOCUMENT_ROOT'] . '/../lib/classes/ini_parser.inc.php';
    }
    foreach ($_pf as $_c) {
        if (is_readable($_c)) { $parser_file = $_c; break; }
    }
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

/**
 * Which logo VARIANT one surface wants: 'on_light' (the mark drawn FOR light
 * backgrounds, i.e. the dark artwork) or 'on_dark' (the mark drawn for dark
 * ones). This chooses a preference only; brand_logo_for_pref() turns it into a
 * value and is where the cross-variant fallback lives.
 *
 * $stored          the operator's [branding] logo_variant_nav /
 *                  logo_variant_login value, already read by the caller
 * $bg_hex          the colour THIS surface is actually painted, '' when the
 *                  operator has not set one — or when the setting that looks
 *                  like it belongs to this surface does not in fact reach it, as
 *                  in themes/classic/brand.php, where neither hex touches either
 *                  brand slot and both surfaces pass ''
 * $design_default  the answer when nothing else has spoken
 *
 * The precedence is load-bearing. The operator's explicit choice is checked
 * BEFORE the colour so that it is a real escape hatch and not a hint the
 * luminance can overrule: the automatic answer is a guess about a background,
 * and when it guesses wrong the mark is invisible with no other way out.
 *
 * Anything but the two literals — absent, empty, or garbage from a hand-edited
 * blob — is "automatic". Empty is the normal case, not a corner one: saving
 * "Automatic" writes the key with an empty value rather than dropping it
 * (ini_parser's writer emits `logo_variant_nav=` and its reader's
 * /^([\w\d_]+)=(.*)$/ reads it back as present-and-empty), so absent and empty
 * must be indistinguishable here. The === comparisons are also what keeps
 * mandate 4 out of play for this field: a value that is not one of the two
 * literals is never interpolated into the sheet, it only selects a branch.
 *
 * Duplicated as themes/classic/brand.php's brand_logo_variant_pref() and the
 * customizer's customizer_logo_variant_for_surface(), for the reason
 * brand_logo_variant() is: this is a pre-authentication endpoint that must keep
 * working with the customizer uninstalled, so it cannot include the module's
 * code. Same three checks, same order, same === comparisons, same fall-through
 * — a copy that disagrees shows the operator a preview of a logo the panel will
 * not render.
 *
 * The PLUMBING differs by one parameter and an auditor should expect it: the
 * other two take ($surface, $branding, …) and compose 'logo_variant_' . $surface
 * internally, while this copy is handed the value. Two reasons, both local to
 * this design. It resolves BOTH surfaces in one request — clarity's shells link
 * this sheet with no ?scene=, so there is no single $surface to compose from —
 * and reading the two keys at the call sites is what puts both names LITERALLY
 * in this file, which CI's "Brand-token contract parity" check
 * (.github/workflows/ci.yml) requires of every design's reader precisely so that
 * a renamed key cannot pass unnoticed. The same note is recorded on the
 * customizer's copy.
 */
function brand_logo_variant_pref($stored, $bg_hex, $design_default)
{
    if ($stored === 'on_light' || $stored === 'on_dark') {
        return $stored;
    }
    if (is_string($bg_hex) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $bg_hex)) {
        return brand_is_dark($bg_hex) ? 'on_dark' : 'on_light';
    }
    return $design_default;
}

/**
 * Is $hex dark enough that the mark drawn for dark backgrounds is the readable
 * one on it?
 *
 * WCAG 2.x relative luminance — the same maths behind every contrast ratio:
 * normalise each channel to 0..1, undo the sRGB transfer function, then weight
 * the linear channels by human luminous sensitivity. Averaging the raw bytes
 * instead gets this visibly wrong in both directions at once: #00FF00 and
 * #0000FF have the identical byte average of 85, yet their luminances are 0.715
 * and 0.072 — one wants the dark mark and the other wants the white one, and a
 * byte average cannot tell them apart at all.
 *
 * The 0.5 threshold is in LUMINANCE, not lightness, so it does not fall at the
 * middle of the byte range: measured, it sits between #BBBBBB and #BCBCBC, and
 * everything from there down counts as dark. That bias is deliberate — a light
 * mark on a slightly-too-light background disappears, while a dark mark on a
 * slightly-too-dark one is merely low-contrast.
 *
 * A malformed or empty hex returns FALSE — "not dark", which resolves to the
 * light-background mark. No call site here can reach that (brand_hex() has
 * already validated anything that gets this far, and the caller re-checks), and
 * the guard exists so that a future one gets a documented answer instead of a
 * PHP warning: on this endpoint a warning is emitted INTO a text/css response,
 * where it corrupts the stylesheet for every visitor including the login screen.
 * "Light" is also the safer of the two guesses — the light-background mark is
 * the one every panel already has, because it is the variant core itself stores
 * in sys_ini.custom_logo.
 *
 * BYTE-IDENTICAL to themes/classic/brand.php's brand_is_dark() and to the
 * customizer's customizer_hex_is_dark() (same arithmetic under this file's
 * naming convention). The prefix may differ between copies; the arithmetic and
 * the 0.5 constant may not. A copy that decided differently would make the
 * module's preview promise a mark the panel does not render, which is the one
 * thing that preview exists to prevent. All three change together.
 */
function brand_is_dark($hex)
{
    if (!is_string($hex) || !preg_match('/^#[0-9A-Fa-f]{6}$/D', $hex)) {
        return false;
    }
    $c = ltrim($hex, '#');
    $w = array(0.2126, 0.7152, 0.0722);
    $y = 0.0;
    for ($i = 0; $i < 3; $i++) {
        $v = hexdec(substr($c, $i * 2, 2)) / 255;
        $v = ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
        $y += $w[$i] * $v;
    }
    return ($y < 0.5);
}

/**
 * The value a surface renders: the variant it asked for, or the other one when
 * that slot is empty.
 *
 * The fallback is what makes a preference safe to expose at all. Every panel in
 * the field has exactly one logo, so a preference for the slot the operator has
 * not filled must render the one they have — never nothing. Dropping it would
 * also be worse than a blank slot on this design: with no image the sheet emits
 * no content: override, and the shipped ISPConfig-era wordmark paints in the
 * brand slots of a panel that has been white-labelled.
 *
 * These two lines are inlined rather than shared in themes/classic/brand.php,
 * which resolves ONE surface per request (?scene=login) and so needs them once;
 * this sheet carries both surfaces plus light mode's login slot and needs them
 * three times. Same expression, and it must stay that way.
 */
function brand_logo_for_pref($pref, $on_light, $on_dark)
{
    $primary = ($pref === 'on_dark') ? $on_dark  : $on_light;
    $other   = ($pref === 'on_dark') ? $on_light : $on_dark;
    return ($primary !== '') ? $primary : $other;
}

/**
 * Name the custom property carrying $src, registering it on first use so each
 * distinct artwork is emitted exactly once. $vars is the value => property map,
 * by reference; iterate it in insertion order to emit the :root block.
 *
 * Two names cannot run out: every use site draws from the same two variant
 * slots, so the map holds at most two entries however many slots reference them.
 * Nor can a registered property go unused — the only caller that can add the
 * second entry is the one whose value differs from the rules already emitted,
 * which is exactly the condition under which it emits a reference to it.
 */
function brand_logo_var($src, &$vars)
{
    if (!isset($vars[$src])) {
        $vars[$src] = empty($vars) ? '--nz-brand-logo' : '--nz-brand-logo-alt';
    }
    return $vars[$src];
}

/** The two rail custom-properties, emitted identically wherever rail is set. */
function brand_rail_vars($rail)
{
    return "  --nz-rail: {$rail};\n" .
           '  --nz-rail-active: ' . brand_shade($rail, 15) . ";\n";
}

/**
 * Return a validated #rrggbb value from the branding array, or '' if absent/invalid.
 *
 * The D modifier is here for the reason spelled out on brand_logo_variant():
 * without it PCRE's `$` also matches BEFORE a final newline, so "#FFFFFF\n"
 * validates and the raw LF is emitted into this sheet. It was benign while these
 * values only ever landed in a declaration (`background: #FFFFFF\n;` is legal
 * CSS); it is not benign now that the same values also decide which logo each
 * surface gets, where a hex that validates by accident silently picks a mark.
 */
function brand_hex($branding, $key)
{
    if (isset($branding[$key]) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$key])) {
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
