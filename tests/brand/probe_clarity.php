<?php
/**
 * themes/clarity/brand.php — the pure helpers.
 *
 * Run through tests/brand/run.php, which gives this file a process of its own:
 * classic defines the same function names.
 */

require_once __DIR__ . '/harness.php';
load_helpers(__DIR__ . '/../../themes/clarity/brand.php');

/* ---- the rail ink family ------------------------------------------------
 * rail_hex repaints --nz-rail, and the panel's whole navigation is printed on
 * top of it. Every other rail token was a white-family constant declared once in
 * tokens.css, so an operator who set a light rail got white text, white group
 * headings and a white-on-white logo — and the field's own help text invites
 * exactly that ("such as a white sidebar"). These assertions are the contract
 * that made the help text true.
 */
/**
 * The ratio to hold this ink to on this backdrop.
 *
 * 4.5:1 is the target, but no ink reaches it on every colour: a mid grey is the
 * worst case for legibility, and #767676 tops out at 4.69:1 against pure black
 * and 4.48:1 against pure white — so on some backdrops the best ink in existence
 * still misses AA. Demanding a flat 4.5 there would be demanding the impossible
 * and the honest assertion is "as good as any ink could be". Anything short of
 * that IS a defect, which is what this catches: it fails the moment the rule
 * picks the worse of the two directions.
 */
function want_ratio($backdrop) {
    $best = max(h_contrast('#FFFFFF', $backdrop), h_contrast('#000000', $backdrop));
    return min(4.5, $best - 0.01);
}

foreach (h_rails() as $rail) {
    $d = h_decls(brand_rail_vars($rail));

    t_eq("rail $rail: --nz-rail is the operator's own colour", isset($d['--nz-rail']) ? $d['--nz-rail'] : null, $rail);

    foreach (array('--nz-rail-text', '--nz-rail-text-hover', '--nz-rail-heading') as $ink) {
        if (!t_ok("rail $rail: $ink is emitted", isset($d[$ink]))) continue;
        $flat = h_flatten($d[$ink], $rail);
        if (!t_ok("rail $rail: $ink parses", $flat !== '', $d[$ink])) continue;
        $c = h_contrast($flat, $rail);
        $w = want_ratio($rail);
        t_ok(sprintf("rail %s: %s reads at %.2f:1 (>= %.2f)", $rail, $ink, $c, $w), $c >= $w, "flattened $flat");
    }

    // The active/hover strata are backgrounds the SAME inks are printed on, so
    // every ink has to clear the ratio against them too — a token set that only
    // considered the base rail would go unreadable on the selected item alone.
    // --nz-rail-text-hover on --nz-rail-hover is the pair app.css renders
    // together for a hovered item, so it is asserted as a pair.
    foreach (array('--nz-rail-active', '--nz-rail-hover') as $bgtok) {
        if (!t_ok("rail $rail: $bgtok is emitted", isset($d[$bgtok]))) continue;
        $bg = h_flatten($d[$bgtok], $rail);
        if (!t_ok("rail $rail: $bgtok parses", $bg !== '', $d[$bgtok])) continue;
        foreach (array('--nz-rail-text', '--nz-rail-text-hover') as $inktok) {
            if (!isset($d[$inktok])) continue;
            $ink = h_flatten($d[$inktok], $bg);
            if ($ink === '') continue;
            $c = h_contrast($ink, $bg);
            $w = want_ratio($bg);
            t_ok(sprintf("rail %s: %s on %s reads at %.2f:1 (>= %.2f)", $rail, $inktok, $bgtok, $c, $w), $c >= $w);
        }
    }

    // The two strata have to stay tellable apart. Both are derived by moving the
    // rail's lightness, and a rail near the top of the range has so little
    // headroom that a naive clamp collapses both onto pure white — leaving a
    // hovered item and a selected item painted the same colour as each other.
    if (isset($d['--nz-rail-active']) && isset($d['--nz-rail-hover'])) {
        t_ok("rail $rail: the active and hover strata are distinct",
            h_flatten($d['--nz-rail-active'], $rail) !== h_flatten($d['--nz-rail-hover'], $rail),
            $d['--nz-rail-active'] . ' vs ' . $d['--nz-rail-hover']);
        t_ok("rail $rail: the hover stratum is distinct from the rail",
            h_flatten($d['--nz-rail-hover'], $rail) !== strtoupper($rail), $d['--nz-rail-hover']);
    }

    // Icons and the active indicator are non-text, so WCAG's 3:1 applies.
    if (t_ok("rail $rail: --nz-rail-accent is emitted", isset($d['--nz-rail-accent']))) {
        $acc = h_flatten($d['--nz-rail-accent'], $rail);
        if ($acc !== '') {
            $c = h_contrast($acc, $rail);
            $w = min(3.0, max(h_contrast('#FFFFFF', $rail), h_contrast('#000000', $rail)) - 0.01);
            t_ok(sprintf("rail %s: --nz-rail-accent reads at %.2f:1 (>= %.2f)", $rail, $c, $w), $c >= $w, "flattened $acc");
        }
    }

    if (t_ok("rail $rail: --nz-rail-edge is emitted", isset($d['--nz-rail-edge']))) {
        t_ok("rail $rail: --nz-rail-edge is not the rail itself", h_flatten($d['--nz-rail-edge'], $rail) !== strtoupper($rail));
    }
}

