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
 * ---- WHICH SURFACE ASKS FOR WHICH VARIANT ------------------------------
 * Resolution above answers "what is stored"; this answers "which of the two a
 * given SURFACE wants". That used to be hardcoded per design — clarity asked
 * for on_dark because its rail is navy, classic for on_light because stock's
 * header is grey — and the operator's own [branding] rail_hex and login_bg
 * falsify exactly that assumption: a white rail_hex on clarity painted the
 * white mark onto a white rail, which is the bug this preference exists to fix.
 *
 * So the preference is stored per SURFACE rather than per design, and a third
 * design inherits the behaviour for free:
 *
 *   logo_variant_nav     [branding]  the rail / header / topbar slot
 *   logo_variant_login   [branding]  the login-screen slot
 *
 * Each holds '' (automatic — and an ABSENT key means the same thing), 'on_light'
 * or 'on_dark'. The vocabulary is the one this file already uses for the
 * variants themselves, deliberately: a surface asks for a variant BY NAME, so
 * there is nothing to translate between the two halves of the model.
 *
 * ---- WHY THE PREVIEW SHOWS BOTH ----------------------------------------
 * This page is the only place a wrong upload can be caught before a customer
 * sees it, so each variant is drawn on the background it will really sit on:
 * the operator's own rail_hex / login_bg wherever those colours reach the slot,
 * and the design's real constant otherwise (clarity's rail navy, stock's header
 * grey, the near-white gradient inside stock's login card). A white mark
 * dropped into the light slot then looks wrong here immediately, which is the
 * whole point. The preview resolves through the same fallback AND the same
 * surface preference the themes do, so it shows what the panel will actually
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
 * Is this background colour dark enough that the surface wants the mark made
 * FOR dark backgrounds (the light/white artwork)?
 *
 * Proper WCAG relative luminance: each channel to 0..1, the sRGB gamma
 * expansion, then the 0.2126/0.7152/0.0722 weighting. Dark below 0.5.
 *
 * MIRRORED by brand_is_dark() in themes/clarity/brand.php and in
 * themes/classic/brand.php (the brand_* prefix those files use; classic's older
 * brand_luminance() is this same arithmetic, line for line). They are pre-auth
 * endpoints that must keep working with this module uninstalled, so they cannot
 * include this file. The prefix may differ, the ARITHMETIC may not: a copy that
 * decides differently makes this page disagree with the panel about which mark
 * a surface gets, which is the single thing the preview exists to prevent. All
 * three copies change together.
 *
 * A malformed or empty value returns FALSE — documented, so nothing here has to
 * raise a warning out of substr()/hexdec() on a tampered config value. Every
 * caller validates the hex first (see customizer_logo_variant_for_surface), so
 * this is only reached by a copy that forgot to, and "not dark" is the safer
 * guess: every stock ISPConfig surface is light, and on_light is the variant
 * every panel in the field already has.
 *
 * 0.5 is NOT the contrast crossover — black ink beats white below L ~= 0.179 —
 * so a mid-tone in [0.179, 0.5), a #999999 rail say, is called dark and gets the
 * light mark where the dark one would read marginally better. That band is
 * precisely what the explicit on_light/on_dark setting is for. The constant is
 * fixed by the brand-token contract and must never be "improved" in one copy.
 */
function customizer_hex_is_dark($hex) {
    if(!is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $hex) !== 1) return false;
    return (customizer_luminance($hex) < 0.5);
}

/**
 * WCAG relative luminance of a validated #rrggbb (0.0 .. 1.0).
 *
 * Split out of customizer_hex_is_dark() so the sRGB transfer function is written
 * once per file rather than once per question — the theme readers carry the same
 * split (brand_luminance()), which is what lets all three be compared as the
 * contract above requires.
 *
 * Trusts its input: the caller has already matched the hex pattern, and every
 * call site here is reached only through customizer_hex_is_dark().
 */
function customizer_luminance($hex) {
    $c = ltrim($hex, '#');
    $w = array(0.2126, 0.7152, 0.0722);
    $y = 0.0;
    for($i = 0; $i < 3; $i++) {
        $v = hexdec(substr($c, $i * 2, 2)) / 255;
        $v = ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
        $y += $w[$i] * $v;
    }
    return $y;
}

/**
 * WCAG 2.x contrast ratio between two validated #rrggbb colours.
 *
 * Built on customizer_luminance() rather than carrying its own transfer
 * function, for the same reason customizer_hex_is_dark() is: a second copy of
 * the same arithmetic in the same file is a drift hazard with nothing to catch
 * it. Trusts its input, like the function it is built on.
 */
