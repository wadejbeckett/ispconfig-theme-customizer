<?php
/* ============================================================
 * classic — brand.php  (the brand READER for the stock design)
 * ------------------------------------------------------------
 * classic IS the stock ISPConfig look, made brandable. It ships two shell
 * templates and no stylesheet of its own: every pixel comes from the stock
 * `default` theme's assets. So this file overrides STOCK's own selectors —
 * theme.min.css, ispconfig.css and the Bootstrap 3.3.0 underneath them — and
 * NOT Clarity's --nz-* custom properties, which do not exist on this design.
 * It is the same brand-token contract, read against a different skin.
 *
 * Contract consumed (sys_ini, global row sysini_id = 1):
 *   THE LOGO — two variants, each named by the BACKGROUND it sits on:
 *     custom_logo  column              -> LIGHT-background mark, uploaded.
 *                                         Usually nothing to do: CORE itself
 *                                         inlines that column on the stock
 *                                         shell, and we must not fight it.
 *     config [branding] logo_url       -> LIGHT-background mark, by reference
 *     config [branding] logo_on_dark   -> DARK-background mark, uploaded
 *     config [branding] logo_url_on_dark -> DARK-background mark, by reference
 *     config [branding] logo_variant_nav   -> ''|'on_light'|'on_dark': which
 *     config [branding] logo_variant_login    variant each SURFACE asks for.
 *                                         '' — and absent — mean AUTOMATIC.
 *   Resolution, identical to themes/clarity/brand.php and to the customizer's
 *   lib/preview.inc.php:
 *     1. within a variant, a valid reference beats an uploaded data URI;
 *     2. each SURFACE asks for the variant matching its own background and
 *        falls back to the other when the asked-for variant is empty.
 *   Which variant a surface wanted used to be hardcoded here — "classic is the
 *   light design, therefore the light-background mark" — and that is an
 *   assumption the operator's own colour settings can falsify. It is now
 *   resolved per surface. On classic the answer still comes out LIGHT on both
 *   surfaces, because neither of its logo slots sits on a colour the operator
 *   can set; that is a finding backed by stock's markup and sheets, and the
 *   evidence is recorded in the logo block below so it is not "fixed" back.
 *   The light-background mark is exactly the column core already renders, so in
 *   the common case this file still emits nothing at all for the logo.
 *
 *   config [branding] accent_hex  -> primary action, links, focus, active nav
 *   config [branding] rail_hex    -> the main-navigation band (+ mobile menu)
 *   config [branding] login_bg    -> login-screen background
 *   config [branding] show_version (0/1) -> 0 hides Help's version surfaces
 *   config [misc] company_name    -> text wordmark when no logo is set;
 *                                    tab title / alt text via title.php
 *
 *   config [branding] show_ispconfig_credit (0/1) -> footer courtesy line
 *   config [branding] show_theme_credit     (0/1) -> footer courtesy line
 *
 * The footer toggles need a hand from install.sh. Stock prints the credit as
 * bare text inside <footer id='footer'>, which CSS can only hide wholesale, so
 * the generator splits it into .nzc-credit-ispconfig (core's line) and appends
 * .nzc-credit-theme (ours) while building this design's shell. Every option on
 * the Branding page then behaves the same way whichever design is active —
 * a toggle that silently does nothing on one design is a bug, not a nuance.
 *
 * Both ship ON, and neither touches a licence notice: these are courtesy lines.
 *
 * ---- how a colour is derived -------------------------------------------
 * Two rules, and which one applies depends on what the colour is FOR.
 *
 * TEXT and TINTS take stock's ABSOLUTE lightness for that exact role (the
 * ladder is annotated inline with the stock hex it replaces) and keep only the
 * operator's hue and saturation. Lightness is what carries contrast, so a
 * re-brand inherits the contrast relationships ISPConfig already chose; and
 * stock's link lightness is an affordance as much as a colour — a navy brand
 * whose links were navy would read as body text.
 *   Except that lightness is not luminance: hsl(208,56%,52%) — stock's link
 *   blue — and hsl(60,100%,52%) — a yellow brand — sit on the same rung while
 *   the yellow is roughly five times as bright, so a yellow accent dropped in at
 *   stock's lightness is unreadable. Every text role therefore goes through
 *   brand_readable(), which walks the lightness away from its background until
 *   the pair clears a WCAG ratio.
 *
 * FILLED BRAND SURFACES — the primary button, the navigation band, the login
 * submit — use the operator's colour EXACTLY as entered, and take only the
 * RELATIVE geometry from stock: the gradient split, the border offset, the
 * hover delta, the pressed edge, each measured off the stock rule it replaces.
 * These are the surfaces where someone who typed #8B0000 has to see #8B0000;
 * re-lighting them onto stock's green ladder (lightness 57-64) would hand them
 * #FF2424 and no amount of correct contrast makes that their brand. Their ink
 * is then chosen by brand_ink(), so legibility survives any hue.
 *
 * ---- design constraints (carried over from Clarity's brand.php) ---------
 *   - Pre-auth safe: the login screen links this file, so it must work with no
 *     session. A single, side-effect-free, read-only query of one sys_ini row —
 *     no ISPConfig app bootstrap, no maintenance-mode redirect, no session.
 *   - Always HTTP 200 with valid CSS. With nothing set it emits an empty sheet
 *     and the panel is stock, so it is a no-op both without the Branding page
 *     installed and before first use.
 *   - Injection-safe: every value is validated for its output context before it
 *     reaches the page (anchored hex regex / data-URI regex / url allowlist
 *     regex with /D / 0|1 / control-char strip + CSS-string escape + length cap).
 *   - company_name is normalised identically here and in title.php so the CSS
 *     wordmark and the tab title never disagree about the panel's own name.
 *     Change one, change the other.
 *
 * ---- ?scene=login --------------------------------------------------------
 * A few rules must apply to the login screen only. Clarity scopes them with a
 * body class it puts in its own login template; classic cannot, because its
 * templates are generated from the panel's stock ones and the transform is not
 * allowed to touch the markup. Stock's login <body> carries no class, no id and
 * nothing stable to hang a :has() selector on. So install.sh links THIS
 * endpoint as brand.php?scene=login from the login template only, and the
 * page-scoped rules key off the URL instead of the DOM. Everything else is
 * emitted in both scenes.
 * ============================================================ */

