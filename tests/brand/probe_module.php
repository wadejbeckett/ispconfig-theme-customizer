<?php
/**
 * interface/web/customizer/lib/preview.inc.php — the module's copy of the
 * logo-variant model, plus the two POST helpers the edit page leans on.
 *
 * This file is a plain library of function definitions, so unlike the two theme
 * endpoints it can simply be required.
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/../../interface/web/customizer/lib/preview.inc.php';

/* ---- luminance is defined once, here too --------------------------------- */
t_ok('customizer_luminance() exists', function_exists('customizer_luminance'));
if (function_exists('customizer_luminance')) {
    foreach (h_rails() as $hex) {
        t_ok("luminance agrees with the spec for $hex", abs(customizer_luminance($hex) - h_lum($hex)) < 1e-9);
        t_eq("customizer_hex_is_dark is customizer_luminance < 0.5 for $hex",
            customizer_hex_is_dark($hex), customizer_luminance($hex) < 0.5);
    }
}

/* ---- a posted variant is REPORTED, never healed --------------------------
 * onBeforeUpdate has to guarantee a string reaches the framework, because an
 * array subject makes preg_match() a TypeError on PHP 8. It was doing that by
 * rewriting the array to '' — which is a VALID value meaning "Automatic", so the
 * REGEX validator passed, the save succeeded, the page said "Settings saved."
 * and the operator's stored choice had been silently reset. The coercion has to
 * produce something the validator REJECTS.
 */
t_ok('customizer_logo_variant_posted() exists', function_exists('customizer_logo_variant_posted'));
if (function_exists('customizer_logo_variant_posted')) {
    $re = '/^(on_light|on_dark)?$/D';
    t_eq('a real choice survives untouched', customizer_logo_variant_posted('on_dark'), 'on_dark');
    t_eq('Automatic survives untouched', customizer_logo_variant_posted(''), '');
    t_eq('an absent field is Automatic', customizer_logo_variant_posted(null), '');
    t_ok('a bad token is left for the validator to reject',
        customizer_logo_variant_posted('nonsense') === 'nonsense');

    $arr = customizer_logo_variant_posted(array('on_dark'));
    t_ok('an array POST becomes a string', is_string($arr), gettype($arr));
    t_ok('...that the validator rejects rather than storing', !preg_match($re, (string)$arr), var_export($arr, true));
}

/* ---- the preview describes what the CONTROLS say, not what is stored ------
 * On a validation error tform redisplays the raw POST, so the two selects show
 * the operator's new choice while render_image_previews() re-read the stored
 * blob and drew the swatches under the OLD one. One page, two answers about the
 * same setting.
 */
t_ok('customizer_branding_with_posted_variants() exists', function_exists('customizer_branding_with_posted_variants'));
if (function_exists('customizer_branding_with_posted_variants')) {
    $stored = array('logo_variant_nav' => 'on_light', 'logo_variant_login' => '', 'rail_hex' => '#01243D');
    $posted = array('logo_variant_nav' => 'on_dark', 'accent_hex' => '#123456');
    $m = customizer_branding_with_posted_variants($stored, $posted);
    t_eq('a posted variant wins over the stored one', $m['logo_variant_nav'], 'on_dark');
    t_eq('a variant absent from the POST keeps the stored value', $m['logo_variant_login'], '');
    t_eq('non-variant keys are never taken from the POST', $m['rail_hex'], '#01243D');
    t_ok('the POST cannot introduce unrelated keys', !isset($m['accent_hex']));
    t_eq('no POST at all leaves the stored blob alone',
        customizer_branding_with_posted_variants($stored, array()), $stored);
    t_ok('a non-array POST is ignored', customizer_branding_with_posted_variants($stored, null) === $stored);
}

/* ---- every design the panel runs, not just the one the admin is looking at
 * logo_variant_* is stored ONCE in [branding] and read by every installed
 * design, but "nav" is navy on clarity and stock's #F2F5F7 on classic. Pinning
 * on_dark to rescue a recoloured clarity rail therefore paints the white mark
 * onto classic's light header — and the preview, which described only
 * $_SESSION['s']['theme'], could not show that happening.
 */
t_ok('customizer_logo_surfaces_all() exists', function_exists('customizer_logo_surfaces_all'));
if (function_exists('customizer_logo_surfaces_all')) {
    $branding = array('logo_variant_nav' => 'on_dark');
    $all = customizer_logo_surfaces_all(array('clarity', 'classic'), $branding,
        array('nav' => 'Navigation', 'login' => 'Login screen'));

    $classic_nav = null;
    foreach ($all as $e) {
        if ($e['design'] === 'classic' && $e['surface'] === 'nav') $classic_nav = $e;
    }
    t_ok('classic\'s header is described even while the admin runs clarity', $classic_nav !== null);
    if ($classic_nav !== null) {
        t_eq('...on stock\'s real header colour', $classic_nav['bg'], '#F2F5F7');
        t_eq('...showing the forced mark the operator chose', $classic_nav['variant'], 'on_dark');
        t_ok('...labelled with the design, so two rows are tellable apart',
            stripos($classic_nav['label'], 'classic') !== false, $classic_nav['label']);
    }

    t_eq('one design alone is not design-labelled',
        customizer_logo_surfaces_all(array('clarity'), array(), array('nav' => 'Navigation'))[0]['label'], 'Navigation');
    t_eq('an unknown design contributes nothing',
        customizer_logo_surfaces_all(array('nosuchdesign'), array(), array()), array());
}

