<?php
/**
 * The brand readers' test suite.
 *
 *   php tests/brand/run.php
 *
 * Exits 0 only if every probe passes AND the three copies of the logo-variant
 * resolver agree on every input.
 *
 * Three files carry that resolver — themes/clarity/brand.php,
 * themes/classic/brand.php and interface/web/customizer/lib/preview.inc.php.
 * They are duplicated on purpose: the theme endpoints run PRE-AUTH and must keep
 * working with the module uninstalled, so they cannot include its code. Every
 * copy's docblock says the three must decide alike, because a copy that
 * disagrees makes the module's preview promise a mark the panel will not render
 * — the one thing that preview exists to prevent.
 *
 * Until now "must decide alike" was prose. Each probe emits its decision over a
 * shared (stored, background, default) grid and this runner diffs them, so the
 * audit is executed on every push instead of being performed by eye.
 *
 * Each probe runs in a process of its own because clarity and classic define the
 * same function names.
 */

// The three resolver probes emit a decision matrix and are cross-compared below.
$probes = array(
    'clarity' => __DIR__ . '/probe_clarity.php',
    'classic' => __DIR__ . '/probe_classic.php',
    'module'  => __DIR__ . '/probe_module.php',
);

// The render probes execute each design's endpoint BODY — the part that decides
// which helper to call with what — and emit no matrix.
$renders = array(
    'render:clarity' => array(__DIR__ . '/probe_render.php', 'clarity'),
    'render:classic' => array(__DIR__ . '/probe_render.php', 'classic'),
);

// Probes that assert STRUCTURE rather than a decision: they emit no matrix and
// take no argument. Kept separate from $probes so run.php never demands a
// decision matrix from a file that has no decision to report.
$checks = array(
    'tform'   => __DIR__ . '/probe_tform.php',
    'preview' => __DIR__ . '/probe_preview.php',
    'page'    => __DIR__ . '/probe_page.php',
    'frozen'  => __DIR__ . '/probe_frozen.php',
);

$fail      = 0;
$matrix    = array();
$inkmatrix = array();
$php       = PHP_BINARY !== '' ? PHP_BINARY : 'php';

foreach ($probes as $name => $path) {
    echo "\n== $name ==\n";
    $out = array();
    $rc  = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    foreach ($out as $line) {
        if (strpos($line, 'INKMATRIX ') === 0) {
            $rows = json_decode(substr($line, 10), true);
            if (is_array($rows) && $rows) { $inkmatrix[$name] = $rows; }
            continue;
        }
        if (strpos($line, 'MATRIX ') === 0) {
            $rows = json_decode(substr($line, 7), true);
            if (is_array($rows) && $rows) { $matrix[$name] = $rows; }
            continue;
        }
        echo "$line\n";
    }
    if ($rc !== 0) {
        $fail++;
        echo "-- $name probe exited $rc\n";
    }
    if (!isset($matrix[$name])) {
        $fail++;
        echo "-- $name emitted no decision matrix\n";
    }
}

foreach ($renders as $name => $spec) {
    echo "\n== $name ==\n";
    $out = array();
    $rc  = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($spec[0]) . ' ' . escapeshellarg($spec[1]) . ' 2>&1', $out, $rc);
    foreach ($out as $line) {
        echo "$line\n";
    }
    if ($rc !== 0) {
        $fail++;
        echo "-- $name probe exited $rc\n";
    }
}

foreach ($checks as $name => $path) {
    echo "\n== $name ==\n";
    $out = array();
    $rc  = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    foreach ($out as $line) {
        echo "$line\n";
    }
    if ($rc !== 0) {
        $fail++;
        echo "-- $name probe exited $rc\n";
    }
}

echo "\n== resolver parity ==\n";
if (count($matrix) === count($probes)) {
    $ref = null;
    $refname = '';
    foreach ($matrix as $name => $rows) {
        if ($ref === null) { $ref = $rows; $refname = $name; continue; }
        if ($rows === $ref) {
            echo "ok   $name decides identically to $refname on all " . count($ref) . " inputs\n";
            continue;
        }
        $fail++;
        echo "FAIL $name disagrees with $refname\n";
        $shown = 0;
        foreach ($ref as $i => $want) {
            $got = isset($rows[$i]) ? $rows[$i] : null;
            if ($got === $want) continue;
            if ($shown++ >= 8) { echo "     ... and more\n"; break; }
            echo sprintf("     input #%d: %s says %s, %s says %s\n",
                $i, $refname, var_export($want, true), $name, var_export($got, true));
        }
    }
} else {
    echo "SKIP not every probe reported\n";
}

echo "\n== rail-ink parity ==\n";
/* Opt-in, unlike the resolver matrix: classic derives its rail ink from stock's
 * own ink colour rather than from black, so it has no comparable direction to
 * report and emits no INKMATRIX. Two emitters is the minimum for a comparison
 * to mean anything, so fewer is a failure rather than a skip — that is how a
 * renamed probe silently stops being compared. */
if (count($inkmatrix) < 2) {
    $fail++;
    echo "FAIL fewer than two probes reported a rail-ink matrix (" . count($inkmatrix) . ")\n";
} else {
    $ref = null;
    $refname = '';
    foreach ($inkmatrix as $name => $rows) {
        if ($ref === null) { $ref = $rows; $refname = $name; continue; }
        if ($rows === $ref) {
            echo "ok   $name chooses the same ink direction as $refname on all " . count($ref) . " rails\n";
            continue;
        }
        $fail++;
        echo "FAIL $name disagrees with $refname about the ink direction\n";
        foreach ($ref as $i => $want) {
            $got = isset($rows[$i]) ? $rows[$i] : null;
            if ($got === $want) continue;
            echo sprintf("     rail #%d: %s says %s, %s says %s\n",
                $i, $refname, var_export($want, true), $name, var_export($got, true));
        }
    }
}

echo "\n";
if ($fail > 0) {
    echo "BRAND SUITE FAILED ($fail)\n";
    exit(1);
}
echo "brand suite passed\n";
exit(0);