header('Content-Type: text/css; charset=utf-8');

// Only ever compared, never emitted. The login shell is the only template
// install.sh gives this parameter to.
$scene = (isset($_GET['scene']) && $_GET['scene'] === 'login') ? 'login' : 'app';

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
                        $parsed   = brand_parse_config((string)$row['config']);
                        $branding = (isset($parsed['branding']) && is_array($parsed['branding'])) ? $parsed['branding'] : array();
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
                            // codepoint in half and corrupt the rendered wordmark.
                            // The cap is for the nowrap header slot only — title.php
                            // keeps document.title and alt text uncapped on purpose.
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
 * The scene is part of the validator: the two scenes are different URLs, but an
 * ETag that ignored it would let a stale revalidation cross them. It carries a
 * second job since the logo variant became per-surface — the two scenes now
 * resolve their logos independently, so their sheets can differ by more than
 * the login-only rules.
 * The two logo_variant_* keys need no term of their own here: the contract
 * stores them in [branding], serialize($branding) already hashes them, and
 * flipping either one therefore moves this validator. That is the ONLY thing
 * keeping them cached correctly — move either key out of the [branding]
 * section and this line has to name it by hand, or a saved change would sit
 * behind a stale 304 for the whole max-age window. */
if ($read_ok) {
    $etag = '"' . md5($scene . '|' . serialize($branding) . '|' . md5($custom_logo) . '|' . $company_name) . '"';
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

// The accent's own lightness, resolved once: every filled brand surface below
// offsets from it rather than from a fixed rung (see "how a colour is derived").
$accent_l = 0.0;
if ($accent !== '') {
    list(, , $accent_l) = brand_hex_to_hsl($accent);
}

// Stock's two constant surfaces, used as the contrast reference for text:
// body background (theme.min.css) and the white cards/tables on top of it.
$page_bg  = '#F2F5F7';
$card_bg  = '#FFFFFF';

$css = "/* classic brand overrides — generated by themes/classic/brand.php */\n";

/* =========================================================================
 * accent — the primary action colour, links, focus, information tints
 * =======================================================================*/
if ($accent !== '') {
    // Links. Stock: a{color:#428bca} / a:hover,a:focus{color:#2a6496}
    // (bootstrap.min.css 3.3.0) = lightness 52 and 38 on the white content area.
    $link       = brand_readable($accent, 52, $card_bg, 4.5);
    $link_hover = brand_readable($accent, 38, $card_bg, 4.5);
    $css .= "a { color: {$link}; }\n";
    $css .= "a:hover, a:focus { color: {$link_hover}; }\n";

    // Stock's pale-blue "information" family, re-hued at its own lightness:
    // #sidebar / #sidebar header (#dfeaf6 bg, #cedded edges, #698296 ink) and
    // .alert-notification, which reuses exactly those three values — plus the
    // table row hover, which is the same #dfeaf6. This family is ISPConfig's
    // accent identity; leaving it blue is what makes a re-brand look half-done.
    $tint      = brand_shade($accent, 92);              // #dfeaf6
    $tint_edge = brand_shade($accent, 87);              // #cedded
    $tint_ink  = brand_readable($accent, 50, $tint, 4.5); // #698296 on that tint
    $side_link = brand_readable($accent, 56, $card_bg, 4.5); // #428bde
    $note_link = brand_readable($accent, 46, $tint, 4.5);    // #2371ca on the tint
    $css .= "#sidebar { border-color: {$tint_edge}; }\n";
    $css .= "#sidebar header { background: {$tint}; color: {$tint_ink}; }\n";
    $css .= "#sidebar li { border-top-color: {$tint_edge}; }\n";
    $css .= "#sidebar a:hover { color: {$side_link}; }\n";
    $css .= ".alert-notification { background: {$tint}; border-color: {$tint_edge}; color: {$tint_ink}; }\n";
    $css .= ".alert-notification a { color: {$note_link}; }\n";
    $css .= ".alert-notification a:hover { color: {$link_hover}; }\n";
    $css .= ".table tbody tr:hover { background: {$tint}; }\n";

    // Focus ring. Stock narrows Bootstrap's blue glow to a border colour only
    // (theme.min.css .form-control:focus{border-color:#698296}) and leaves
    // Bootstrap's rgba(102,175,233,.6) glow in place, so both have to move
    // together or the ring and the border disagree about the brand. A ring is a
    // brand surface, so it starts at the accent as entered and only darkens if
    // it would otherwise disappear into the white field. 3:1 is the WCAG
    // threshold for a non-text indicator, not 4.5.
    $focus = brand_readable($accent, $accent_l, $card_bg, 3.0);
    $css .= ".form-control:focus { border-color: {$focus}; "
          . 'box-shadow: inset 0 1px 1px rgba(0, 0, 0, 0.075), 0 0 8px ' . brand_rgba($focus, 0.5) . "; }\n";

    // Primary action. In stock markup that is .formbutton-success — the Save
    // button on every tform page (there is no .btn-primary anywhere in the
    // 3.3.1p1 interface; it is styled anyway so third-party modules inherit the
    // brand).
    //
    // The face IS the accent, and stock supplies only the geometry around it.
    // The offsets are the deltas of theme.min.css's own green ladder measured
    // from its midpoint (#7bcc89/#6ab977 gradient = +3.5/-3.5, #5aab68 border =
    // -9.5, #74c982/#63b671 hover = -2 on both stops, #509b5a pressed edge =
    // -14.5), so the button keeps stock's depth, its border weight and its hover
    // step while showing the operator's actual colour.
    $btn_ink    = brand_ink($accent);
    $btn_top    = brand_shade($accent, $accent_l + 3.5);
    $btn_bottom = brand_shade($accent, $accent_l - 3.5);
    $btn_edge   = brand_shade($accent, $accent_l - 9.5);
    $btn_top_h  = brand_shade($accent, $accent_l + 1.5);
    $btn_bot_h  = brand_shade($accent, $accent_l - 5.5);
    $btn_edge_a = brand_shade($accent, $accent_l - 14.5);
    $css .= ".formbutton-success { background: linear-gradient(to bottom, {$btn_top}, {$btn_bottom}); "
          . "border-color: {$btn_edge}; border-bottom-color: {$btn_edge}; color: {$btn_ink}; }\n";
    $css .= ".formbutton-success:hover { background: linear-gradient(to bottom, {$btn_top_h}, {$btn_bot_h}); }\n";
    $css .= ".formbutton-success:hover, .formbutton-success.active { border-bottom-color: {$btn_edge_a}; color: {$btn_ink}; }\n";

    // Bootstrap's own primary, same treatment with its own deltas
    // (#428bca face / #357ebd edge = -5, #3071a9 hover = -10, #285e8e = -17).
    $bp_edge   = brand_shade($accent, $accent_l - 5);
    $bp_face_h = brand_shade($accent, $accent_l - 10);
    $bp_edge_h = brand_shade($accent, $accent_l - 17);
    $css .= ".btn-primary { color: {$btn_ink}; background-color: {$accent}; border-color: {$bp_edge}; }\n";
    $css .= ".btn-primary:hover, .btn-primary:focus, .btn-primary.focus, .btn-primary:active, "
          . ".btn-primary.active, .open > .dropdown-toggle.btn-primary "
          . "{ color: {$btn_ink}; background-color: {$bp_face_h}; border-color: {$bp_edge_h}; }\n";
}

/* =========================================================================
 * rail — the main-navigation band
 * -------------------------------------------------------------------------
 * Stock has no coloured band: #main-navigation a is a row of white-to-#eef0f2
 * Bootstrap buttons, and the ACTIVE one is marked with ISPConfig red text
 * (#c70f19, theme.min.css). rail_hex turns that row into a flat brand band; the
 * accent then marks the active item. Every rule below also has to beat
 * .btn-default's own hover/focus/active greys, which is why :focus and :active
 * are carried alongside :hover — without them the band flashes Bootstrap grey
 * on click.
 * .pushy is the same navigation below 670px, where #main-navigation is
 * display:none — branding one and not the other would leave phones stock.
 * =======================================================================*/
if ($rail !== '') {
    $rail_ink  = brand_ink($rail);                 // white or stock ink #3c444b
    $rail_dim  = brand_rgba($rail_ink, 0.82);      // inactive items, one step back
    list(, , $rail_l) = brand_hex_to_hsl($rail);
    // Hover/active/edges move TOWARD the ink: lighter on a dark band, darker on
    // a light one. One direction flag keeps every derived shade consistent.
    $toward     = ($rail_ink === '#FFFFFF') ? 1 : -1;
    $rail_hover = brand_shade($rail, $rail_l + $toward * 7);
    $rail_act   = brand_shade($rail, $rail_l + $toward * 13);
    $rail_edge  = brand_shade($rail, $rail_l + $toward * 10);
    // The active item's underline is the accent when there is one — as entered,
    // like every other brand surface — but it has to stay visible ON the band,
    // not on the page, so the walk is against the rail itself. That also covers
    // the operator who sets accent and rail to the SAME colour: the underline
    // steps away until it separates. 3:1 is the WCAG threshold for a non-text cue.
    $rail_mark  = ($accent !== '') ? brand_readable($accent, $accent_l, $rail, 3.0) : $rail_edge;

    $css .= "#main-navigation a { background: {$rail}; border-color: {$rail_edge}; "
          . "border-bottom-color: {$rail_edge}; color: {$rail_dim}; }\n";
    $css .= "#main-navigation a:hover, #main-navigation a:focus, #main-navigation a:active "
          . "{ background: {$rail_hover}; color: {$rail_ink}; }\n";
    $css .= "#main-navigation a.active { background: {$rail_act}; color: {$rail_ink}; }\n";
    $css .= "#main-navigation a:hover, #main-navigation a.active { border-bottom-color: {$rail_mark}; }\n";
    if ($rail_ink === '#FFFFFF') {
        // theme.min.css gives the module icons a 1px white text-shadow — an
        // emboss that only works on the stock near-white band. On a dark rail it
        // reads as a halo around every glyph.
        $css .= "#main-navigation .icon { text-shadow: none; }\n";
    }
    $css .= ".pushy { background: {$rail}; }\n";
    $css .= ".pushy a { color: {$rail_dim}; }\n";
    $css .= ".pushy a:hover, .pushy a.active { background: {$rail_hover}; color: {$rail_ink}; }\n";
} elseif ($accent !== '') {
    // No rail: keep stock's white button row and just replace the ISPConfig red
    // that marks the active module (#c70f19, lightness 42) with the brand. The
    // label is text on the row's own near-white gradient face, so it takes the
    // absolute rung; the underline beneath it is a graphic, so it stays the
    // accent as entered unless that would vanish into the row.
    $nav_mark = brand_readable($accent, 42, '#F6F8F9', 4.5);
    $nav_edge = brand_readable($accent, $accent_l, '#F6F8F9', 3.0);
    $css .= "#main-navigation a:hover, #main-navigation a.active { color: {$nav_mark}; }\n";
    $css .= "#main-navigation a.active { border-bottom-color: {$nav_edge}; }\n";
}

/* =========================================================================
 * logo + wordmark
 * -------------------------------------------------------------------------
 * Same two-variant model as Clarity, resolved per SURFACE rather than per
 * design. classic has two logo slots and they are never live in the same
 * response — install.sh:384 hands ?scene=login to the login shell alone — so
 * one request paints one surface and resolves one preference. The panel name
 * becomes the wordmark only when there is no logo of either variant.
 *
 * ---- WHAT AUTOMATIC MEASURES ON CLASSIC: nothing, on either surface --------
 * The resolver takes the background colour as a parameter because the mapping
 * from surface to background is a property of the DESIGN, not of the feature.
 * Clarity hands it rail_hex and login_bg because its two slots genuinely sit on
 * those colours. classic hands it '' for BOTH, because neither of its slots
 * does, and automatic therefore falls through to this design's default of
 * 'on_light'. That is a determination with evidence, not an omission — do not
 * "finish" it by wiring rail_hex and login_bg in:
 *
 *   LOGIN — the mark is inside a Bootstrap card whose header paints itself.
 *   .refs/ispconfig3/interface/web/themes/default/templates/main_login.tpl.htm
 *   :39-40 is  <div class="panel-heading" style="background: linear-gradient(
 *   to bottom, white, #eef0f2);text-align:center;">  with the <img> as its
 *   direct child. That gradient is an INLINE style attribute, so no external
 *   sheet can move it without !important, and nothing tries: the login block
 *   further down emits `body { background: … }` and nothing else, stock's
 *   login-only sheet (themes/default/assets/stylesheets/login.css, linked at
 *   main_login.tpl.htm:31) carries no background rule at all, and Bootstrap's
 *   own .panel-default > .panel-heading is overridden by the inline value.
 *   login_bg paints the page BEHIND the card. The login mark thus sits on
 *   white-to-#eef0f2 — relative luminance 1.00 down to 0.86 — in every
 *   configuration, so login_bg's luminance is an INVALID input here and
 *   automatic must ignore it, including (in fact especially) when it IS set.
 *
 *   NAV — #logo is in the header strip, which rail_hex does not reach. It sits
 *   in main.tpl.htm:51 inside #inner-wrapper, a sibling of #headerbar, while
 *   #main-navigation is a separate band injected into #topnav-container at
 *   main.tpl.htm:102 and pushed clear of the strip by ispconfig.css:112-113
 *   (`#main-navigation { margin-top: 24px; }`). The rail block above selects
 *   only `#main-navigation a…`, `#main-navigation .icon` and `.pushy…` — never
 *   #logo, an ancestor of it, or body — and #logo is not inside .pushy either
 *   (that is the off-canvas drawer at main.tpl.htm:40). Nothing else paints the
 *   strip: ispconfig.css:75-80 has #logo's own background COMMENTED OUT, and no
 *   stock sheet gives #container, #main-wrapper, #inner-wrapper or
 *   #topnav-container a background at all (grepped ispconfig.css, responsive*,
 *   pushy*, login.css and themes/default/theme*.css). The only paint under the
 *   header strip is themes/default/theme.css:4-6 `body { background: #f2f5f7 }`,
 *   which this file replaces in the LOGIN scene only. So rail_hex's luminance is
 *   an invalid input for this surface too, and #logo's backdrop is stock's
 *   #F2F5F7 whatever the operator sets — the same value the customizer already
 *   uses as its on_light preview swatch (lib/preview.inc.php).
 *
 * Ignoring the hexes narrows nothing, because the operator's explicit
 * logo_variant_nav / logo_variant_login is checked FIRST and wins outright: a
 * panel whose login card has been restyled dark by a third-party module still
 * has a full escape hatch, it just has to be told rather than guessed at.
 *
 * ---- the $core_logo guard, and why it became per-surface -------------------
 * The uploaded LIGHT-background mark needs NO rule here in the ordinary case:
 * it lives in sys_ini.custom_logo, and core reads that column itself and inlines
 * it into the stock shell (index.php writes a data URI into #logo's style
 * attribute, and main_login.tpl.htm renders it as an <img>). Duplicating that
 * would be two code paths racing over one value. So the test below is not "is
 * there a logo_url" but "is the resolved logo something OTHER than the value
 * core is already painting" — which covers three cases with one rule:
 *   - a logo_url reference (core knows nothing about it), as before;
 *   - the dark-background mark arriving by fallback because the operator has
 *     only uploaded that one — core would otherwise paint sys_ini.default_logo,
 *     i.e. the stock ISPConfig logo, which is the single thing a white-label
 *     panel must never show;
 *   - both, in which case the reference wins as it always has.
 *
 * That test used to run against ONE surface-agnostic $logo_src computed before
 * the scene was consulted. Once two surfaces can resolve to DIFFERENT artwork
 * that is no longer one question, and asking it once was a live bug: with both
 * variants stored, logo_variant_login = 'on_dark' and nav left automatic, the
 * global $logo_src resolved to the on_light upload — which IS $core_logo — the
 * guard suppressed, and the LOGIN scene emitted nothing. The operator's
 * explicit choice silently did nothing while core kept painting the light mark.
 * $logo_src is now derived BELOW the scene decision from that surface's own
 * preference, so the comparison suppresses exactly the surface whose resolved
 * image is byte-identical to core's and leaves the other free to emit.
 *
 * ONE $core_logo is still correct: it can only ever be the on_light upload
 * (core has no idea the dark-background variant exists), but core paints that
 * same single value on BOTH shells, so the right-hand side never varies by
 * surface — only the left-hand side does. Keep it a BYTE comparison and do not
 * "simplify" it to "the resolved variant is on_light": a logo_url reference is
 * a URL string and can never equal a data URI, so it must always emit even
 * though it is the light-background variant; and $core_logo is '' whenever
 * custom_logo is empty or unparseable, which is what forces an emit and stops
 * core's default_logo from reaching a white-labelled panel.
 *
 * The cross-variant fallback is untouched: a preference says which variant to
 * ASK for, never which to require, so a preference for a slot the operator has
 * not filled still renders the other mark. The single-logo panel — which is
 * every panel in the field — sees no change from any of this.
 *
 * Overriding core costs !important: core's logo is an INLINE style attribute,
 * which outranks every declaration in an external sheet no matter how specific.
 * The declarations stay on the image itself — the slot's width/height remain
 * core's. Those dimensions are computed from whatever core itself is holding
 * (custom_logo, else default_logo's 200x65, which is also #logo's fixed size in
 * ispconfig.css), so they are the right box to fit into and background-size:
 * contain does the fitting; second-guessing them here would move the header for
 * everyone.
 * =======================================================================*/
// Whether CORE already has a logo to render, and which value it is. An
// unparseable custom_logo counts as none: core would inline the broken value
// either way, so treating it as "no logo" lets the wordmark below replace it
// instead of stacking on top.
$core_logo = (preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $custom_logo)) ? $custom_logo : '';

