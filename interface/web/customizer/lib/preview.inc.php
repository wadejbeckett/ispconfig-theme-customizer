<?php
/**
 * ispconfig-customizer — the brand-image model: slot vocabulary, source
 * resolution and the preview renderers.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../../LICENSE.
 *
 * Required by customizer_edit.php, logo_upload.php and logo_delete.php. Those
 * three must agree about which slots exist, where each one is stored and which
 * one a given design renders, so all of that lives here and nowhere else.
 *
 * Three slots: the two logo variants below, and the FAVICON (see the block near
 * the bottom of this file). All three share one upload endpoint, one delete
 * endpoint, one CSRF flow and one slot allowlist — only the screening rules and
 * the storage location differ per slot.
 *
 * ---- WHY THERE ARE TWO LOGOS -------------------------------------------
 * One panel runs designs whose headers have OPPOSITE brightness: clarity's rail
 * is navy and needs a light mark, classic's header is stock's #F2F5F7 and needs
 * a dark one. A single stored logo cannot serve both — a white wordmark
 * (measured at mean luma 0.845) is very nearly invisible on classic's header,
 * which is the bug this model exists to fix.
 *
 * The variants are therefore named by the BACKGROUND they sit on, not by the
 * colour of the artwork, because the background is what the operator can see
 * and reason about. "Which of my designs has a dark header?" is answerable by
 * looking at the panel; "is this artwork light enough?" is not.
 *
 *   on_light   the mark for LIGHT backgrounds (so: dark or full-colour artwork)
 *                reference : [branding] logo_url          (config)
 *                upload    : sys_ini.custom_logo          (core's own column)
 *   on_dark    the mark for DARK backgrounds (so: light or white artwork)
 *                reference : [branding] logo_url_on_dark  (config)
 *                upload    : [branding] logo_on_dark      (config, data URI)
 *
 * ---- WHY THOSE STORES ---------------------------------------------------
 * custom_logo is CORE's column and core itself renders it — on the stock theme's
 * header (index.php writes it into #logo's inline style) and on the stock login
 * page (main_login.tpl.htm renders it as an <img>). Both are light surfaces.
 * Assigning the light-background variant to that column is therefore not a
 * convenience, it is the only assignment that leaves core correct with no extra
 * work; and it is written by a direct UPDATE, so it never enters sys_datalog.
 *
 * The dark-background upload has no core column and we may not add one (no
 * schema changes, ever), so it rides in sys_ini.config as a data URI. That costs
 * something real, stated here so nobody has to rediscover it:
 *   - sys_ini.config is read and INI-parsed by getconf::get_global_config() on
 *     every page that asks for any global setting, so the blob is now carried
 *     and parsed with up to ~60 KB of base64 in it;
 *   - the Branding form writes that blob through db::datalogUpdate(), so ONE
 *     save of the form journals the whole thing into sys_datalog. The uploads
 *     themselves do not — they use a direct UPDATE, like the column write — but
 *     the next form save does.
 * The column is `longtext` (install/sql/ispconfig3.sql), so nothing truncates;
 * the existing 45 KB raw upload cap is what keeps the number bounded. An
 * operator who minds the cost has logo_url_on_dark, which stores a path.
 *
 * ---- SELECTION AND FALLBACK --------------------------------------------
 * Mirrored EXACTLY by themes/clarity/brand.php and themes/classic/brand.php.
 * The readers cannot include this file — they are pre-auth endpoints that must
 * keep working with this module uninstalled — so the rule is duplicated there
 * deliberately, and all three copies must be changed together.
 *
 *   1. Within a variant, a valid reference beats an uploaded data URI. This is
 *      exactly the precedence logo_url has always had over custom_logo.
 *   2. Each design asks for the variant matching its own background, and if
 *      that variant is empty it falls back to the other one.
 *
 * Rule 2 is what makes this change strictly non-breaking. Every panel in the
 * field today has exactly one logo, in custom_logo (= on_light), and until a
 * second variant is uploaded every design still resolves to it and renders it
 * exactly as it does now.
 *
 * ---- WHY THE PREVIEW SHOWS BOTH ----------------------------------------
 * This page is the only place a wrong upload can be caught before a customer
 * sees it, so each variant is drawn on a swatch of the background it is FOR:
 * the light row on stock's own header grey, the dark row on clarity's rail
 * navy. A white mark dropped into the light slot then looks wrong here
 * immediately, which is the whole point. The preview also resolves through the
 * same fallback the themes use, so it shows what the panel will actually
 * render — never merely what is stored in one slot.
 */