function customizer_contrast($a, $b) {
    $la = customizer_luminance($a);
    $lb = customizer_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * Which ink a rail of this colour will carry: '#FFFFFF' or '#000000', or '' when
 * the value is not a valid hex.
 *
 * This is the DIRECTION half of brand_rail_vars() in themes/clarity/brand.php,
 * and it is a copy for the same reason every other copy in this file is one:
 * brand.php is a PRE-AUTH endpoint that opens a database connection and prints a
 * stylesheet the moment it is included, so nothing here can call into it.
 *
 * Direction by MEASUREMENT, not by a lightness pivot. The crossover where black
 * and white read equally well sits at luminance 0.1791, close enough to the
 * 0.184 figure usually quoted that rails between the two — #767676 is one — are
 * handed the WORSE ink by any rule that rounds. Comparing the two ratios is
 * exact and needs no constant. It is a different question from
 * customizer_hex_is_dark()'s 0.5, which asks "is this background more dark than
 * light" in order to choose between two finished ARTWORKS; choosing INK has to
 * respect a contrast ratio, and the two thresholds must not be merged.
 *
 * What is deliberately NOT copied is the ink VALUE. clarity walks its inks out
 * of the rail's own hue up an alpha ladder so a branded rail still looks
 * branded, and reproducing that here would be a fourth implementation of
 * something with no contract to hold it. The Branding page promises the
 * direction and the measured ratio, which is what an operator needs before they
 * commit a colour — and tests/brand/run.php's rail-ink parity step compares this
 * direction against the one clarity's shipped output actually takes, on every
 * rail in the shared grid.
 */
function customizer_rail_ink($hex) {
    if(!is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $hex) !== 1) return '';
    return (customizer_contrast('#FFFFFF', $hex) >= customizer_contrast('#000000', $hex)) ? '#FFFFFF' : '#000000';
}

/**
 * Which variant a SURFACE asks for: the operator's explicit choice, else the
 * luminance of the colour that will be behind it, else the design's default.
 *
 * $stored         the operator's [branding] logo_variant_nav / logo_variant_login
 *                 value, already read by the caller — customizer_logo_variant_stored()
 *                 is what reads it
 * $bg_hex         the colour that will really be behind THIS slot, or '' when no
 *                 operator-settable colour reaches it (see below)
 * $design_default the variant the design wants when nothing else decides
 *
 * The ORDER is the contract, not an implementation detail. The explicit choice
 * is read first so that it wins over a colour the operator set earlier and
 * forgot about; the colour is only consulted when the choice is automatic; the
 * design default only when neither has anything to say.
 *
 * WHY $bg_hex IS A PARAMETER instead of being read from $branding right here:
 * the mapping surface -> colour is per DESIGN, and it is not universal.
 *   - clarity's nav slots sit on --nz-rail (app.css:121 for the rail, app.css:710
 *     for the mobile .nz-topbar-brand chip), which rail_hex repaints; and its
 *     login mark sits DIRECTLY on body.nz-login (login.css:35), because
 *     .nzl-brand is a sibling ABOVE .nzl-card in main_login.tpl.htm and both it
 *     and .nzl-scene are transparent — which login_bg repaints. clarity passes
 *     rail_hex and login_bg.
 *   - classic's logo is reached by NEITHER colour. Its rail block repaints only
 *     #main-navigation and .pushy — a band injected BELOW the header strip that
 *     holds #logo (core's main.tpl.htm:102-103 vs :51) — and no stock sheet
 *     paints that strip at all: ispconfig.css:75-80 has #logo's own background
 *     commented out, leaving body's #f2f5f7 (themes/default/theme.css:5). And
 *     classic's login mark is an <img> inside .panel-heading, whose
 *     "linear-gradient(to bottom, white, #eef0f2)" is an INLINE style attribute
 *     on core's own template (main_login.tpl.htm:39) that no external sheet can
 *     reach; login_bg paints only body, which is the page BEHIND that card.
 *     classic passes '' for both surfaces and falls to its design default.
 * Reading the hex in here would therefore flip classic's login mark to white the
 * moment an operator chose a dark login background — for a mark still sitting on
 * a white Bootstrap card. The operator's explicit choice is still honoured on
 * classic, because it is tested before any hex: the escape hatch stays open.
 *
 * An ABSENT key and a stored '' both mean automatic, and both occur in the
 * field: the key is absent until the first save and present-but-empty after it
 * (customizer_edit.php writes every branding key on every save, and ini_parser
 * round-trips the resulting "logo_variant_nav=" back as ''). Comparing with ===
 * against the two literals collapses those two states into one and, in the same
 * stroke, keeps any other value — a hand edit, a downgrade — out of the decision
 * entirely.
 *
 * MIRRORED by brand_logo_variant_pref() in themes/clarity/brand.php and in
 * themes/classic/brand.php — same three checks, in this order, with the same
 * === comparisons, the same fall-through and, since the signatures were unified,
 * the same parameters. All three take the already-read stored VALUE rather than
 * the key, which is what makes "diff the three copies" an audit somebody can
 * actually run; tests/brand/run.php runs the behavioural half of it on every
 * push by comparing all three decisions over a shared grid. See
 * customizer_hex_is_dark() for why the copies exist at all.
 */
function customizer_logo_variant_for_surface($stored, $bg_hex, $design_default) {
    if($stored === 'on_light' || $stored === 'on_dark') return $stored;

    //* The same anchored hex pattern the whole module uses, carrying /D: without
    //* it PCRE's `$` also matches before a final newline, so "#FF0000\n" would be
    //* accepted here while the pre-auth readers reject it — and this preview
    //* would then promise a mark the panel does not show.
    if(is_string($bg_hex) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $bg_hex) === 1) {
        return customizer_hex_is_dark($bg_hex) ? 'on_dark' : 'on_light';
    }

    return $design_default;
}

