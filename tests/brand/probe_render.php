<?php
/**
 * End-to-end: the stylesheet each design really emits, for a given [branding].
 *
 * The other probes load only the helper tail, so nothing there executes the
 * endpoint BODY — the part that decides which helper to call with what, and the
 * part an edit is most likely to break in a way php -l cannot see (a renamed
 * local, an argument dropped from a call, a branch that no longer runs).
 *
 * The body cannot simply be included: the first ~195 lines locate ISPConfig's
 * config.inc.php and read sys_ini over mysqli. So this slices the file into its
 * two pure halves — the helpers, and everything from "resolve + validate the
 * contract values" up to `echo $css;` — and runs the second with $branding,
 * $custom_logo and $company_name supplied directly. That is exactly the state
 * the DB block leaves behind on a real request, so what is rendered here is what
 * the panel serves.
 *
 * Run through tests/brand/run.php, which gives each design a process of its own.
 */

require_once __DIR__ . '/harness.php';

$design = isset($argv[1]) ? $argv[1] : 'clarity';
$path   = __DIR__ . '/../../themes/' . $design . '/brand.php';

/**
 * Render one design's sheet for a given contract.
 *
 * $scene is classic's ?scene= surface selector; clarity ignores it and emits
 * both surfaces in one sheet.
 */
function render($path, $branding, $custom_logo = '', $company_name = '', $scene = '') {
    $src = file_get_contents($path);

    $from = strpos($src, '/* ---- resolve + validate the contract values ---- */');
    $to   = strpos($src, 'echo $css;');
    if ($from === false || $to === false || $to <= $from) {
        fwrite(STDERR, "cannot slice the endpoint body out of $path\n");
        exit(2);
    }
    $body = substr($src, $from, $to - $from);

    // Run the body in a function scope with only the variables the DB block
    // would have left set, so a body that reaches for anything else fails here
    // rather than on a panel.
    $run = function () use ($body, $branding, $custom_logo, $company_name, $scene) {
        // phpcs:ignore -- see load_helpers() in harness.php: this is a slice of a
        // file committed to this repository, named by a constant path.
        eval($body);
        return isset($css) ? $css : '';
    };
    return $run();
}

load_helpers($path);

/**
 * Record PHP diagnostics rather than hoping they appear in the returned string.
 *
 * The first version of this probe asserted "no warning in the sheet" by
 * searching the returned CSS — which catches nothing: at display_errors=Off (the
 * CLI default here, and php.ini-production's) a warning goes to stderr and never
 * reaches $css at all. Injecting an undefined variable into the endpoint body
 * produced 87 PHP warnings and the probe stayed green.
 *
 * That matters more here than in most code. These endpoints emit text/css, so a
 * diagnostic printed into the response corrupts every rule after it — which is
 * the reason half the guards in both brand.php files exist.
 */
$GLOBALS['t_diag'] = array();
set_error_handler(function ($no, $str, $file, $line) {
    $GLOBALS['t_diag'][] = "$str ($file:$line)";
    return true;
});

//* Nothing set at all: the sheet must be a no-op so an unbranded panel keeps its
//* shipped tokens and its shipped logo.
$empty = render($path, array());
t_ok("$design: an empty contract emits no rules", strpos($empty, '{') === false, $empty);

//* Every emitted sheet has to be CSS a browser will not choke on. Counting
//* braces is crude and that is the point — it catches an unterminated block,
//* which is how a stray PHP warning in a text/css response corrupts every rule
//* after it.
function css_balanced($css) {
    return substr_count($css, '{') === substr_count($css, '}');
}

$cases = array(
    'accent only'          => array('accent_hex' => '#0065AB'),
    'rail only, dark'      => array('rail_hex' => '#01243D'),
    'rail only, white'     => array('rail_hex' => '#FFFFFF'),
    'rail only, mid grey'  => array('rail_hex' => '#767676'),
    'rail + accent'        => array('rail_hex' => '#FFD700', 'accent_hex' => '#7F0000'),
    'login bg'             => array('login_bg' => '#101010'),
    'forced nav variant'   => array('rail_hex' => '#FFFFFF', 'logo_variant_nav' => 'on_dark'),
    'forced login variant' => array('logo_variant_login' => 'on_light'),
    'hide version'         => array('show_version' => '0'),
    'no credits'           => array('show_ispconfig_credit' => '0', 'show_theme_credit' => '0'),
);

$png  = 'data:image/png;base64,iVBORw0KGgo=';
$png2 = 'data:image/png;base64,iVBORw0KGgoAAA=';