/**
 * The slot identifiers the upload/delete endpoints accept.
 *
 * Returned as a MAP so callers test with isset() rather than in_array(): the
 * slot selects a storage location (a core column vs. a config key), so it is
 * exactly the kind of value that must never be taken raw from a request.
 *
 * 'favicon' is the third brand image and rides the same machinery deliberately:
 * one allowlist, one CSRF flow, one SVG screen, one delete path. The function
 * keeps its name even though it now also names a non-logo slot — it IS the slot
 * allowlist, its two callers read it as "which brand image does this request
 * target", and renaming it would churn a contract shared with logo_delete.php
 * for no behavioural gain.
 */
function customizer_logo_slots() {
    return array('on_light' => true, 'on_dark' => true, 'favicon' => true);
}

/**
 * Which sys_ini.config key a slot's UPLOAD is stored under, or '' when the slot
 * is stored somewhere else (on_light lives in core's own sys_ini.custom_logo
 * column). Shared by logo_upload.php and logo_delete.php so the write path and
 * the delete path can never disagree about where a slot's bytes live.
 */
function customizer_slot_config_key($slot) {
    $keys = array('on_dark' => 'logo_on_dark', 'favicon' => 'favicon');
    return isset($keys[$slot]) ? $keys[$slot] : '';
}

/**
 * Is $url a logo reference we are willing to emit?
 *
 * The same anchored pattern the readers and the tform validator use — a
 * root-relative path or an https URL, with (?!/) rejecting protocol-relative
 * "//host" (a remote URL in disguise). Reader/writer/preview parity is the
 * point: the copies must agree.
 *
 * /D matters: without it PCRE's `$` also matches before a final newline, so
 * "/img/logo.png\n" would validate here while the themes' readers (which do
 * carry /D) reject it — the preview would then show an image the panel does not
 * render, the exact class of contradiction this file exists to remove.
 */
function customizer_logo_ref_ok($url) {
    return ($url !== '' && preg_match('#^(https://[^\s"\'<>()\\\\]+|/(?!/)[^\s"\'<>()\\\\]+)$#D', $url) === 1);
}

/**
 * Is $uri a real image data-URI? Defence-in-depth against a tampered column or
 * config key: the value is emitted into an <img src> here and into a CSS
 * url("…") in the themes, and this pattern admits no character that could break
 * out of either context.
 */
function customizer_logo_data_ok($uri) {
    return ($uri !== '' && preg_match('#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i', $uri) === 1);
}

/**
 * Resolve both variants from the four stored values, applying rule 1 (reference
 * beats upload, within a variant) and then rule 2 (fall back to the other
 * variant) above.
 *
 * $stored keys, all optional: custom_logo, logo_url, logo_on_dark,
 * logo_url_on_dark.
 *
 * Returns, for each of 'on_light' and 'on_dark':
 *   'src'  the value the panel will render, or '' if there is no logo at all
 *   'from' which variant that value came from ('on_light' / 'on_dark' / '')
 * 'from' differing from the key it is stored under is precisely the fallback
 * case, and is what lets the caller tell the operator it happened.
 */