/* ---- the shipped navy must not move -------------------------------------
 * Every panel in the field runs this value. The ink family above is a rescue for
 * colours the design never anticipated; it must be invisible to everyone else,
 * so the two tokens that existed before keep their exact previous output.
 */
$navy = h_decls(brand_rail_vars('#01243D'));
t_eq('navy rail: --nz-rail unchanged', $navy['--nz-rail'], '#01243D');
t_eq('navy rail: --nz-rail-active unchanged', $navy['--nz-rail-active'], brand_shade('#01243D', 15));
t_ok('navy rail: ink stays the white family',
    isset($navy['--nz-rail-text']) && strpos($navy['--nz-rail-text'], '255, 255, 255') !== false,
    isset($navy['--nz-rail-text']) ? $navy['--nz-rail-text'] : 'not emitted');
t_eq('navy rail: text keeps tokens.css\'s exact value',
    isset($navy['--nz-rail-text']) ? $navy['--nz-rail-text'] : null, 'rgba(255, 255, 255, 0.88)');
t_eq('navy rail: heading keeps tokens.css\'s exact value',
    isset($navy['--nz-rail-heading']) ? $navy['--nz-rail-heading'] : null, 'rgba(255, 255, 255, 0.66)');

/* ---- no rail ink may be a literal ---------------------------------------
 * brand.php can repaint the rail and its strata to ANY colour the operator
 * chooses, so an ink written as a literal in the stylesheet is an ink that
 * cannot follow it. Three rules did exactly that while --nz-rail-active was
 * always dark, which made the literal white harmless — and the moment the
 * active stratum became light on a light rail, the current module and the
 * current page turned white-on-white. Catching the CLASS matters more than
 * catching the three, because the next rule someone adds to the rail will reach
 * for #FFFFFF too.
 */