$logo_on_light = brand_logo_variant(
    isset($branding['logo_url']) ? $branding['logo_url'] : '',
    $custom_logo
);
$logo_on_dark = brand_logo_variant(
    isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    isset($branding['logo_on_dark'])     ? $branding['logo_on_dark']     : ''
);

// Does the operator have ANY artwork? The wordmark branch keys off this rather
// than off "$logo_src came out empty". The two are equivalent only while the
// cross-variant fallback below survives, and that is too quiet a coupling to
// rely on: drop the fallback and a preference for an unfilled slot would leave
// $logo_src empty, fall into the wordmark branch, and emit
// `#logo { background-image: none !important; }` — actively ERASING a logo the
// operator does have. Stating the real condition makes that edit fail loudly.
$has_logo = ($logo_on_light !== '' || $logo_on_dark !== '');

// Which of classic's two logo slots this response is painting. The scene is the
// surface here: ?scene=login reaches the login shell alone (install.sh:384), so
// nothing after this point has to consider the other slot.
$surface = ($scene === 'login') ? 'login' : 'nav';

// '' for the background hex on both surfaces, and 'on_light' as the design
// default — the determination and its evidence are in the block comment above.
//
// Both key names are spelled out here rather than composed from $surface. CI's
// brand-token contract check greps every themes/*/brand.php for them so a rename
// on the writer side cannot pass unnoticed, and a composed lookup would leave it
// matching nothing but prose — green while this design quietly stopped honouring
// the operator.
$stored_pref = ($surface === 'login')
    ? (isset($branding['logo_variant_login']) ? $branding['logo_variant_login'] : '')
    : (isset($branding['logo_variant_nav'])   ? $branding['logo_variant_nav']   : '');