/**
 * Read one surface's stored variant out of [branding], as a string.
 *
 * The composition the resolver used to do internally, kept here so the resolver
 * itself stays identical to the two theme copies. An absent key and a stored ''
 * both mean automatic and both occur in the field — the key is absent until the
 * first save and present-but-empty after it — so they must be indistinguishable,
 * which is what returning '' for anything non-string achieves.
 */
function customizer_logo_variant_stored($surface, $branding) {
    if(!is_array($branding)) return '';
    $key = 'logo_variant_' . $surface;
    return (isset($branding[$key]) && is_string($branding[$key])) ? $branding[$key] : '';
}

/**
 * A POSTed field value, guaranteed to be a string, and guaranteed not to have
 * been healed into a valid one.
 *
 * Every validated field on this form is one crafted POST away from a fatal: a
 * VARCHAR field posted as an array (accent_hex[]=x) reaches tform_base's
 * filters and validators with an array subject, and trim(), strip_tags() and
 * preg_match() are all TypeErrors on PHP 8 — an admin page that dies instead of
 * reporting a bad value. Neither case below is reachable from a browser; both
 * are one request away from an authenticated admin.
 *
 * The coercion has to produce something the field's own validator REJECTS.
 * Rewriting an array to '' satisfied the string requirement by writing a VALID
 * value — the validator then passed, the save succeeded, and the page said
 * "saved" having quietly reset the operator's choice. 'invalid' matches none of
 * this form's three patterns (the hex, the reference and the variant one), so a
 * malformed POST is reported like any other bad value.
 *
 * A genuinely ABSENT field still becomes '': there is nothing to report in that
 * case, and the framework already treats a missing VARCHAR as '' one layer down
 * (tform_base.inc.php:830).
 *
 * CHECKBOX fields are deliberately NOT put through this — see the caller.
 */
function customizer_posted_string($raw) {
    if($raw === null)   return '';
    if(is_string($raw)) return $raw;
    return 'invalid';
}

/**
 * Normalise a POSTED logo-variant value so the VALIDATOR decides its fate.
 *
 * onBeforeUpdate has to guarantee a string reaches the framework: tform's
 * _encode converts arrays to strings for RADIO and CHECKBOX only, so an array
 * posted into one of these SELECTs reaches validateField and makes preg_match()
 * a TypeError — a fatal on an admin page rather than a validation error.
 *
 * It used to guarantee that by rewriting the array to '', and '' is the VALID
 * value meaning "Automatic". So the REGEX validator passed, the save went
 * through, the page redirected to "Settings saved." and the operator's stored
 * choice had been silently reset to automatic — a bad POST healed into a
 * successful write of the wrong thing, which is the exact failure the comment
 * beside that loop said must not happen. Returning a token the validator
 * REJECTS puts the decision back where the rest of the form keeps it.
 *
 * A missing field still becomes '': there is no value to report in that case,
 * and the framework already treats a missing VARCHAR as '' one layer down.
 *
 * The logo-variant flavour of the above, kept as its own name because the two
 * SELECTs' contract is documented under it and probe_module tests it by name.
 * One line, so the two can never disagree about the token.
 */
function customizer_logo_variant_posted($raw) {
    return customizer_posted_string($raw);
}

/**
 * The stored [branding] with the two variant keys overlaid from a POST.
 *
 * On a validation error tform redisplays the RAW POST, so the two selects show
 * what the operator just chose while the previews, which read the stored blob,
 * were still drawing the old choice: one page holding two answers about the same
 * setting, which is precisely the contradiction
 * customizer_logo_preview_html()'s own note says a preview exists to remove.
 *
 * ONLY the two variant keys are taken from the POST. Everything else — colours,
 * logos, the favicon — is either already reflected by its own control or is not
 * something the preview reads, and letting arbitrary posted keys into a blob
 * that gets handed to the resolvers would be a much wider contract than this
 * needs.
 */
function customizer_branding_with_posted_variants($stored, $posted) {
    if(!is_array($stored)) $stored = array();
    if(!is_array($posted)) return $stored;

    foreach(array('logo_variant_nav', 'logo_variant_login') as $key) {
        if(isset($posted[$key]) && is_string($posted[$key])) $stored[$key] = $posted[$key];
    }
    return $stored;
}

/**
 * The stored [branding] with the form's CANDIDATE values overlaid — what the
 * operator is looking at, not what is stored.
 *
 * The live preview is only worth having if it describes the unsaved page, so
 * this overlay is wider than customizer_branding_with_posted_variants(), which
 * takes the two variant keys alone and is used on the validation-error
 * redisplay. Both are allowlists, and both stay allowlists: the result is handed
 * to the resolvers and rendered into an admin page, so a POST must never be able
 * to introduce a key nobody expected.
 *
 * What it will NOT take from a POST, deliberately: the three uploaded images
 * (custom_logo, logo_on_dark, favicon). Those are not form fields — they are
 * written by logo_upload.php and are refreshed from the SERVER's own response —
 * so a posted one would be an image the panel does not have, previewed as
 * though it did.
 *
 * Each candidate is accepted only if it passes that key's own rule, and an
 * invalid one leaves the STORED value showing. That is exactly right rather than
 * merely safe: a save carrying an invalid value is refused outright, so the
 * stored value is what the panel would still be rendering.
 *
 * The hex repair here — trim, add a missing '#', upper-case — is the same repair
 * customizer_edit.php's onBeforeUpdate applies to a real save, so the preview
 * and the save agree about a pasted "0065ab". That includes the trim: a value
 * with a trailing newline is repaired here exactly as onBeforeUpdate repairs it
 * before the validators run, because a preview that refused what the save
 * accepts would be describing a panel that does not exist. The /D on both
 * patterns is still what keeps a newline from surviving INTO a value — without
 * it '$' matches before a final LF and an unrepairable value would be accepted
 * whole.
 */