$css_path = __DIR__ . '/../../themes/clarity/assets/stylesheets/clarity/app.css';
$app_css  = file_get_contents($css_path);
t_ok('app.css is readable', $app_css !== false);
if ($app_css !== false) {
    //* Split into rule bodies and look at the ones painted with a rail token.
    $bad = array();
    if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $app_css, $rules, PREG_SET_ORDER)) {
        foreach ($rules as $rule) {
            $body = $rule[2];
            if (strpos($body, 'var(--nz-rail') === false) continue;
            if (!preg_match('/background[^;]*var\(--nz-rail/', $body)) continue;
            if (preg_match('/(?<![-\w])color\s*:\s*(#[0-9A-Fa-f]{3,8}|rgba?\(|white\b)/i', $body, $m)) {
                $bad[] = trim(preg_replace('/\s+/', ' ', $rule[1])) . ' -> ' . $m[1];
            }
        }
    }
    t_ok('no rule painted with a rail token hardcodes its ink',
        empty($bad), implode('; ', $bad));

    //* Any rule colouring one of the dashlet's ANCHORS has to out-specify
    //* app.css's `body.nz a { color: var(--nz-link) }`, which is (0,1,2). A bare
    //* `.nz-donate-cta` is (0,1,0) and loses regardless of source order — which
    //* is how the donate button first shipped rendering link-blue on its own
    //* fill at 1.22:1. Specificity bugs are invisible in the source and obvious
    //* only on screen, so the guard belongs here.
    //*
    //* The donate rules live in components.css, NOT in app.css — the first
    //* version of this assertion scanned app.css, found no donate rules at all,
    //* and passed vacuously while the defect was still in the tree.
    //* Comments are stripped first. A selector capture runs from the previous
    //* brace, so it swallows any comment sitting above the rule — and the
    //* comment above this very block names .nz-donate-cta while explaining the
    //* fix, which made the assertion report the explanation as the defect.
    $comp = (string)@file_get_contents(__DIR__ . '/../../themes/clarity/assets/stylesheets/clarity/components.css');
    $comp = preg_replace('#/\*.*?\*/#s', '', $comp);
    t_ok('components.css is readable and carries the donate block',
        strpos($comp, '.nz-donate') !== false);

    $weak = array();
    if (preg_match_all('/([^{}]*\.nz-donate-(?:cta|dismiss)[^{}]*)\{([^{}]*)\}/', $comp, $rules, PREG_SET_ORDER)) {
        foreach ($rules as $rule) {
            if (!preg_match('/(?<![-\w])color\s*:/', $rule[2])) continue;
            foreach (explode(',', $rule[1]) as $sel) {
                $sel = trim($sel);
                if ($sel === '' || strpos($sel, '.nz-donate-') === false) continue;
                if (strpos($sel, 'body.nz') !== 0) $weak[] = $sel;
            }
        }
    }
    t_ok('every donate anchor rule out-specifies body.nz a',
        empty($weak), implode('; ', $weak));
}

/* ---- the donation dashlet override keeps core's contract ------------------
 * A dashlet override is a template swap: core still resolves the same five
 * tmpl_vars and still expects its own Hide link to be there. Drop a var and that
 * text silently vanishes from the panel; change the Hide target and the button
 * stops writing core's hide_donation_dashlet row, so the block can never be
 * dismissed. Neither failure is visible without an admin looking at a dashboard.
 */
$donate = @file_get_contents(__DIR__ . '/../../themes/clarity/templates/dashboard/donate.htm');
t_ok('the donate override exists', $donate !== false);
if ($donate !== false) {
    //* Assert against the MARKUP, not the file: the header comment quotes stock's
    //* own <h4>, its id and its inline background-color in order to explain why
    //* each one is gone, and a whole-file scan reports the explanation as the
    //* defect it is describing.
    $markup = preg_replace('/<!--.*?-->/s', '', $donate);

    foreach (array('donate_txt', 'more_btn_txt', 'donate2_txt', 'hide_btn_txt', 'donate_btn_txt') as $var) {
        t_ok("donate override keeps {tmpl_var $var}",
            strpos($markup, "name='" . $var . "'") !== false);
    }

    t_ok('donate override keeps core\'s exact Hide target',
        strpos($markup, "data-load-content=\"dashboard/dashboard.php?hide=donate\"") !== false);

    //* The reason this override exists: stock's inline script binds
    //* $("button").click() with no scope, so every button on the dashboard
    //* toggles the dashlet. Carrying any script back in would risk reinstating
    //* it, and <details>/<summary> needs none.
    t_ok('donate override ships no script at all', stripos($markup, '<script') === false);
    t_ok('donate override uses a disclosure rather than a toggle handler',
        strpos($markup, '<details') !== false && strpos($markup, '<summary') !== false);

    //* Stock's white slab is an inline declaration no stylesheet can beat.
    t_ok('donate override sets no inline background', stripos($markup, 'background-color') === false);
    t_ok('donate override introduces no page-global id', !preg_match('/\sid=/', $markup));
    t_ok('donate override does not wrap prose in a heading', !preg_match('/<h[1-6][\s>]/i', $markup));
    t_ok('donate override gives the outbound link rel=noopener',
        strpos($markup, 'rel="noopener"') !== false);

    //* A theme cannot add or shadow ISPConfig lang keys, so any visible word
    //* written here would be English on a translated panel. Everything the
    //* operator reads has to arrive through a tmpl_var.
    $body = preg_replace('/<[^>]*>/', '', $markup);               // strip tags
    $body = preg_replace('/\{tmpl_var[^}]*\}/', '', $body);       // strip the vars
    t_ok('donate override has no hardcoded visible text', trim($body) === '', trim($body));
}

