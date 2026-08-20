<?php
/**
 * Shared plumbing for the brand-reader tests.
 *
 * The three brand readers (themes/clarity/brand.php, themes/classic/brand.php,
 * interface/web/customizer/lib/preview.inc.php) each carry their own copy of the
 * logo-variant resolver and the luminance maths, and every one of them documents
 * that the copies must agree. Until now that agreement was asserted in prose and
 * audited by eye. These tests execute it.
 *
 * The two theme files are ENDPOINTS: including one emits headers, opens a
 * database connection and prints a stylesheet. Only the helper block at the
 * bottom is pure, and that block is what is under test, so load_helpers() slices
 * the file at its "Helpers — pure, dependency-free" marker and evals just that
 * tail. It is a slice of the real shipped file, not a copy: a helper that moves
 * above the marker leaves the tests, which is why the marker is asserted too.
 *
 * Both theme files define the same function NAMES, so each one has to be loaded
 * into a process of its own. run.php does that; the probes are the per-process
 * halves and each prints TAP-ish lines plus a JSON decision matrix that run.php
 * cross-compares.
 *
 * The contrast maths here is deliberately a SECOND implementation, written from
 * the WCAG 2.x definition rather than shared with the readers: a test that
 * verifies production maths by calling that same production maths proves only
 * that it equals itself.
 */

$GLOBALS['t_fail'] = 0;
$GLOBALS['t_pass'] = 0;

function t_ok($name, $cond, $detail = '') {
    if ($cond) {
        $GLOBALS['t_pass']++;
        echo "ok   $name\n";
        return true;
    }
    $GLOBALS['t_fail']++;
    echo "FAIL $name" . ($detail !== '' ? "  -- $detail" : '') . "\n";
    return false;
}

function t_eq($name, $got, $want) {
    return t_ok($name, $got === $want, 'got ' . var_export($got, true) . ', want ' . var_export($want, true));
}

function t_done() {
    echo sprintf("# %d passed, %d failed\n", $GLOBALS['t_pass'], $GLOBALS['t_fail']);
    exit($GLOBALS['t_fail'] > 0 ? 1 : 0);
}

/**
 * Eval the pure-helper tail of one brand reader.
 *
 * The eval() input is a slice of a file committed to THIS repository, named by a
 * constant path in the probe that calls it — never a request, an upload or
 * anything an operator can reach. That is the whole point: the code under test
 * has to be the shipped code, and the shipped file cannot be included outright
 * because its first 480 lines are a live endpoint. Nothing here runs on a panel;
 * tests/ is not installed by install.sh.
 *
 * Fails loudly rather than silently loading nothing: a renamed marker would
 * otherwise turn every test in the probe into a vacuous pass.
 */
function load_helpers($path) {
    $src = file_get_contents($path);
    if ($src === false) {
        fwrite(STDERR, "cannot read $path\n");
        exit(2);
    }
    $marker = 'Helpers — pure, dependency-free.';
    $at = strpos($src, $marker);
    if ($at === false) {
        fwrite(STDERR, "no helper marker in $path — the tests would load nothing\n");
        exit(2);
    }
    $end = strpos($src, '*/', $at);
    if ($end === false) {
        fwrite(STDERR, "unterminated helper banner in $path\n");
        exit(2);
    }
    eval(substr($src, $end + 2));
}

/** WCAG 2.x relative luminance, written from the spec (not from the readers). */
function h_lum($hex) {
    $c = ltrim($hex, '#');
    $y = 0.0;
    $w = array(0.2126, 0.7152, 0.0722);
    for ($i = 0; $i < 3; $i++) {
        $v = hexdec(substr($c, $i * 2, 2)) / 255;
        $v = ($v <= 0.03928) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
        $y += $w[$i] * $v;
    }
    return $y;
}

/** WCAG 2.x contrast ratio between two #rrggbb colours. */
function h_contrast($a, $b) {
    $la = h_lum($a);
    $lb = h_lum($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * Flatten a CSS colour onto an opaque backdrop and return #rrggbb.
 *
 * Accepts "#rrggbb" and "rgba(r, g, b, a)" — the only two forms the rail tokens
 * use. An rgba ink is what the shipped theme actually paints on the rail, so a
 * contrast assertion that ignored the alpha would be measuring a colour nobody
 * sees.
 */
function h_flatten($colour, $backdrop) {
    $colour = trim($colour);
    if (preg_match('/^#[0-9A-Fa-f]{6}$/', $colour)) {
        return strtoupper($colour);
    }
    if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $colour, $m)) {
        $a  = isset($m[4]) && $m[4] !== '' ? (float)$m[4] : 1.0;
        $bc = ltrim($backdrop, '#');
        $out = '#';
        for ($i = 0; $i < 3; $i++) {
            $fg = (float)$m[$i + 1];
            $bg = hexdec(substr($bc, $i * 2, 2));
            $out .= sprintf('%02X', (int)round($fg * $a + $bg * (1 - $a)));
        }
        return $out;
    }
    return '';
}

/** Parse "  --token: value;\n" lines into a token => value map. */
function h_decls($css) {
    $out = array();
    if (preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', (string)$css, $m, PREG_SET_ORDER)) {
        foreach ($m as $d) {
            $out[$d[1]] = trim($d[2]);
        }
    }
    return $out;
}

/**
 * The rail colours every ink assertion is run against.
 *
 * #767676 and #808080 straddle the crossover where neither pure white nor pure
 * black is comfortably readable — the worst case for the ink DIRECTION choice,
 * and the reason that rule cannot be a simple light/dark flip. #FFD700 and
 * #7FFF00 are bright but saturated, where lightness and luminance disagree most.
 *
 * #0970DC, #1F6FEB and #348346 are a different worst case: rails dark enough to
 * take the white ink, but light enough that a stratum tinted further toward
 * white pushes that ink under AA. They are ordinary brand blues and greens, not
 * exotica, and nothing in the first group exercises the stratum crossover.
 *
 * #FAFAFA, #F5F5F5 and #F8F8F8 are the near-whites an operator actually types
 * for a light sidebar, and they are where a lighter stratum has almost no
 * headroom left before it collapses into the rail.
 */
function h_rails() {
    return array('#01243D', '#000000', '#FFFFFF', '#767676', '#808080', '#BBBBBB',
                 '#FFD700', '#7FFF00', '#7F0000', '#0065AB', '#F2F5F7',
                 '#0970DC', '#1F6FEB', '#348346',
                 '#FAFAFA', '#F5F5F5', '#F8F8F8');
}

/** The (stored, bg_hex, default) grid every copy of the resolver must agree on. */
function h_variant_matrix() {
    $stored   = array('', 'on_light', 'on_dark', 'ON_DARK', 'garbage', '0', 'on_dark ');
    $bgs      = array('', '#FFFFFF', '#01243D', '#808080', "#FF0000\n", '#GGGGGG', '#FFF', 'red');
    $defaults = array('', 'on_light', 'on_dark');
    $out = array();
    foreach ($stored as $s) {
        foreach ($bgs as $b) {
            foreach ($defaults as $d) {
                $out[] = array($s, $b, $d);
            }
        }
    }
    return $out;
}