function customizer_branding_with_posted_candidates($stored, $posted) {
    if(!is_array($stored)) $stored = array();
    if(!is_array($posted)) return $stored;

    foreach(array('accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg') as $key) {
        if(!isset($posted[$key]) || !is_string($posted[$key])) continue;
        $v = trim($posted[$key]);
        if(preg_match('/^[0-9A-Fa-f]{6}$/D', $v) === 1) $v = '#' . $v;
        if($v === '' || preg_match('/^#[0-9A-Fa-f]{6}$/D', $v) === 1) $stored[$key] = strtoupper($v);
    }

    foreach(array('logo_url', 'logo_url_on_dark', 'favicon_url') as $key) {
        if(!isset($posted[$key]) || !is_string($posted[$key])) continue;
        $v = trim($posted[$key]);
        //* customizer_logo_ref_ok() is the same anchored allowlist the tform
        //* validator and both theme readers use; '' is separately legitimate and
        //* means "no reference", which that function reports as not-ok.
        if($v === '' || customizer_logo_ref_ok($v)) $stored[$key] = $v;
    }

    foreach(array('logo_variant_nav', 'logo_variant_login') as $key) {
        if(!isset($posted[$key]) || !is_string($posted[$key])) continue;
        if($posted[$key] === '' || $posted[$key] === 'on_light' || $posted[$key] === 'on_dark') {
            $stored[$key] = $posted[$key];
        }
    }

    return $stored;
}

/**
 * One colour as the preview column needs it: the value, the ink that will be
 * printed on it, and the ratio the two measure.
 *
 * An unset or invalid value returns hex '' with a null ratio rather than a
 * default, because "the design's own colour stands" is a different statement
 * from "this colour", and the page says so in words rather than drawing a
 * swatch of a colour the operator never chose.
 */
function customizer_preview_colour($branding, $key) {
    $hex = '';
    if(is_array($branding) && isset($branding[$key]) && is_string($branding[$key])
        && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$key]) === 1) {
        $hex = strtoupper($branding[$key]);
    }
    if($hex === '') return array('hex' => '', 'ink' => '', 'ratio' => null);

    $ink = customizer_rail_ink($hex);
    return array('hex' => $hex, 'ink' => $ink, 'ratio' => round(customizer_contrast($ink, $hex), 2));
}

/**
 * Everything preview.php answers with, built without touching a database, an
 * HTTP request or a session — so it can be tested, and so the endpoint itself
 * holds no decision of its own.
 *
 * $stored_branding  the [branding] section as stored
 * $custom_logo      sys_ini.custom_logo as stored (the light-background upload)
 * $posted           the raw $_POST
 * $designs          which designs to describe, from customizer_installed_designs()
 * $labels           array('nav' => …, 'login' => …), already localised
 * $texts            the five already-localised preview strings, keyed
 *                   'no_logo', 'fallback_from_dark', 'fallback_from_light',
 *                   'no_favicon', 'favicon_url_wins'
 *
 * Returns:
 *   'previews' => array('used_logo' => html, 'used_logo_on_dark' => html,
 *                       'used_favicon' => html)   — the same renderers the page
 *                       and logo_upload.php use, so the three agree by
 *                       construction rather than by inspection
 *   'surfaces' => customizer_logo_surfaces_all()'s list, each entry additionally
 *                 carrying 'ink' and 'ratio' for its own 'bg'
 *   'colours'  => 'accent' | 'rail' | 'rail_light' | 'login', each a
 *                 customizer_preview_colour() block; 'rail_light' additionally
 *                 carries 'inherited' => bool
 *
 * The ink on each SURFACE is measured here rather than left to the caller
 * because the page's JavaScript repaints these panes after every keystroke and
 * carries no copy of the ink rule: customizer_rail_ink() is the only place that
 * direction is decided, on the server, and this payload is how it reaches the
 * browser. A pane needs the backdrop, the ink that goes on it, the ratio the two
 * measure and the variant that surface resolved to, so an entry carries all
 * four.
 *
 * Nothing the operator typed as free text comes back. The panel name is rendered
 * into the preview by the page itself, from the input, as text — round-tripping
 * it through JSON and into innerHTML would add a path for no gain.
 */