function customizer_logo_resolve($stored) {
    if(!is_array($stored)) $stored = array();

    //* variant => array(reference key, uploaded-data-URI key)
    $slot_keys = array(
        'on_light' => array('logo_url',         'custom_logo'),
        'on_dark'  => array('logo_url_on_dark', 'logo_on_dark'),
    );

    $own = array();
    foreach($slot_keys as $variant => $keys) {
        $ref  = (isset($stored[$keys[0]]) && is_string($stored[$keys[0]])) ? $stored[$keys[0]] : '';
        $data = (isset($stored[$keys[1]]) && is_string($stored[$keys[1]])) ? $stored[$keys[1]] : '';
        if(customizer_logo_ref_ok($ref))         $own[$variant] = $ref;
        elseif(customizer_logo_data_ok($data))   $own[$variant] = $data;
        else                                     $own[$variant] = '';
    }

    $out = array();
    foreach(array('on_light' => 'on_dark', 'on_dark' => 'on_light') as $want => $other) {
        if($own[$want] !== '')       $out[$want] = array('src' => $own[$want],  'from' => $want);
        elseif($own[$other] !== '')  $out[$want] = array('src' => $own[$other], 'from' => $other);
        else                         $out[$want] = array('src' => '',           'from' => '');
    }
    return $out;
}

/**
 * One preview thumbnail, drawn on the background its variant is meant for.
 *
 * $resolved      one entry of customizer_logo_resolve()
 * $want          'on_light' or 'on_dark' — which row this is
 * $no_logo_text  already-localised text for "nothing set anywhere"
 * $fallback_text already-localised note, shown only when this row is displaying
 *                the OTHER variant; pass '' to suppress it
 */
function customizer_logo_preview_html($resolved, $want, $no_logo_text, $fallback_text = '') {
    $src  = (is_array($resolved) && isset($resolved['src']))  ? (string)$resolved['src']  : '';
    $from = (is_array($resolved) && isset($resolved['from'])) ? (string)$resolved['from'] : '';

    if($src === '') return '<em>' . $no_logo_text . '</em>';

    //* The swatch IS the feature. #F2F5F7 is stock's own page/header grey
    //* (theme.min.css) and #01243D is clarity's rail navy, so each row shows the
    //* mark against the surface it will really sit on. The 1px edge keeps the
    //* light swatch visible against the (also light) form background — without
    //* it a white logo on light grey reads as an empty box rather than as a
    //* logo that has disappeared, and those two look identical for the wrong
    //* reason.
    $bg   = ($want === 'on_dark') ? '#01243D' : '#F2F5F7';
    $edge = ($want === 'on_dark') ? '#01243D' : '#D5DDE3';

    //* htmlspecialchars is a no-op on a valid data URI — the base64 alphabet and
    //* the "data:image/…;base64," prefix contain none of & < > " ' — so escaping
    //* unconditionally costs nothing and removes the need for the reader of this
    //* line to know which of the two kinds of value it is holding.
    $html = '<img src="' . htmlspecialchars($src, ENT_QUOTES) . '" alt="" style="max-height:48px;max-width:220px;'
          . 'background:' . $bg . ';padding:6px 12px;border-radius:4px;border:1px solid ' . $edge . '" />';

    //* Say so when this row is borrowing the other variant, otherwise the two
    //* rows show the same mark twice and look like a bug rather than like the
    //* documented fallback.
    if($fallback_text !== '' && $from !== '' && $from !== $want) {
        $html .= '<p class="help-block">' . $fallback_text . '</p>';
    }
    return $html;
}

/* ---- THE FAVICON ---------------------------------------------------------
 *
 * The third brand image, and the one an operator notices last and complains
 * about first: every design ships its own icon set and the shells hardcoded
 * links to it, so a white-labelled panel still showed whoever's mark the design
 * happened to ship, on every tab, bookmark and history entry.
 *
 *   reference : [branding] favicon_url   (a root-relative path or an https URL)
 *   upload    : [branding] favicon       (a data URI)
 *
 * ONE precedence rule for the whole extension: the reference beats the upload,
 * exactly as logo_url beats custom_logo and logo_url_on_dark beats
 * logo_on_dark. There is deliberately no cross-slot fallback here — the logo
 * has two variants that stand in for each other, the favicon has one, and
 * "falls back to the logo" would print a 500px wordmark into a 16px box.
 *
 * WHY AN ENDPOINT, not CSS: brand.php emits a stylesheet and a favicon is a
 * <link>, not a style. Swapping it from JavaScript would flicker on every load
 * and would not work at all with JS disabled — on a pre-auth page. So each
 * design serves themes/<design>/favicon.php, and the shells link THAT.
 *
 * Both stores are config keys because core has no favicon column and this
 * extension adds no schema; the size cap (15 KB raw, well under the logo's
 * 45 KB) is what keeps the config blob from growing a second image-sized value.
 * An operator who minds that cost has favicon_url, which stores a path.
 *
 * The resolution + validation below is MIRRORED by themes/clarity/favicon.php
 * and themes/classic/favicon.php, which cannot include this file (they are
 * pre-auth endpoints that must keep working with this module uninstalled). All
 * three copies change together — same rule as the two brand.php readers.
 */