foreach ($cases as $name => $branding) {
    foreach (array(
        'no logo'        => array('', array()),
        'one logo'       => array($png, array()),
        'both logos'     => array($png, array('logo_on_dark' => $png2)),
        'dark logo only' => array('', array('logo_on_dark' => $png2)),
    ) as $logoname => $logo) {
        $b = array_merge($branding, $logo[1]);
        foreach (array('', 'login') as $scene) {
            $GLOBALS['t_diag'] = array();
            $css = render($path, $b, $logo[0], 'Acme Hosting', $scene);
            $label = "$design: $name / $logoname" . ($scene !== '' ? " / $scene" : '');
            t_ok("$label renders balanced CSS", css_balanced($css));
            t_ok("$label raises no PHP diagnostic",
                $GLOBALS['t_diag'] === array(), implode(' | ', $GLOBALS['t_diag']));
            //* Only <br is searched in the OUTPUT: it is what an html_errors=On
            //* diagnostic looks like once it lands in the body. The words
            //* "warning" and "notice" are deliberately NOT searched — the panel's
            //* own name is interpolated into this sheet as a CSS wordmark, and
            //* "Notice Board Hosting" is a legitimate company_name.
            t_ok("$label emits no markup into the sheet", stripos($css, '<br') === false);
        }
    }
}

/* ---- the rail ink actually reaches the sheet -----------------------------
 * Gated on the CAPABILITY, not the design name: a design either has a rail to
 * recolour or it does not, and keying off the name means a copy of the file
 * under another name silently runs none of the assertions below — which is
 * exactly how a mutation test comes back falsely clean.
 */
if (function_exists('brand_rail_vars')) {
    //* Both call sites: rail alone, and rail alongside an accent ramp. The
    //* accent branch is the one that nearly went unfixed — it builds its own
    //* :root block and used to call brand_rail_vars() without the accent.
    foreach (array(
        'rail alone' => array('rail_hex' => '#FFFFFF'),
        'rail + accent' => array('rail_hex' => '#FFFFFF', 'accent_hex' => '#0065AB'),
    ) as $name => $b) {
        $css = render($path, $b);
        foreach (array('--nz-rail-text', '--nz-rail-heading', '--nz-rail-accent') as $tok) {
            t_ok("clarity: $name emits $tok", strpos($css, $tok . ':') !== false);
        }
    }

    //* A white rail must not leave white ink anywhere in the rail family — the
    //* whole bug in one assertion.
    $css = render($path, array('rail_hex' => '#FFFFFF'));
    if (preg_match('/--nz-rail-text:\s*([^;]+);/', $css, $m)) {
        $c = h_contrast(h_flatten(trim($m[1]), '#FFFFFF'), '#FFFFFF');
        t_ok(sprintf('clarity: a white rail gets ink that reads (%.2f:1)', $c), $c >= 4.5, trim($m[1]));
    } else {
        t_ok('clarity: a white rail emits --nz-rail-text', false);
    }

    //* The shipped navy still emits exactly the tokens.css values.
    $navy = render($path, array('rail_hex' => '#01243D'));
    t_ok('clarity: navy still emits the shipped text ink',
        strpos($navy, '--nz-rail-text: rgba(255, 255, 255, 0.88);') !== false);

    //* The light-mode login filter — the extracted decision, seen through the
    //* sheet rather than through the helper.
    $both = render($path, array('logo_on_dark' => $png2), $png);
    t_ok('clarity: two stored variants let the light login mark keep its colours',
        strpos($both, 'filter: none;') !== false);
    $one = render($path, array(), $png);
    t_ok('clarity: one stored variant keeps the rescue halo',
        strpos($one, 'drop-shadow') !== false && strpos($one, 'filter: none;') === false);
}

/* ---- a one-surface-per-request design honours the operator --------------- */
if (!function_exists('brand_rail_vars')) {
    //* The bug this whole exercise started from: a forced on_dark, chosen to
    //* rescue a recoloured clarity rail, paints the light-artwork slot's
    //* alternative onto stock's light header. classic must still DO what it is
    //* told — the escape hatch is absolute by design — so what is asserted here
    //* is that the instruction is obeyed, which is what makes showing the
    //* operator its consequence (customizer_logo_surfaces_all) the real fix.
    $css = render($path, array('logo_variant_nav' => 'on_dark', 'logo_on_dark' => $png2), $png, '', '');
    t_ok('classic: a forced nav variant reaches the header rule',
        strpos($css, $png2) !== false, substr($css, 0, 200));

    $css = render($path, array('logo_variant_login' => 'on_dark', 'logo_on_dark' => $png2), $png, '', 'login');
    t_ok('classic: a forced login variant reaches the login rule',
        strpos($css, $png2) !== false, substr($css, 0, 200));

    //* Automatic on classic stays on the light-background mark, which is the one
    //* core itself is already painting, so nothing is emitted for it.
    $css = render($path, array(), $png, '', '');
    t_ok('classic: automatic leaves core\'s own logo alone', strpos($css, 'background-image') === false);
}

t_done();
