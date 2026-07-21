<?php
/**
 * CI guard: every <lang>_customizer.lng must carry the exact key set of the
 * English source, and every top-menu title must fit the dashboard dashlet's
 * 8-character truncation. Run from the repo root: php .github/scripts/lang_check.php
 */

function load_wb($file) {
    $wb = array();
    include $file;               // .lng files only assign $wb — no side effects
    return is_array($wb) ? $wb : array();
}

$dir = 'interface/web/customizer/lib/lang';
$en_keys = array_keys(load_wb("$dir/en_customizer.lng"));
sort($en_keys);

$fail = 0;

foreach (glob("$dir/*_customizer.lng") as $f) {
    $keys = array_keys(load_wb($f));
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
foreach (glob("interface/web/customizer/lib/*.lng") as $f) {
    $wb = load_wb($f);
    if (isset($wb['top_menu_customizer'])) {
        $len = function_exists('mb_strlen') ? mb_strlen($wb['top_menu_customizer'], 'UTF-8')
                                            : strlen($wb['top_menu_customizer']);
        if ($len > 8) {
            fwrite(STDERR, basename($f) . ": top_menu_customizer '" . $wb['top_menu_customizer']
                 . "' is $len chars (dashlet truncates > 8)\n");
            $fail = 1;
        }
    }
}

if (!$fail) echo "language files OK\n";
exit($fail);