$logo_pref = brand_logo_variant_pref($stored_pref, '', 'on_light');

// Ask for the preferred variant, fall back to the other. The fallback is what
// keeps a preference honest: it expresses which mark suits this surface, not a
// demand that the operator upload two.
$logo_want  = ($logo_pref === 'on_dark') ? $logo_on_dark  : $logo_on_light;
$logo_other = ($logo_pref === 'on_dark') ? $logo_on_light : $logo_on_dark;
$logo_src   = ($logo_want !== '') ? $logo_want : $logo_other;

if ($logo_src !== '' && $logo_src !== $core_logo) {
    if ($scene === 'login') {
        // The login shell renders the logo as a plain <img> in .panel-heading
        // (main_login.tpl.htm). content: swaps the rendered image; auto sizing
        // with a max box keeps any aspect ratio instead of squashing it. This
        // selector is emitted in the login scene ONLY — .panel-heading img would
        // otherwise match any Bootstrap panel in the app frame.
        $css .= ".panel-heading img { content: url(\"{$logo_src}\"); height: auto; width: auto; "
              . "max-height: 64px; max-width: 100%; }\n";
    } else {
        $css .= "#logo { background-image: url(\"{$logo_src}\") !important; background-repeat: no-repeat !important; "
              . "background-position: left center !important; background-size: contain !important; }\n";
    }
} elseif (!$has_logo && $company_name !== '') {
    // No logo of either kind, but the panel is named: the NAME becomes the
    // wordmark. Without this the stock ISPConfig logo keeps rendering — core
    // falls back to sys_ini.default_logo — which is the one thing a white-label
    // panel must not show.
    //
    // Escape for the CSS string context rather than deleting: inside content:"…"
    // only " (which would close the string) and \ (which would start an escape
    // sequence and swallow the next character) have any meaning, and both have a
    // lossless CSS escape. CR/LF — the one thing with no escape worth using —
    // were already removed on read. Order matters: backslashes are doubled first,
    // so the backslash this adds in front of a quote is not itself doubled.
    // < and > are deliberately left untouched: this file is only ever fetched
    // through <link rel='stylesheet'>, never inlined into a <style> block, so
    // angle brackets have no HTML context to break out of — and deleting them
    // silently rewrote legitimate panel names ("Host > Cloud" lost its arrow,
    // '"Acme" Hosting' lost its quotes) while title.php's tab title on the same
    // screen rendered them intact.
    $wordmark = str_replace(array('\\', '"'), array('\\\\', '\\"'), $company_name);
    $ink      = ($accent !== '') ? brand_readable($accent, 42, $page_bg, 4.5) : '#3C444B';
    if ($scene === 'login') {
        $css .= ".panel-heading img { content: \"{$wordmark}\"; font-size: 20px; font-weight: 700; "
              . "letter-spacing: 0.01em; color: {$ink}; }\n";
    } else {
        // #logo is a fixed 200x65 float carrying the logo as a background image
        // (ispconfig.css) with an empty <a> stretched over it. Blank the image,
        // let the float shrink-to-fit so a long name pushes the header instead of
        // overflowing into #headerbar, and hang the text off the anchor so the
        // whole slot stays clickable exactly as before.
        $css .= "#logo { background-image: none !important; width: auto !important; }\n";
        $css .= "#logo a { display: flex; align-items: center; width: auto; height: 100%; text-decoration: none; }\n";
        $css .= "#logo a::after { content: \"{$wordmark}\"; font-size: 20px; font-weight: 700; "
              . "letter-spacing: 0.01em; white-space: nowrap; color: {$ink}; }\n";
    }
}

