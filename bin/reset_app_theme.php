<?php
/**
 * Clarity Theme for ISPConfig — reset users' theme choice back to 'default'.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../LICENSE.
 *
 * After the theme directory is removed, ISPConfig does NOT heal the
 * sys_user.app_theme column: affected users get a "chosen theme is not
 * compatible" error banner at EVERY login (core only falls back at session
 * level). This flips every 'clarity' row back to 'default'. Idempotent;
 * run by uninstall.sh; safe to re-run.
 *
 * Usage: php reset_app_theme.php [/usr/local/ispconfig/interface/lib/config.inc.php]
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

//* MYSQLI_REPORT_OFF means prepare() returns false instead of throwing —
//* guard it or the next line fatals mid-uninstall with no useful message
$stmt = $m->prepare("UPDATE sys_user SET app_theme = 'default' WHERE app_theme = 'clarity'");
if(!$stmt) {
    fwrite(STDERR, "ERROR: prepare failed: " . $m->error . "\n");
    exit(1);
}
if(!$stmt->execute()) {
    fwrite(STDERR, "ERROR: update failed: " . $stmt->error . "\n");
    exit(1);
}
$n = $stmt->affected_rows;
$stmt->close();

if($n > 0) {
    echo "  - reset app_theme to 'default' for $n user(s)\n";
} else {
    echo "  no user had app_theme = 'clarity'\n";
}
$m->close();