/* ---- which designs the panel actually has --------------------------------
 * install.sh deploys clarity, classic or both, so the preview must describe what
 * is deployed rather than everything this repository ships. ISPC_THEMES_PATH is
 * core's own constant and is defined here against a scratch tree so the check
 * exercises the real filesystem branch.
 */
t_ok('customizer_installed_designs() exists', function_exists('customizer_installed_designs'));
if (function_exists('customizer_installed_designs')) {
    $root = sys_get_temp_dir() . '/brandprobe_' . getmypid();
    @mkdir($root . '/clarity', 0700, true);
    define('ISPC_THEMES_PATH', $root);

    t_eq('only the deployed design is described', customizer_installed_designs('clarity'), array('clarity'));
    t_eq('a design that is not deployed is not described',
        customizer_installed_designs('clarity'), array('clarity'));

    @mkdir($root . '/classic', 0700, true);
    t_eq('both deployed designs are described, active first',
        customizer_installed_designs('clarity'), array('clarity', 'classic'));
    t_eq('the active design leads even when it is not the first known one',
        customizer_installed_designs('classic'), array('classic', 'clarity'));

    //* Stock ISPConfig, or any third-party theme: this extension describes the
    //* designs it ships and knows nothing about that one, which is the honest
    //* answer rather than a guess about someone else's chrome.
    t_eq('a stock or third-party active theme still lists what is deployed',
        customizer_installed_designs('default'), array('clarity', 'classic'));
    t_eq('a non-string active theme is not fatal',
        customizer_installed_designs(null), array('clarity', 'classic'));

    //* The active design is trusted only if it is one this extension ships, so a
    //* tampered session value can never become a path segment.
    t_ok('an arbitrary session theme is not admitted',
        !in_array('../../etc', customizer_installed_designs('../../etc'), true));

    @rmdir($root . '/clarity');
    @rmdir($root . '/classic');
    @rmdir($root);
}

/* ---- characterisation: the two shipped designs' chrome -------------------
 * These are the values the preview has always drawn. They pin the refactor
 * above: whatever changes, what the operator sees for clarity and classic on a
 * default install must not.
 */
$c = customizer_logo_surfaces('clarity', array());
t_eq('clarity: three swatches (nav, and login once per colour mode)', count($c), 3);
t_eq('clarity nav: the dark mark on the navy rail', $c[0], array('surface' => 'nav', 'label' => '', 'variant' => 'on_dark', 'bg' => '#01243D'));
t_eq('clarity login, dark mode', $c[1], array('surface' => 'login', 'label' => '', 'variant' => 'on_dark', 'bg' => '#17252B'));
t_eq('clarity login, light mode', $c[2], array('surface' => 'login', 'label' => '', 'variant' => 'on_light', 'bg' => '#F1F6F8'));

$k = customizer_logo_surfaces('classic', array());
t_eq('classic: two swatches', count($k), 2);
t_eq('classic nav: the light mark on stock\'s header', $k[0], array('surface' => 'nav', 'label' => '', 'variant' => 'on_light', 'bg' => '#F2F5F7'));
t_eq('classic login: the light mark on the panel heading', $k[1], array('surface' => 'login', 'label' => '', 'variant' => 'on_light', 'bg' => '#EEF0F2'));

t_eq('a rail_hex the operator set is what the nav swatch is drawn on',
    customizer_logo_surfaces('clarity', array('rail_hex' => '#FFFFFF'))[0],
    array('surface' => 'nav', 'label' => '', 'variant' => 'on_light', 'bg' => '#FFFFFF'));

t_eq('an unknown design is described as nothing rather than guessed at',
    customizer_logo_surfaces('nosuchdesign', array()), array());

/* ---- one resolver signature across all three copies ---------------------- */
t_eq('explicit choice beats a contradicting background',
    customizer_logo_variant_for_surface('on_dark', '#FFFFFF', 'on_light'), 'on_dark');
t_eq('an unrecognised stored value is automatic',
    customizer_logo_variant_for_surface('garbage', '#FFFFFF', 'on_dark'), 'on_light');
t_eq('a trailing newline does not make a hex valid',
    customizer_logo_variant_for_surface('', "#FF0000\n", 'on_dark'), 'on_dark');

$matrix = array();
foreach (h_variant_matrix() as $row) {
    $matrix[] = customizer_logo_variant_for_surface($row[0], $row[1], $row[2]);
}
echo 'MATRIX ' . json_encode($matrix) . "\n";

t_done();