/* =========================================================================
 * login scene
 * =======================================================================*/
if ($scene === 'login') {
    if ($login_bg !== '') {
        // Stock paints the login page with the same body background as the app
        // (#f2f5f7, theme.min.css). The login card stays white on top, so any
        // background colour keeps a readable form.
        $css .= "body { background: {$login_bg}; }\n";
    }
    if ($accent !== '') {
        // The one primary action on this screen. Stock ships it as the neutral
        // .formbutton-default grey — fine beside a grey page, adrift on a branded
        // one. Scoped to input[type=submit] so the neighbouring "password lost"
        // <button> and every other neutral button in the panel stay stock.
        // Same treatment as the Save button: the accent as entered, stock's
        // offsets around it.
        $sub_ink = brand_ink($accent);
        $css .= "input[type='submit'].formbutton-default { background: {$accent}; "
              . 'border-color: ' . brand_shade($accent, $accent_l - 9.5) . '; '
              . 'border-bottom-color: ' . brand_shade($accent, $accent_l - 9.5) . "; color: {$sub_ink}; }\n";
        $css .= "input[type='submit'].formbutton-default:hover { background: "
              . brand_shade($accent, $accent_l - 6) . '; '
              . 'border-bottom-color: ' . brand_shade($accent, $accent_l - 14.5) . "; color: {$sub_ink}; }\n";
    }
}