/* ---- luminance is defined once ------------------------------------------
 * brand_is_dark() carried its own copy of the sRGB transfer function. A second
 * copy of the same arithmetic in the same file is a drift hazard with nothing to
 * catch it, so the threshold test now sits on top of the shared measurement.
 */
t_ok('brand_luminance() exists', function_exists('brand_luminance'));
if (function_exists('brand_luminance')) {
    foreach (h_rails() as $hex) {
        t_ok("luminance agrees with the spec for $hex", abs(brand_luminance($hex) - h_lum($hex)) < 1e-9);
        t_eq("brand_is_dark is brand_luminance < 0.5 for $hex", brand_is_dark($hex), brand_luminance($hex) < 0.5);
    }
}

/* ---- contrast helpers, so the rail ink can be derived rather than guessed */
t_ok('brand_contrast() exists', function_exists('brand_contrast'));
if (function_exists('brand_contrast')) {
    t_ok('black on white is ~21:1', abs(brand_contrast('#000000', '#FFFFFF') - 21.0) < 0.05);
    t_ok('a colour against itself is 1:1', abs(brand_contrast('#0065AB', '#0065AB') - 1.0) < 1e-9);
}

/* ---- the light-mode login filter ----------------------------------------
 * Extracted from the endpoint body, where the test read
 * `$light_slot !== '' && !$one_variant`. The left half could never be false when
 * the right half was true, so it read as two guards while being one. Naming the
 * real question makes the redundancy impossible to reintroduce.
 */
t_ok('brand_login_light_filter() exists', function_exists('brand_login_light_filter'));
if (function_exists('brand_login_light_filter')) {
    t_eq('both variants stored: the mark keeps its own colours',
        brand_login_light_filter('data:image/png;base64,AA', 'data:image/png;base64,BB'), 'filter: none;');
    t_ok('only the light-background mark: the halo stays',
        strpos(brand_login_light_filter('data:image/png;base64,AA', ''), 'drop-shadow') !== false);
    t_ok('only the dark-background mark: the halo stays',
        strpos(brand_login_light_filter('', 'data:image/png;base64,BB'), 'drop-shadow') !== false);
    t_ok('no mark at all: the halo stays',
        strpos(brand_login_light_filter('', ''), 'drop-shadow') !== false);
}

/* ---- the resolver, and the matrix run.php cross-compares ----------------- */
t_eq('explicit choice beats a contradicting background',
    brand_logo_variant_pref('on_dark', '#FFFFFF', 'on_light'), 'on_dark');
t_eq('an unrecognised stored value is automatic',
    brand_logo_variant_pref('garbage', '#FFFFFF', 'on_dark'), 'on_light');
t_eq('a trailing newline does not make a hex valid',
    brand_logo_variant_pref('', "#FF0000\n", 'on_dark'), 'on_dark');

$matrix = array();
foreach (h_variant_matrix() as $row) {
    $matrix[] = brand_logo_variant_pref($row[0], $row[1], $row[2]);
}
echo 'MATRIX ' . json_encode($matrix) . "\n";

t_done();