function customizer_preview_payload($stored_branding, $custom_logo, $posted, $designs, $labels, $texts) {
    $branding = customizer_branding_with_posted_candidates($stored_branding, $posted);
    if(!is_array($texts)) $texts = array();
    $txt = function($key) use ($texts) {
        return (isset($texts[$key]) && is_string($texts[$key])) ? $texts[$key] : '';
    };

    $resolved = customizer_logo_resolve(array(
        'custom_logo'      => is_string($custom_logo) ? $custom_logo : '',
        'logo_on_dark'     => isset($branding['logo_on_dark']) ? $branding['logo_on_dark'] : '',
        'logo_url'         => isset($branding['logo_url']) ? $branding['logo_url'] : '',
        'logo_url_on_dark' => isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    ));

    $surfaces = customizer_logo_surfaces_all($designs, $branding, $labels);

    //* customizer_preview_colour() is asked for the ink rather than
    //* customizer_rail_ink() directly, so the swatch beside a colour field and
    //* the pane painted on that same colour cannot round the ratio differently.
    //* 'bg' is documented as always a valid #rrggbb; an entry that ever is not
    //* gets ink '' and ratio null, which is the same "say nothing" answer a
    //* colour field gives, rather than a guessed ink on an unknown backdrop.
    foreach($surfaces as $i => $surface) {
        $measured = customizer_preview_colour($surface, 'bg');
        $surfaces[$i]['ink']   = $measured['ink'];
        $surfaces[$i]['ratio'] = $measured['ratio'];
    }

    //* The light rail falls back to the rail: a design with a light scope paints
    //* the same colour in both modes until the operator sets this one, and the
    //* preview has to show that rather than an empty pane. 'inherited' is what
    //* lets the page say WHICH of the two it is showing.
    $rail_light_key = (isset($branding['rail_hex_light']) && is_string($branding['rail_hex_light'])
        && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding['rail_hex_light']) === 1) ? 'rail_hex_light' : 'rail_hex';
    $rail_light = customizer_preview_colour($branding, $rail_light_key);
    $rail_light['inherited'] = ($rail_light_key === 'rail_hex');

    return array(
        'previews' => array(
            'used_logo' => customizer_logo_preview_html($resolved['on_light'], 'on_light',
                $txt('no_logo'), $txt('fallback_from_dark'), $surfaces),
            'used_logo_on_dark' => customizer_logo_preview_html($resolved['on_dark'], 'on_dark',
                $txt('no_logo'), $txt('fallback_from_light'), $surfaces),
            'used_favicon' => customizer_favicon_preview_html(
                customizer_favicon_resolve(array(
                    'favicon'     => isset($branding['favicon']) ? $branding['favicon'] : '',
                    'favicon_url' => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
                )),
                $txt('no_favicon'), $txt('favicon_url_wins')),
        ),
        'surfaces' => $surfaces,
        'colours'  => array(
            'accent'     => customizer_preview_colour($branding, 'accent_hex'),
            'rail'       => customizer_preview_colour($branding, 'rail_hex'),
            'rail_light' => $rail_light,
            'login'      => customizer_preview_colour($branding, 'login_bg'),
        ),
    );
}

/**
 * What a given design will really do with the two variants, one entry per thing
 * the operator can look at.
 *
 * $design    'clarity' or 'classic' — the design whose chrome is being described
 * $branding  the [branding] section as stored
 * $labels    array('nav' => …, 'login' => …), ALREADY LOCALISED, naming the two
 *            surfaces to the operator. surface_nav_txt and surface_login_txt say
 *            exactly this and live in the MODULE wordbook (lib/lang/<lang>.lng),
 *            which is the one both callers can reach: $app->lng() loads
 *            web/customizer/lib/lang/<lang>.lng (core app.inc.php:252-266) and
 *            cannot address the _customizer.lng tform wordbook by key at all, so
 *            a caller following any other instruction gets the key string back
 *            and prints it under the swatch. Omit them and the swatches are
 *            still drawn on the true colours, just unlabelled.
 *
 * Returns a list of array('surface', 'label', 'variant', 'bg') — one entry per
 * (surface, background) pair, because one surface can have two backgrounds; see
 * the mode note below. 'bg' is always a valid #rrggbb.
 *
 * An unknown $design returns an EMPTY list rather than a guess. A panel can run
 * a third-party theme that has no brand.php at all, in which case nothing here
 * applies and the honest preview is the generic one — saying nothing beats
 * describing chrome we have never seen. customizer_logo_surfaces_all() is the
 * caller that describes several installed designs at once.
 *
 * Be exact about how far "keyed by surface, not by design" reaches. It is true
 * of the READERS: logo_variant_nav / logo_variant_login name surfaces, so a
 * design that implements them inherits the operator's choice with no change
 * here, and CI holds every design to that. It is NOT true of the table below,
 * which is this module's hand-written knowledge of two specific designs. A third
 * design gets the correct MARK and no per-surface SWATCH until someone adds an
 * entry here — it degrades to the design-neutral preview rather than describing
 * chrome wrongly, which is the intended failure but is a failure to describe,
 * not a free inheritance. Moving the table into a per-design declaration the
 * designs themselves ship is the obvious next step and deliberately not taken
 * here: it would put a design-named path into an include, and this file is
 * reached from an admin page.
 *
 * Every value in the table is a citation:
 *
 *   clarity nav    --nz-rail, declared once at tokens.css:88 as --nz-blue-1100
 *                  (#01243D, tokens.css:47) and NOT redeclared in the light-mode
 *                  block at tokens.css:215+ — the shipped rail is navy in both
 *                  colour modes — and, since rail_hex_light exists, redeclared
 *                  in the light scope by brand.php whenever the operator sets
 *                  that key. So this surface has ONE backdrop until the two
 *                  rails differ and TWO after, which is why it carries
 *                  'bg_key_light' rather than the static 'modes' map the login
 *                  slot uses: the login slot's two backdrops are the DESIGN's
 *                  own colours, and the nav slot's are the OPERATOR's.
 *   clarity login  body.nz-login is var(--nz-page) (login.css:35), and --nz-page
 *                  IS mode-dependent: --nz-ink-1100 #17252B dark (tokens.css:61,
 *                  :80) against #F1F6F8 light (tokens.css:219). With login_bg
 *                  set, clarity's login-background block paints that one colour
 *                  in both modes — the same $base in the base rule and in the
 *                  :root[data-nz-theme='light'] one — and the split collapses
 *                  to a single swatch.
 *   classic nav    #f2f5f7 — body's own background (themes/default/theme.css:5);
 *                  #logo has no background of its own (ispconfig.css:75-80, the
 *                  stock one commented out) and rail_hex never reaches it.
 *   classic login  the DARKER stop of core's inline white -> #eef0f2 gradient on
 *                  .panel-heading (main_login.tpl.htm:39). The two stops are a
 *                  hair apart and averaging them would print a number that
 *                  exists nowhere; the darker stop is the honest one to show.
 */