/* =========================================================================
 * software version visibility (demo / white-label mode)
 * =======================================================================*/
// [branding] show_version = 0 hides Help's version surfaces for EVERY user,
// including the operator (CSS cannot see roles): the sidebar "About ISPConfig"
// section with its Version item, and the version line itself — p.frmTextHead is
// used by help/version.php alone across the whole 3.3.1p1 interface. Both
// surfaces are rendered from CORE templates (sidenav.tpl.htm, help/version.php),
// which is why the selectors are identical to Clarity's.
if (isset($branding['show_version']) && $branding['show_version'] === '0') {
    $css .= "#sidebar li#help_version, #sidebar ul:has(> li#help_version), "
          . "#sidebar header:has(+ ul > li#help_version) { display: none; }\n";
    $css .= "#pageContent p.frmTextHead { display: none; }\n";
}

/* =========================================================================
 * footer courtesy lines
 * =======================================================================*/
// Stock renders the whole footer as bare text inside <footer id='footer'>, so
// CSS alone could only hide all of it or none of it. install.sh therefore splits
// it while generating this design's shell: core's own line goes into
// .nzc-credit-ispconfig and ours is appended as .nzc-credit-theme, giving each
// toggle a target. If a future ISPConfig moves that markup the spans are absent,
// these rules match nothing, and the credits simply stay visible — the failure
// mode is "attribution shown", which is the right way round.
//
// Both default to ON: only an explicit '0' hides anything. These are courtesy
// lines, NOT licence notices — no toggle here touches a licence, and the MIT and
// BSD-3-Clause texts ship regardless of what is switched off.
$hide_ispc  = isset($branding['show_ispconfig_credit']) && $branding['show_ispconfig_credit'] === '0';
$hide_theme = isset($branding['show_theme_credit'])     && $branding['show_theme_credit']     === '0';

