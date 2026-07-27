<?php
/**
 * Reproduction for UPSTREAM-PATCHES.md #4 — "Saving any config tab silently
 * strips one backslash level from every value it does not edit".
 *
 * Safe to run anywhere: it touches no database and writes nothing. It loads
 * ISPConfig's own ini_parser and replays the exact code path that
 * interface/web/admin/system_config_edit.php takes.
 *
 * Usage:
 *   php config-escaping-repro.php [/path/to/ispconfig]
 *     path defaults to /usr/local/ispconfig
 */

$root = isset($argv[1]) ? rtrim($argv[1], '/') : '/usr/local/ispconfig';
$parser_file = $root . '/interface/lib/classes/ini_parser.inc.php';
if (!is_readable($parser_file)) {
    fwrite(STDERR, "Cannot read $parser_file\nPass your ISPConfig root as the first argument.\n");
    exit(2);
}
require_once $parser_file;
$p = new ini_parser();

/* db->quote() -> db->escape() -> mysqli_real_escape_string. addslashes matches
   it for the backslash/quote cases this bug is about. */
function quote_like($s) { return addslashes($s); }
function read_via_getconf($p, $blob) { return $p->parse_ini_string(stripslashes($blob)); }
function value($cfg, $sec, $key) { return isset($cfg[$sec][$key]) ? $cfg[$sec][$key] : '(absent)'; }

/* Step 1 — admin saves the Mail tab with a backslash in the relay password.
   tform_base::_encode():912 applies db->quote(), so the blob stores it escaped. */
$stored = "[mail]\nsmtp_pass=" . quote_like('pa\\ss') . "\n\n[misc]\ncompany_name=Acme\n";
echo "1. after saving System > Interface Config > Mail:\n";
echo "     column holds : " . trim(explode("\n", $stored)[1]) . "\n";
echo "     reads back as: " . value(read_via_getconf($p, $stored), 'mail', 'smtp_pass') . "   <- correct\n\n";

/* Step 2 — current behaviour. system_config_edit.php:143 reads via
   get_global_config() (which stripslashes the WHOLE blob), replaces only the
   edited section with tform-encoded values (:185), and writes everything back
   through get_ini_string() (:186), which re-escapes nothing. */
function core_save($p, $blob, $section, $fields) {
    $cfg = read_via_getconf($p, $blob);
    foreach ($fields as $k => $v) $cfg[$section][$k] = quote_like($v);
    return $p->get_ini_string($cfg);
}

echo "2. CURRENT — admin now saves the Misc tab three times:\n";
$b = $stored;
for ($i = 1; $i <= 3; $i++) {
    $b = core_save($p, $b, 'misc', array('company_name' => 'Acme ' . $i));
    printf("     save #%d -> smtp_pass reads back as: %s\n", $i, value(read_via_getconf($p, $b), 'mail', 'smtp_pass'));
}

/* Step 3 — proposed patch: parse the raw column, so pass-through sections keep
   their stored (escaped) form and the whole array stays consistently escaped. */
function patched_save($p, $blob, $section, $fields) {
    $cfg = $p->parse_ini_string($blob);   // no stripslashes
    foreach ($fields as $k => $v) $cfg[$section][$k] = quote_like($v);
    return $p->get_ini_string($cfg);
}

echo "\n3. PATCHED — identical three saves:\n";
$b2 = $stored;
for ($i = 1; $i <= 3; $i++) {
    $b2 = patched_save($p, $b2, 'misc', array('company_name' => 'Acme ' . $i));
    printf("     save #%d -> smtp_pass reads back as: %s\n", $i, value(read_via_getconf($p, $b2), 'mail', 'smtp_pass'));
}

echo "\n4. the edited value still round-trips correctly under the patch:\n";
echo "     company_name reads back as: " . value(read_via_getconf($p, $b2), 'misc', 'company_name') . "\n";

$broken = value(read_via_getconf($p, $b),  'mail', 'smtp_pass');
$fixed  = value(read_via_getconf($p, $b2), 'mail', 'smtp_pass');
echo "\ncurrent = $broken\npatched = $fixed\n";
exit(($broken !== 'pa\\ss' && $fixed === 'pa\\ss') ? 0 : 1);