/**
 * Is $uri a favicon data URI we are willing to store, preview and re-serve?
 *
 * Narrower than customizer_logo_data_ok() on purpose: a favicon is decoded and
 * streamed back by favicon.php with a Content-Type derived from this very
 * string, so the type is an ALLOWLIST of the three formats the uploader accepts
 * rather than "any image/*". Anything else falls back to the design's shipped
 * icon at render time, which is the documented behaviour for an invalid value.
 *
 * image/vnd.microsoft.icon is accepted alongside image/x-icon even though the
 * uploader normalises to the latter: the two are the same format under two
 * spellings, and a value written by an older build (or by hand) should render
 * rather than be silently ignored.
 *
 * /D for the same reason as everywhere else in this file — without it PCRE's $
 * also matches before a trailing newline.
 */
function customizer_favicon_data_ok($uri) {
    return ($uri !== '' && preg_match(
        '#^data:image/(?:svg\+xml|png|x-icon|vnd\.microsoft\.icon);base64,[A-Za-z0-9+/=]+$#D',
        $uri) === 1);
}

/**
 * Resolve the favicon from the two stored values, applying the one precedence
 * rule (reference beats upload).
 *
 * $stored keys, both optional: favicon_url, favicon.
 *
 * Returns:
 *   'src'    what the panel will render, or '' when nothing valid is stored
 *   'kind'   which field it came from ('url' / 'data' / '')
 *   'masked' true when the reference is winning over a stored upload
 *
 * 'masked' is the case worth reporting: an operator who has a path set and then
 * uploads a file gets "Favicon updated." over a preview of the OTHER icon,
 * because the precedence rule quietly applied. The single most confusing thing
 * about a precedence rule is not being told it took effect.
 */
function customizer_favicon_resolve($stored) {
    if(!is_array($stored)) $stored = array();
    $ref  = (isset($stored['favicon_url']) && is_string($stored['favicon_url'])) ? $stored['favicon_url'] : '';
    $data = (isset($stored['favicon'])     && is_string($stored['favicon']))     ? $stored['favicon']     : '';

    $data_ok = customizer_favicon_data_ok($data);

    //* The SAME reference validator the logos use — one allowlist regex for the
    //* whole module, carrying /D, mirrored by every reader.
    if(customizer_logo_ref_ok($ref)) return array('src' => $ref,  'kind' => 'url',  'masked' => $data_ok);
    if($data_ok)                     return array('src' => $data, 'kind' => 'data', 'masked' => false);
    return array('src' => '', 'kind' => '', 'masked' => false);
}

/**
 * The favicon preview, drawn at the sizes a browser actually paints.
 *
 * This is the whole point of previewing a favicon: the failure mode is not "the
 * wrong image", it is a perfectly good logo that turns to mud at 16px. So the
 * icon is rendered at 16px and 32px — no scaling up to a comfortable preview
 * size, which would hide exactly the defect the operator needs to see — and on
 * BOTH a light and a dark swatch, because a tab strip is light or dark
 * depending on the browser's own theme and a mark that only survives one of
 * them is a real defect too.
 *
 * $resolved        one customizer_favicon_resolve() result
 * $no_favicon_text already-localised text for "nothing set"
 * $masked_text     already-localised note, shown only when the path override is
 *                  hiding an uploaded icon; pass '' to suppress it
 */
