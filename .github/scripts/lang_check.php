<?php
/**
 * CI guard for the module's language files:  php .github/scripts/lang_check.php
 *
 * SECURITY — DO NOT CHANGE THIS: the .lng files are PHP, but this script parses
 * them as TEXT and never include()s / executes them. They arrive through
 * community translation pull requests, so include()ing one would be arbitrary
 * code execution inside CI. Everything below is regex over file_get_contents().
 * (The workflow separately runs `php -l` on every .lng, which only parses.)
 *
 * Three wordbooks ship and three different core code paths load them:
 *
 *   lib/<lang>.lng                  nav.php merges this on every page to
 *                                   translate the top-menu title, and
 *                                   lib/module.conf.php reads it directly to
 *                                   resolve the dashboard launcher tile title.
 *   lib/lang/<lang>.lng             auto-loaded by app.inc.php inside the module.
 *   lib/lang/<lang>_customizer.lng  the tform wordbook for the settings form.
 *
 * What is checked, and why each check exists:
 *
 *  1. KEY PARITY, per wordbook, against that wordbook's English source.
 *     ISPConfig SUBSTITUTES wordbooks, it does not merge them — app.inc.php
 *     loads <lang>.lng and only falls back to en.lng when the per-language file
 *     is ABSENT. A file that exists but omits a key therefore suppresses the
 *     English fallback entirely, $app->lng() returns the raw key name, and the
 *     key renders verbatim in the UI (e.g. "<em>no_logo_set_txt</em>" on the
 *     Branding page). Only the tform wordbook used to be checked, so a partial
 *     lib/lang/<lang>.lng could merge with a green CI run.
 *
 *  2. NAV-TITLE LENGTH. top_menu_customizer drives the top nav AND the dashboard
 *     launcher tile, and the core dashlet truncates > 8 chars to 7 + "..".
 *
 *  3. NAV-TITLE / KEY COLLISION in the flat wordbook — see that check for the
 *     mechanism; in short, nav.php looks the resolved title up a second time as
 *     if it were a key, so a title whose value is also a key in the same file
 *     can be silently rewritten into something the dashboard tile never sees.
 *
 * Every check asserts it actually had files to inspect, and all paths are
 * derived from __DIR__ rather than the process cwd. A guard that validates zero
 * files is worse than no guard: the green "Language key parity" step reads as
 * proof the translations are in parity, and both of the old glob()s were
 * relative, so running from any other directory (or renaming the module dir)
 * printed "language files OK" and exited 0 having checked nothing.
 */

//* .github/scripts -> .github -> repo root
$root     = dirname(__DIR__, 2);
$flat_dir = $root . '/interface/web/customizer/lib';
$lang_dir = $root . '/interface/web/customizer/lib/lang';

define('NAV_TITLE_KEY', 'top_menu_customizer');
define('NAV_TITLE_MAX', 8);

function lc_fail($msg) {
    fwrite(STDERR, "lang_check: $msg\n");
    exit(1);
}

function lc_read($file) {
    $src = @file_get_contents($file);
    if ($src === false) lc_fail("cannot read $file");
    return $src;
}

/**
 * glob() that refuses to hand back an empty set. An empty match means the tree
 * moved out from under this script, not that everything passed.
 */
function lc_glob($pattern, $label) {
    $files = glob($pattern);
    if (!is_array($files) || count($files) === 0) {
        lc_fail("$label: nothing matched $pattern — this guard would have "
              . "reported success without checking a single file");
    }
    return $files;
}

/**
 * Every key this wordbook assigns, in file order.
 *
 * Matches assignment keys only: `$wb['key'] =`. The trailing '=' means a literal
 * $wb['...'] appearing INSIDE a translated value is not counted as a key.
 */
function wb_keys($src) {
    preg_match_all('/\$wb\[\s*\'([^\']+)\'\s*\]\s*=/', $src, $m);
    return $m[1];
}

/**
 * The decoded value of one $wb key, or false if it is not a plain quoted
 * literal (concatenation, heredoc, interpolation, a function call).
 *
 * Callers MUST treat false as a FAILURE, never as "skip". The previous version
 * accepted only unescaped single-quoted values and returned null for everything
 * else, and the caller skipped those files — so `$wb['top_menu_customizer'] =
 * "Personalizzazione";` (legal PHP, passes `php -l`) silently disabled the
 * length guard while CI still printed "language files OK". Presence of the key
 * is the caller's business: check wb_keys() first, then this.
 */
function wb_value($src, $key) {
    $q = preg_quote($key, '/');

    //* Single-quoted: PHP recognises only \' and \\ as escapes inside '...',
    //* so un-escape exactly those two and nothing else, or the length comes out
    //* wrong (a literal backslash-n is two characters here, not a newline).
    if (preg_match('/\$wb\[\s*\'' . $q . '\'\s*\]\s*=\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*;/', $src, $m)) {
        return preg_replace('/\\\\([\'\\\\])/', '$1', $m[1]);
    }

    //* Double-quoted is equally legal PHP and translators do write it.
    if (preg_match('/\$wb\[\s*\'' . $q . '\'\s*\]\s*=\s*"((?:[^"\\\\]|\\\\.)*)"\s*;/', $src, $m)) {
        //* Interpolation cannot be resolved from text — report it as unparseable
        //* rather than measuring the source form and guessing.
        if (strpos($m[1], '$') !== false) return false;
        return stripcslashes($m[1]);
    }

    return false;
}

/**
 * Every file in $files must define exactly the key set of $ref_file.
 */
