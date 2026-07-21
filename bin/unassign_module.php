<?php
/**
 * ispconfig-customizer — remove the 'customizer' module from ALL users.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../LICENSE.
 *
 * Reverses bin/assign_module.php, but scans EVERY user (install assigns to all
 * admins, and an admin may have hand-assigned the module to others since):
 *   - strips 'customizer' from each sys_user.modules CSV
 *   - resets sys_user.startmodule to 'dashboard' where it points at customizer
 *     (a stale startmodule breaks the "login as" redirect after the module
 *     directory is gone)
 * Idempotent; run by uninstall.sh; safe to re-run.
 *
 * Usage: php unassign_module.php [/usr/local/ispconfig/interface/lib/config.inc.php]
 */

$conf_path = isset($argv[1]) ? $argv[1] : '/usr/local/ispconfig/interface/lib/config.inc.php';
if(!is_readable($conf_path)) {
    fwrite(STDERR, "ERROR: ISPConfig config not readable: $conf_path\n");
    exit(1);
}
require $conf_path;
if(!isset($conf) || !is_array($conf) || empty($conf['db_host'])) {
    fwrite(STDERR, "ERROR: no database configuration found in $conf_path\n");
    exit(1);
}

//* PHP 8.1+ makes mysqli throw by default; turn that off so the connect_errno /
//* error-return idiom below stays live and we can report failures cleanly.
if(function_exists('mysqli_report')) {
    mysqli_report(MYSQLI_REPORT_OFF);
}

$port = isset($conf['db_port']) ? (int)$conf['db_port'] : 3306;
try {
    $m = @new mysqli($conf['db_host'], $conf['db_user'], $conf['db_password'], $conf['db_database'], $port);
} catch(\Throwable $e) {
    $m = false;
}
if(!$m || $m->connect_errno) {
    fwrite(STDERR, "ERROR: database connection failed" . ($m ? ": " . $m->connect_error : "") . "\n");
    exit(1);
}

$res = $m->query("SELECT userid, username, modules, startmodule FROM sys_user");
if(!$res) {
    fwrite(STDERR, "ERROR: query failed: " . $m->error . "\n");
    exit(1);
}

$changed = 0;
while($r = $res->fetch_assoc()) {
    $mods = array_values(array_filter(array_map('trim', explode(',', (string)$r['modules'])), 'strlen'));
    $new_mods = array_values(array_filter($mods, function($x) { return $x !== 'customizer'; }));
    $new_start = ((string)$r['startmodule'] === 'customizer') ? 'dashboard' : (string)$r['startmodule'];

    if($new_mods !== $mods || $new_start !== (string)$r['startmodule']) {
        $csv = implode(',', $new_mods);
        $uid = (int)$r['userid'];
        $stmt = $m->prepare("UPDATE sys_user SET modules = ?, startmodule = ? WHERE userid = ?");
        if(!$stmt) {
            fwrite(STDERR, "ERROR: prepare failed: " . $m->error . "\n");
            exit(1);
        }
        $stmt->bind_param('ssi', $csv, $new_start, $uid);
        if(!$stmt->execute()) {
            fwrite(STDERR, "ERROR: update failed for user '" . $r['username'] . "': " . $stmt->error . "\n");
            exit(1);
        }
        $stmt->close();
        echo "  - removed 'customizer' from user '" . $r['username'] . "'"
           . (($new_start !== (string)$r['startmodule']) ? " (startmodule reset to dashboard)" : "") . "\n";
        $changed++;
    }
}
if($changed === 0) {
    echo "  no user had the 'customizer' module assigned\n";
}
echo "  NOTE: module lists are session-cached — active sessions see the change at next login\n";
$m->close();