function customizer_logo_surfaces($design, $branding, $labels = array()) {
    if(!is_array($branding)) $branding = array();
    if(!is_array($labels))   $labels   = array();

    //* 'bg_key' is the [branding] colour that repaints this slot's backdrop, or
    //* '' when no operator-settable colour reaches it — the parameter argument
    //* in customizer_logo_variant_for_surface() spelled as data. 'bg_key_light'
    //* is the same thing for LIGHT colour mode, or '' where the operator has no
    //* per-mode say over this slot's backdrop. 'modes' is the
    //* variant => colour pair for a slot whose backdrop follows the viewer's
    //* own light/dark mode, and is empty for every slot that has one backdrop.
    //*
    //* 'default' is only ever reached by a slot with ONE backdrop. A slot with a
    //* 'modes' map has two, and on automatic each mode shows its own mark, so the
    //* per-mode variant below replaces whatever the default said — which is why
    //* clarity's login carries '' here rather than a variant name. It was
    //* 'on_dark', unreachable by any combination of stored values, and reading
    //* like the authoritative default a third design would copy.
    $chrome = array(
        'clarity' => array(
            'nav'   => array('default' => 'on_dark', 'bg_key' => 'rail_hex', 'bg' => '#01243D',
                             'bg_key_light' => 'rail_hex_light', 'modes' => array()),
            'login' => array('default' => '', 'bg_key' => 'login_bg', 'bg' => '',
                             'bg_key_light' => '',
                             'modes'   => array('on_dark' => '#17252B', 'on_light' => '#F1F6F8')),
        ),
        'classic' => array(
            'nav'   => array('default' => 'on_light', 'bg_key' => '', 'bg' => '#F2F5F7',
                             'bg_key_light' => '', 'modes' => array()),
            'login' => array('default' => 'on_light', 'bg_key' => '', 'bg' => '#EEF0F2',
                             'bg_key_light' => '', 'modes' => array()),
        ),
    );

    if(!is_string($design) || !isset($chrome[$design])) return array();

    $out = array();
    foreach($chrome[$design] as $surface => $spec) {
        $label = (isset($labels[$surface]) && is_string($labels[$surface])) ? $labels[$surface] : '';

        $bg_hex = '';
        if($spec['bg_key'] !== '' && isset($branding[$spec['bg_key']]) && is_string($branding[$spec['bg_key']])
            && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$spec['bg_key']]) === 1) {
            $bg_hex = $branding[$spec['bg_key']];
        }

        //* A slot whose backdrop the operator can set PER COLOUR MODE. It is
        //* resolved like the base one and only produces a second entry when it
        //* is set AND differs — an operator who has not touched it, or who set
        //* the same colour twice, sees exactly what they saw before.
        $bg_light = '';
        if($spec['bg_key_light'] !== '' && isset($branding[$spec['bg_key_light']])
            && is_string($branding[$spec['bg_key_light']])
            && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$spec['bg_key_light']]) === 1) {
            $bg_light = $branding[$spec['bg_key_light']];
        }
        //* With no base colour set the design's own is what light mode differs
        //* FROM, so the comparison is against the swatch that will be drawn.
        $bg_base = ($bg_hex !== '') ? $bg_hex : $spec['bg'];
        if($bg_light !== '' && strcasecmp($bg_light, $bg_base) !== 0) {
            //* Key ORDER matters and must match every other entry this function
            //* emits: probe_module compares whole entries with ===, which in PHP
            //* is order-sensitive for arrays.
            $out[] = array('surface' => $surface, 'label' => $label,
                           'variant' => customizer_logo_variant_for_surface(
                               customizer_logo_variant_stored($surface, $branding), $bg_base, $spec['default']),
                           'bg' => $bg_base);
            $out[] = array('surface' => $surface, 'label' => $label,
                           'variant' => customizer_logo_variant_for_surface(
                               customizer_logo_variant_stored($surface, $branding), $bg_light, $spec['default']),
                           'bg' => $bg_light);
            continue;
        }

        //* Called with an EMPTY design default so the fall-through is visible:
        //* '' coming back means neither the explicit choice nor a known colour
        //* decided, which is the only state in which a mode-following slot shows
        //* two different marks. Re-reading logo_variant_<surface> here instead
        //* would be a second copy of the resolver's first check — the very line
        //* that has to stay identical to the two brand.php readers.
        $variant = customizer_logo_variant_for_surface(
            customizer_logo_variant_stored($surface, $branding), $bg_hex, '');
        $auto    = ($variant === '');
        if($auto) $variant = $spec['default'];

        //* A slot with no single backdrop is previewed once per colour mode, on
        //* that mode's real page colour. On automatic each mode also gets its
        //* own mark — clarity's $login_follows_mode branch, which emits a base
        //* rule plus a :root[data-nz-theme='light'] override, and the reason
        //* this function passes '' as a sentinel default just above. With an
        //* explicit choice the SAME mark is drawn on both backdrops instead,
        //* matching what that branch then does ($light_want falls back to the
        //* chosen preference), and showing the operator the thing they most need
        //* to see before committing: their forced mark sitting on the colour
        //* mode they did not have in mind.
        if($bg_hex === '' && !empty($spec['modes'])) {
            foreach($spec['modes'] as $mode_variant => $mode_bg) {
                $out[] = array('surface' => $surface, 'label' => $label,
                               'variant' => ($auto ? $mode_variant : $variant), 'bg' => $mode_bg);
            }
            continue;
        }

        $out[] = array('surface' => $surface, 'label' => $label, 'variant' => $variant,
                       'bg' => ($bg_hex !== '' ? $bg_hex : $spec['bg']));
    }
    return $out;
}

