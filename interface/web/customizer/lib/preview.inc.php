<?php
/**
 * ispconfig-customizer — the two-logo model: slot vocabulary, source resolution
 * and the preview renderer.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../../LICENSE.
 *
 * Required by customizer_edit.php, logo_upload.php and logo_delete.php. Those
 * three must agree about which slots exist, where each one is stored and which
 * one a given design renders, so all of that lives here and nowhere else.
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
 */
function customizer_logo_slots() {
    return array('on_light' => true, 'on_dark' => true);
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