// The separator lives INSIDE .nzc-credit-theme (install.sh nests it there), so
// hiding ours takes the separator with it by containment — no rule needed. The
// case that actually dangles is the opposite one: hide core's line, keep ours,
// and the footer opens with a stray "·". So the separator is hidden alongside
// .nzc-credit-ispconfig, which is also what clarity's reader does.
if ($hide_ispc)  { $css .= ".nzc-credit-ispconfig, .nzc-credit-sep { display: none; }\n"; }
if ($hide_theme) { $css .= ".nzc-credit-theme { display: none; }\n"; }
// With both gone the bar is an empty strip of padding, so remove it entirely
// rather than leave a gap the operator has to wonder about.
if ($hide_ispc && $hide_theme) { $css .= "#footer { display: none; }\n"; }

echo $css;

/* ============================================================
 * Helpers — pure, dependency-free.
 * The colour maths is shared verbatim with themes/clarity/brand.php (same
 * function names, same bodies) so the two readers cannot drift; only the
 * contrast helpers below are new, and only because classic paints on stock's
 * light surfaces where a mis-hued text colour would actually be unreadable.
 * The two files are never loaded in the same request — one active theme per
 * request, one endpoint per theme — so the shared names cannot collide.
 * ============================================================ */

/**
 * Parse the whole sys_ini config blob with ISPConfig's own INI reader, so this
 * reader can never drift from the customizer's writer (which serialises with the
 * same framework class). ini_parser.inc.php is a pure, dependency-free class —
 * safe to require on this pre-auth path. Falls back to '' if it's ever missing.
 *
 * stripslashes() before parsing is mandatory, not a nicety: the write path
 * escapes the field on its way in (tform_base::_encode() runs db->quote() over
 * every value before the blob is serialised) and ini_parser unescapes nothing,
 * so core ALWAYS unquotes on read — app.inc.php:108, getconf.inc.php:54,
 * server_config_edit.php:190. Using getconf::get_global_config() instead would
 * be worse than doing it here: it applies the same stripslashes with no
 * counterpart on write, and it drags the app bootstrap onto a pre-auth route.
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
 * Byte-identical to themes/clarity/brand.php's copy, and to the two patterns in
 * the customizer's lib/preview.inc.php. The duplication is deliberate: this is a
 * pre-authentication endpoint that must keep working with the customizer
 * uninstalled, so it cannot include the module's code, and the two designs are
 * never loaded in the same request. All copies must be changed together.
 *
 * (?!/) rejects protocol-relative "//host/..." — a REMOTE url in disguise, which
 * would defeat the local-path privacy contract; writer-side validation matches.
 *
 * The D modifier is load-bearing, not decoration: without it PCRE's `$` also
 * matches BEFORE a final newline, so "/img/logo.png\n" would validate and the
 * raw LF would be emitted inside url("...") — a literal newline terminates a
 * double-quoted CSS string, breaking this sheet for every visitor including
 * pre-auth on the login screen. The writer-side validator in the module's tform
 * carries /D for the same reason.
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
 * Which logo VARIANT one surface should ask for: 'on_light' (the mark drawn FOR
 * light backgrounds) or 'on_dark' (the mark drawn FOR dark ones).
 *
 * $stored   the operator's [branding] logo_variant_nav / logo_variant_login
 *           value, already read by the caller.
 * $bg_hex   the colour that slot's background actually ends up, or '' when the
 *           design cannot know it. Caller-supplied on purpose: which colour a
 *           logo sits on is a property of the DESIGN, not of this feature.
 *           Clarity passes rail_hex / login_bg; classic passes '' for both.
 * $default  the variant to use when nothing else decides.
 *
 * The operator's explicit choice is checked FIRST and is absolute. Automatic is
 * a convenience, not a policy: someone who has looked at their own panel and
 * disagreed with the guess has to win, or the feature is just a second
 * hardcoded assumption wearing a select box.
 *
 * Anything that is not one of the two literals means automatic, which folds
 * three cases into one test: the key absent (never saved), the key present and
 * EMPTY (saved once while on automatic — the writer emits the line
 * unconditionally, so ini_parser::parse_ini_string returns '' rather than
 * nothing, and absent/empty must behave alike), and a value hand-edited into
 * the blob that this build does not recognise.
 *
 * The result is only ever compared with ===; it is never interpolated into CSS,
 * which is what keeps this field out of the escaping contract that governs
 * every colour and URL in this file.
 *
 * Byte-identical to themes/clarity/brand.php's copy and to the customizer's
 * lib/preview.inc.php, for the same reason brand_logo_variant() is: a pre-auth
 * reader cannot include the module's code, so the copies are duplicated and
 * must be changed together. tests/brand/run.php diffs the three copies'
 * decisions over a shared grid on every push, so that claim is executed rather
 * than left to an eye.
 *
 * It has not always been true. This copy took ($surface, $branding, …) and
 * composed the key itself while the docblock above said it was byte-identical to
 * clarity's ($stored, …) — so a maintainer who believed the instruction and
 * pasted clarity's body in would have handed $stored the string 'nav', which
 * never matches either literal, and every explicit preference on this design
 * would have fallen through to the default in silence. Taking the value rather
 * than the key is also what puts both key names LITERALLY at the call site,
 * which is what CI's brand-token contract check needs in order to catch a
 * rename: a composed lookup is invisible to grep.
 */