/**
 * Every installed design's surfaces at once, which is the only honest way to
 * preview a setting that every installed design obeys.
 *
 * logo_variant_nav and logo_variant_login are stored ONCE in [branding] and read
 * by every design on the panel — but "nav" does not mean one colour. It is navy
 * on clarity and stock's #F2F5F7 on classic, which is the whole reason two logo
 * variants exist. So an operator who pins on_dark to rescue a recoloured clarity
 * rail is, in the same keystroke, painting the white mark onto classic's light
 * header; and while this preview described only $_SESSION['s']['theme'], there
 * was nowhere on the page that could show it happening. They would find out from
 * a client.
 *
 * $designs   the designs to describe, in display order. The CALLER decides what
 *            is installed — install.sh deploys clarity, classic or both, so
 *            listing every design this file knows would describe chrome the
 *            panel does not have, which is the failure mode the single-design
 *            function is careful to avoid.
 * $labels    as customizer_logo_surfaces(), and prefixed with the design's own
 *            name once there is more than one design to tell apart. The name is
 *            a proper noun and is not translated; the surface half still is.
 *
 * Each entry carries a 'design' key so a caller can group or filter; entries are
 * otherwise exactly customizer_logo_surfaces()'s, and a design with no chrome
 * entry contributes nothing rather than a guess.
 */
function customizer_logo_surfaces_all($designs, $branding, $labels = array()) {
    if(!is_array($designs)) $designs = array();
    if(!is_array($labels))  $labels  = array();

    //* Only name the design when there are two or more to tell apart: on the
    //* overwhelmingly common single-design panel the prefix would be noise
    //* repeated under every swatch.
    $named = count($designs) > 1;

    $out = array();
    foreach($designs as $design) {
        if(!is_string($design) || $design === '') continue;

        $these = $labels;
        if($named) {
            foreach($these as $surface => $text) {
                $these[$surface] = ucfirst($design) . ' · ' . $text;
            }
        }

        foreach(customizer_logo_surfaces($design, $branding, $these) as $entry) {
            $entry['design'] = $design;
            $out[] = $entry;
        }
    }
    return $out;
}

/**
 * The designs this panel actually has installed, in display order.
 *
 * ISPC_THEMES_PATH is core's own answer to "where do designs live" and
 * is_dir() against it is core's own test — tools/user_settings.php uses exactly
 * this shape before it will let a user select a theme. Asking the filesystem
 * rather than __DIR__ matters because install.sh's default mode SYMLINKS a
 * design into the panel: __DIR__ would resolve back into the git clone, where
 * every design this repository ships exists whether or not it was deployed.
 *
 * The active design comes first — it is the one the operator is looking at — and
 * is included even if the directory check cannot see it, so that a panel whose
 * paths are laid out in some way not anticipated here still previews the design
 * in front of the admin instead of nothing at all.
 */
function customizer_installed_designs($active) {
    $known = array('clarity', 'classic');

    $out = array();
    if(is_string($active) && in_array($active, $known, true)) $out[] = $active;

    foreach($known as $design) {
        if(in_array($design, $out, true)) continue;
        if(defined('ISPC_THEMES_PATH') && @is_dir(ISPC_THEMES_PATH . '/' . $design)) $out[] = $design;
    }
    return $out;
}

