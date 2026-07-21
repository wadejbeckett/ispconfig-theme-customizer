<?php
/**
 * ispconfig-customizer — assign the 'customizer' module to admin users.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../LICENSE.
 *
 * The module only appears in the top navigation for users whose sys_user.modules
 * list contains its name. This adds it to every admin user, idempotently, so the
 * module is reachable right after install. Run by install.sh; safe to re-run.
 *
 * Usage: php assign_module.php [/usr/local/ispconfig/interface/lib/config.inc.php]
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

$res = $m->query("SELECT userid, username, modules FROM sys_user WHERE typ = 'admin'");
if(!$res) {
    fwrite(STDERR, "ERROR: query failed: " . $m->error . "\n");
    exit(1);
}

$changed = 0;
while($r = $res->fetch_assoc()) {
    $mods = array_values(array_filter(array_map('trim', explode(',', (string)$r['modules'])), 'strlen'));
    if(!in_array('customizer', $mods, true)) {
        $mods[] = 'customizer';
        $csv = implode(',', $mods);
        $uid = (int)$r['userid'];
        $stmt = $m->prepare("UPDATE sys_user SET modules = ? WHERE userid = ?");
        if(!$stmt) {
            fwrite(STDERR, "ERROR: prepare failed: " . $m->error . "\n");
            exit(1);
        }
        $stmt->bind_param('si', $csv, $uid);
        if(!$stmt->execute()) {
            fwrite(STDERR, "ERROR: update failed for user '" . $r['username'] . "': " . $stmt->error . "\n");
            exit(1);
        }
        $stmt->close();
        echo "  + assigned 'customizer' to admin user '" . $r['username'] . "'\n";
        $changed++;
    }
}
if($changed === 0) {
    echo "  all admin users already have the 'customizer' module\n";
}
$m->close();
