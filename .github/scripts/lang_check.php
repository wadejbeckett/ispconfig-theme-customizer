<?php
/**
 * CI guard: every <lang>_customizer.lng must carry the exact key set of the
 * English source, and every top-menu title must fit the dashboard dashlet's
 * 8-character truncation. Run from the repo root: php .github/scripts/lang_check.php
 *
 * The .lng files are PHP, but this script parses them as TEXT and never
 * include()s / executes them — a translation pull request must not be able to
 * run code inside CI. (Separately, the workflow lints every .lng with `php -l`,
 * which only parses, never executes.)
 */

function wb_keys($file) {
    $src = @file_get_contents($file);
    if ($src === false) return array();
    // Match assignment keys only: `$wb['key'] =`. The trailing '=' means a
    // literal $wb['...'] appearing INSIDE a translated value is not counted.
    preg_match_all('/\$wb\[\s*\'([^\']+)\'\s*\]\s*=/', $src, $m);
    return $m[1];
}

function wb_value($file, $key) {
    $src = @file_get_contents($file);
    if ($src === false) return null;
    // Simple (non-escaped) values only — sufficient for top_menu_customizer.
    $pat = '/\$wb\[\s*\'' . preg_quote($key, '/') . '\'\s*\]\s*=\s*\'([^\']*)\'/';
    return preg_match($pat, $src, $m) ? $m[1] : null;
}

$dir = 'interface/web/customizer/lib/lang';
$en_keys = wb_keys("$dir/en_customizer.lng");
sort($en_keys);

$fail = 0;

foreach (glob("$dir/*_customizer.lng") as $f) {
    $keys = wb_keys($f);
    sort($keys);
    $missing = array_diff($en_keys, $keys);
    $extra   = array_diff($keys, $en_keys);
    if ($missing || $extra) {
        fwrite(STDERR, basename($f) . ": key drift — missing[" . implode(', ', $missing)
             . "] extra[" . implode(', ', $extra) . "]\n");
        $fail = 1;
    } else {
        echo basename($f) . ": OK (" . count($keys) . " keys)\n";
    }
}

//* top_menu_customizer drives the top nav AND the dashboard launcher tile,
//* which truncates > 8 chars to 7 + "..". Guard every nav-title file.
foreach (glob('interface/web/customizer/lib/*.lng') as $f) {
    $v = wb_value($f, 'top_menu_customizer');
    if ($v !== null) {
        $len = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
        if ($len > 8) {
            fwrite(STDERR, basename($f) . ": top_menu_customizer '$v' is $len chars "
                 . "(dashlet truncates > 8)\n");
            $fail = 1;
        }
    }
}

if (!$fail) echo "language files OK\n";
exit($fail);