/**
 * One preview row: this variant's mark, drawn on every surface that will use it.
 *
 * $resolved      one entry of customizer_logo_resolve()
 * $want          'on_light' or 'on_dark' — which row this is
 * $no_logo_text  already-localised text for "nothing set anywhere"
 * $fallback_text already-localised note, shown only when this row is displaying
 *                the OTHER variant; pass '' to suppress it
 * $surfaces      customizer_logo_surfaces() output (any design, or several
 *                concatenated). One swatch is drawn per entry whose 'variant' is
 *                $want, on that entry's real colour and captioned with its
 *                label. Omit it and the row falls back to a single swatch on the
 *                design-neutral colour of its own kind, which is exactly what
 *                this function did before surfaces existed.
 *
 * The swatch IS the feature: a mark is judged against the thing behind it, so a
 * white logo dropped into the light slot has to LOOK wrong here. Which is why
 * the swatch has to be the operator's own rail_hex / login_bg wherever those
 * reach the slot — the hardcoded #F2F5F7 / #01243D pair this replaced assumed
 * exactly the design defaults those two colours can falsify, so on a panel with
 * a recoloured rail the preview drew the mark on a background the panel no
 * longer has. A preview that is confidently wrong is worse than none.
 *
 * The labels are emitted unescaped, exactly as $no_logo_text and $fallback_text
 * always have been: all three are wordbook entries, and this wordbook uses named
 * entities (&times;) deliberately, so escaping them would print the source.
 */
function customizer_logo_preview_html($resolved, $want, $no_logo_text, $fallback_text = '', $surfaces = array()) {
    $src  = (is_array($resolved) && isset($resolved['src']))  ? (string)$resolved['src']  : '';
    $from = (is_array($resolved) && isset($resolved['from'])) ? (string)$resolved['from'] : '';

    if($src === '') return '<em>' . $no_logo_text . '</em>';

    $boxes = array();
    if(is_array($surfaces)) {
        foreach($surfaces as $s) {
            if(!is_array($s) || !isset($s['variant']) || $s['variant'] !== $want) continue;
            $bg = (isset($s['bg']) && is_string($s['bg'])
                && preg_match('/^#[0-9A-Fa-f]{6}$/D', $s['bg']) === 1) ? $s['bg'] : '';
            $boxes[] = array('bg' => $bg, 'label' => (isset($s['label']) && is_string($s['label'])) ? $s['label'] : '');
        }
    }

    //* No surface asks for this variant — every slot resolved to the other one.
    //* Draw the generic swatch and say nothing rather than label the row as
    //* unused: core renders sys_ini.custom_logo on the stock theme's own header
    //* and login page whatever this extension prefers, and the panel may have
    //* the other design installed for other users. "Unused" would be a claim we
    //* cannot support; a swatch of the right kind is one we can.
    if(!$boxes) $boxes[] = array('bg' => '', 'label' => '');

    //* htmlspecialchars is a no-op on a valid data URI — the base64 alphabet and
    //* the "data:image/…;base64," prefix contain none of & < > " ' — so escaping
    //* unconditionally costs nothing and removes the need for the reader of this
    //* line to know which of the two kinds of value it is holding.
    $esc  = htmlspecialchars($src, ENT_QUOTES);
    $html = '<span style="display:inline-flex;gap:12px;flex-wrap:wrap;align-items:flex-start">';

    foreach($boxes as $b) {
        //* The design-neutral colours are the pair this row was drawn on before
        //* surfaces existed: stock's own page/header grey (themes/default/
        //* theme.css:5) and clarity's rail navy (tokens.css:47). They are only
        //* reached when nothing better is known.
        $bg = ($b['bg'] !== '') ? $b['bg'] : (($want === 'on_dark') ? '#01243D' : '#F2F5F7');

        //* One luminance test decides the frame and the caption ink, so an
        //* operator colour gets the same treatment as the built-in swatches. The
        //* 1px edge keeps a LIGHT swatch visible against the (also light) form
        //* background — without it a white logo on light grey reads as an empty
        //* box rather than as a logo that has disappeared, and those two look
        //* identical for the wrong reason. A dark swatch delimits itself, so its
        //* edge is its own colour.
        $dark = customizer_hex_is_dark($bg);
        $edge = $dark ? $bg : '#D5DDE3';
        $ink  = $dark ? '#93A9BA' : '#5A6B78';

        $html .= '<span style="display:inline-block;background:' . $bg . ';border:1px solid ' . $edge
               . ';border-radius:4px;padding:6px 12px;text-align:center;line-height:1">'
               . '<img src="' . $esc . '" alt="" />';
        if($b['label'] !== '') {
            //* Only the INK is inline, because it is a measured value: the
            //* luminance test above chose it for this swatch's own background.
            //* Everything about the caption's SIZE is a rule in the page's style
            //* block (.nz-markcap) — an inline max-width beats every selector,
            //* and this row now lives in a 108px grid column that the page, not
            //* this function, is entitled to decide the width of.
            $html .= '<span class="nz-markcap" style="color:' . $ink . '">' . $b['label'] . '</span>';
        }
        $html .= '</span>';
    }
    $html .= '</span>';

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