function brand_logo_variant_pref($stored, $bg_hex, $default)
{
    if ($stored === 'on_light' || $stored === 'on_dark') {
        return $stored;
    }
    if (is_string($bg_hex) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $bg_hex) === 1) {
        return brand_is_dark($bg_hex) ? 'on_dark' : 'on_light';
    }
    return $default;
}

/**
 * Is $hex a dark background — i.e. does the mark drawn for DARK backgrounds
 * belong on it?
 *
 * WCAG 2.x relative luminance, not HSL lightness. Lightness is a hue-blind
 * midpoint: #0000FF and #FFFF00 both sit at lightness 50 while the yellow is
 * about thirteen times as bright, so choosing artwork by lightness would put
 * the white mark on the yellow. Each channel is normalised to 0..1,
 * gamma-expanded out of sRGB, then weighted 0.2126 / 0.7152 / 0.0722.
 *
 * The 0.5 threshold is deliberately NOT the 0.184 pivot brand_readable() uses.
 * That one picks INK for a surface and has to respect a contrast ratio; this
 * one picks between two finished artworks whose internal contrast is the
 * operator's business, so the question is the plain one — is this background
 * more dark than light.
 *
 * A malformed or empty hex returns FALSE (treat it as light) instead of
 * warning. This is a pre-auth endpoint that emits text/css: a PHP notice would
 * be printed INTO the stylesheet and corrupt every rule after it. Callers
 * validate before calling, so the guard is defence in depth, and "light" is the
 * safer guess — the light-background mark is the one every panel has, because
 * it is the variant core itself stores in sys_ini.custom_logo.
 *
 * Byte-identical to themes/clarity/brand.php's copy and to the customizer's
 * lib/preview.inc.php; all copies change together, and tests/brand/run.php
 * diffs their decisions on every push.
 *
 * The sRGB arithmetic is called, not repeated. This function used to carry its
 * own copy of the transfer function — the same loop, the same weights, the same
 * knee — eighty lines from brand_luminance(), which already had it and which
 * brand_readable() already used. Two copies in ONE file is the drift that has
 * nothing to catch it: a contrast fix applied to the gamma curve in one leaves
 * the other behind, and the two then disagree about the same colour on the same
 * page. Cross-FILE parity never required that; it requires the guard, the
 * delegation and the 0.5 constant to match, which they do.
 */
function brand_is_dark($hex)
{
    if (!is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $hex) !== 1) {
        return false;
    }
    return (brand_luminance($hex) < 0.5);
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

/**
 * A shade of $hex, starting at lightness $l and walking AWAY from $bg until the
 * pair clears $min contrast (WCAG 2.x ratio: 4.5 for body text, 3.0 for a
 * non-text indicator such as a border or an underline).
 *
 * This is what makes an arbitrary brand colour safe on stock's light surfaces.
 * Stock's lightness ladder was chosen for a blue at hue ~208; the same rung in
 * yellow or lime is far brighter, and hue-swapping alone would produce text
 * nobody can read. Walking in 2-point steps keeps the result as close to the
 * operator's chosen colour as the ratio allows. Black on white is ~21:1, so the
 * walk always terminates well before the clamp.
 */
function brand_readable($hex, $l, $bg, $min)
{
    //* Walk toward whichever end of the ramp actually reads better on $bg,
    //* measured rather than pivoted: the crossover is at luminance 0.1791, and a
    //* rounded 0.184 pivot sends colours in between — #767676 is one — walking
    //* the wrong way, away from contrast instead of toward it.
    $dir  = (brand_contrast('#FFFFFF', $bg) > brand_contrast('#000000', $bg)) ? 1 : -1;
    $last = brand_shade($hex, $l);
    for ($i = 0; $i <= 50; $i++) {
        $try_l = $l + ($dir * 2 * $i);
        $last  = brand_shade($hex, $try_l);
        if (brand_contrast($last, $bg) >= $min) {
            return $last;
        }
        //* Stop at the end of the ramp IN THE DIRECTION OF TRAVEL. Testing both
        //* ends would end the walk on its first step whenever $l starts at a
        //* boundary, returning the colour it was asked to walk away from.
        if (($dir < 0 && $try_l <= 0) || ($dir > 0 && $try_l >= 100)) {
            break;
        }
    }
    return $last;
}

/**
 * The ink to print on $hex: white, or stock's own body ink (#3c444b from
 * theme.min.css) — whichever reads better. Choosing by contrast rather than by
 * lightness is what keeps a saturated yellow or lime brand button legible.
 */
function brand_ink($hex)
{
    return (brand_contrast($hex, '#FFFFFF') >= brand_contrast($hex, '#3C444B')) ? '#FFFFFF' : '#3C444B';
}

/** WCAG 2.x contrast ratio between two hex colours (1.0 .. 21.0). */
function brand_contrast($a, $b)
{
    $la = brand_luminance($a);
    $lb = brand_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/** WCAG relative luminance of a hex colour (0.0 .. 1.0). */
function brand_luminance($hex)
{
    $c = ltrim($hex, '#');
    $w = array(0.2126, 0.7152, 0.0722);
    $y = 0.0;
    for ($i = 0; $i < 3; $i++) {
        $v = hexdec(substr($c, $i * 2, 2)) / 255;
        $v = ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
        $y += $w[$i] * $v;
    }
    return $y;
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