function customizer_favicon_preview_html($resolved, $no_favicon_text, $masked_text = '') {
    $src = (is_array($resolved) && isset($resolved['src'])) ? (string)$resolved['src'] : '';
    if($src === '') return '<em>' . $no_favicon_text . '</em>';

    //* htmlspecialchars is a no-op on both value shapes (the base64 alphabet and
    //* the reference allowlist admit none of & < > " '), so escaping
    //* unconditionally costs nothing and removes the need for the reader of this
    //* line to know which of the two kinds of value it is holding.
    $esc  = htmlspecialchars($src, ENT_QUOTES);
    $html = '<span style="display:inline-flex;gap:12px;flex-wrap:wrap">';

    //* light strip first (stock's own page grey), then clarity's rail navy — the
    //* same two surfaces the logo rows use, so both previews on this page speak
    //* about backgrounds in the same vocabulary.
    $swatches = array(
        array('bg' => '#F2F5F7', 'edge' => '#D5DDE3', 'ink' => '#5A6B78'),
        array('bg' => '#01243D', 'edge' => '#01243D', 'ink' => '#93A9BA'),
    );
    foreach($swatches as $sw) {
        //* width/height attributes AND the CSS box: the attributes are what a
        //* browser with images still loading reserves, the CSS is what stops a
        //* stylesheet elsewhere on the page (or an SVG with no intrinsic size)
        //* from growing the icon past true size and defeating the preview.
        $html .= '<span style="background:' . $sw['bg'] . ';border:1px solid ' . $sw['edge']
               . ';border-radius:4px;padding:8px 12px;text-align:center;line-height:1">'
               . '<img src="' . $esc . '" alt="" width="16" height="16" style="width:16px;height:16px;vertical-align:bottom" />'
               . '<img src="' . $esc . '" alt="" width="32" height="32" style="width:32px;height:32px;margin-left:10px;vertical-align:bottom" />'
               . '<span style="display:block;margin-top:6px;font-size:11px;color:' . $sw['ink'] . '">16 &middot; 32 px</span>'
               . '</span>';
    }
    $html .= '</span>';

    //* Say so when the path override is what is being shown while an uploaded
    //* icon also exists — otherwise uploading a file appears to do nothing, and
    //* "Favicon updated." over an unchanged preview reads as a broken upload
    //* rather than as the documented precedence rule.
    if($masked_text !== '' && is_array($resolved)
        && isset($resolved['masked']) && $resolved['masked']) {
        $html .= '<p class="help-block">' . $masked_text . '</p>';
    }
    return $html;
}

/**
 * Is $data a real .ico file?
 *
 * Used by the uploader as a LAST resort, when finfo has no useful verdict for
 * an icon — libmagic's label for ICO varies across builds (image/x-icon,
 * image/vnd.microsoft.icon, and application/octet-stream on old or stripped
 * magic files), and "some operators only have a .ico" is precisely the case
 * this format is accepted for. Unlike SVG there is nothing executable to screen
 * here, so identifying the container structurally is identification, not a
 * weaker substitute for a security check: an ICO is decoded by the browser's
 * image pipeline and never as markup.
 *
 * The header is 6 bytes (reserved=0, type=1 for icons, image count) followed by
 * one 16-byte directory entry per image, each pointing at a byte range that
 * must lie inside the file and after the directory itself. Cursors (type 2)
 * are refused: same container, wrong thing to serve as an icon.
 */
function customizer_ico_ok($data) {
    $len = strlen($data);
    if($len < 22) return false;                      // 6-byte header + one 16-byte entry

    $head = @unpack('vreserved/vtype/vcount', substr($data, 0, 6));
    if(!is_array($head) || $head['reserved'] !== 0 || $head['type'] !== 1) return false;

    $count = $head['count'];
    //* An icon file with hundreds of images is not something a real generator
    //* emits; the bound also keeps the loop below trivially cheap.
    if($count < 1 || $count > 64) return false;
    $dir_end = 6 + $count * 16;
    if($len < $dir_end) return false;

    for($i = 0; $i < $count; $i++) {
        $e = @unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vbytes/Voffset', substr($data, 6 + $i * 16, 16));
        if(!is_array($e)) return false;
        if($e['bytes'] < 1 || $e['offset'] < $dir_end) return false;
        if($e['offset'] + $e['bytes'] > $len) return false;
    }
    return true;
}