function check_parity($label, $ref_file, $files) {
    $ref_keys = wb_keys(lc_read($ref_file));
    if (!$ref_keys) {
        lc_fail("$label: reference wordbook " . basename($ref_file)
              . " defines no \$wb keys — parity against an empty set passes trivially");
    }
    sort($ref_keys);

    $fail = 0;
    foreach ($files as $f) {
        $src  = lc_read($f);

        //* wb_keys() recognises single-quoted keys only, which is the house style
        //* and what every shipped wordbook uses. $wb["key"] is legal PHP but
        //* invisible to a text parser, so name the real problem instead of
        //* reporting the whole file as mysterious key drift.
        if (preg_match('/\$wb\[\s*"/', $src)) {
            fwrite(STDERR, basename($f) . ": \$wb keys must be single-quoted — this "
                 . "checker parses the file as text and does not see \$wb[\"key\"]\n");
            $fail = 1;
        }

        $keys = wb_keys($src);
        sort($keys);
        $missing = array_diff($ref_keys, $keys);
        $extra   = array_diff($keys, $ref_keys);
        if ($missing || $extra) {
            fwrite(STDERR, basename($f) . ": key drift — missing[" . implode(', ', $missing)
                 . "] extra[" . implode(', ', $extra) . "]\n");
            $fail = 1;
        }
    }
    echo "$label: " . count($files) . " file(s) match " . basename($ref_file)
       . " (" . count($ref_keys) . " keys)\n";
    return $fail;
}

/**
 * top_menu_customizer must fit the dashboard launcher tile's 8-character budget.
 */
function check_nav_title_length($file, $src) {
    $keys = wb_keys($src);
    //* Absence is already a parity failure; don't report it twice.
    if (!in_array(NAV_TITLE_KEY, $keys, true)) return 0;

    $title = wb_value($src, NAV_TITLE_KEY);
    if ($title === false) {
        fwrite(STDERR, basename($file) . ": " . NAV_TITLE_KEY . " is not a plain quoted "
             . "literal, so its length cannot be measured — write it as '...' or \"...\" "
             . "on one statement, with no concatenation or interpolation\n");
        return 1;
    }

    $len = function_exists('mb_strlen') ? mb_strlen($title, 'UTF-8') : strlen($title);
    if ($len > NAV_TITLE_MAX) {
        fwrite(STDERR, basename($file) . ": " . NAV_TITLE_KEY . " '$title' is $len chars "
             . "(the dashboard launcher tile truncates > " . NAV_TITLE_MAX . " to 7 + '..')\n");
        return 1;
    }
    return 0;
}

// ---------------------------------------------------------------------------

if (!is_dir($flat_dir)) lc_fail("$flat_dir does not exist");
if (!is_dir($lang_dir)) lc_fail("$lang_dir does not exist");

//* Flat wordbook — nav.php merges these; module.conf.php reads them directly.
$flat_files = lc_glob("$flat_dir/*.lng", 'nav wordbook');

//* Module wordbook and tform wordbook share lib/lang/, so split them by suffix.
$tform_files  = lc_glob("$lang_dir/*_customizer.lng", 'tform wordbook');
$module_files = array();
foreach (lc_glob("$lang_dir/*.lng", 'module wordbook') as $f) {
    if (!preg_match('/_customizer\.lng$/', basename($f))) $module_files[] = $f;
}
if (!$module_files) lc_fail("module wordbook: $lang_dir contains only *_customizer.lng files");

$fail = 0;

$fail |= check_parity('tform wordbook',  "$lang_dir/en_customizer.lng", $tform_files);
$fail |= check_parity('module wordbook', "$lang_dir/en.lng",            $module_files);
$fail |= check_parity('nav wordbook',    "$flat_dir/en.lng",            $flat_files);

//* Both nav.php and module.conf.php resolve the module title from the flat
//* English wordbook, so its loss would degrade silently to a hardcoded fallback.
if (!in_array(NAV_TITLE_KEY, wb_keys(lc_read("$flat_dir/en.lng")), true)) {
    lc_fail("$flat_dir/en.lng does not define " . NAV_TITLE_KEY);
}

foreach ($flat_files as $f) {
    $src   = lc_read($f);
    $fail |= check_nav_title_length($f, $src);

    //* Core nav.php merges this wordbook and THEN calls $app->lng($module['title']),
    //* so the already-resolved title is looked up a second time as if it were a
    //* key. The shipped title is the literal 'Branding', which is also this
    //* wordbook's key for the sidenav item — harmless while the two are equal,
    //* but a translator who renders $wb['Branding'] into their language and
    //* leaves top_menu_customizer alone silently retitles the top rail, while
    //* the dashboard tile (resolved in module.conf.php, which never loads this
    //* wordbook through $app) keeps showing the untranslated title. Identity
    //* mappings pass; only a value-changing collision fails.
    $keys  = wb_keys($src);
    $title = wb_value($src, NAV_TITLE_KEY);
    if ($title === false || !in_array($title, $keys, true)) continue;
    $shadow = wb_value($src, $title);
    if ($shadow !== false && $shadow !== $title) {
        fwrite(STDERR, basename($f) . ": " . NAV_TITLE_KEY . " resolves to '$title', which "
             . "this file also defines as a key with a different value ('$shadow') — the top "
             . "rail would render '$shadow' while the dashboard tile still shows '$title'. "
             . "Translate " . NAV_TITLE_KEY . " to the same string, or give the sidenav item "
             . "a key name that no title can collide with.\n");
        $fail = 1;
    }
}

foreach ($module_files as $f) {
    $fail |= check_nav_title_length($f, lc_read($f));
}

if (!$fail) echo "language files OK\n";
exit($fail ? 1 : 0);
