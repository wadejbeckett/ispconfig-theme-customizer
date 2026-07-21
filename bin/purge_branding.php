<?php
/**
 * ispconfig-customizer — wipe every branding value the module manages.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../LICENSE.
 *
 * A directory uninstall alone leaves the panel branded: [misc] company_name /
 * custom_login_text / custom_login_link and the custom_logo column are STOCK
 * core fields that keep rendering on the stock theme, and the [branding]
 * section keeps driving any brand-aware theme still installed. This script
 * resets all of it:
 *   - drops the module-owned [branding] section from sys_ini.config
 *     (no live core code reads it — its only consumer is a brand-aware theme)
 *   - blanks the three core-owned [misc] keys (blank, never delete: they are
 *     stock fields of System > Interface Config)
 *   - clears sys_ini.custom_logo (the theme/login fall back to their defaults)
 * Uses the framework ini_parser (loaded from the target install) so the
 * round-trip can never drift from what the panel itself writes. Direct UPDATE,
 * same as the module's own logo writes. Idempotent.
 *
 * Usage: php purge_branding.php [/usr/local/ispconfig/interface/lib/config.inc.php]
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

$ini_parser_path = dirname($conf_path) . '/classes/ini_parser.inc.php';
if(!is_readable($ini_parser_path)) {
    fwrite(STDERR, "ERROR: framework ini_parser not found at $ini_parser_path\n");
    exit(1);
}
require $ini_parser_path;
$parser = new ini_parser();

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

$res = $m->query("SELECT config, LENGTH(custom_logo) AS logo_len FROM sys_ini WHERE sysini_id = 1");
if(!$res || !($row = $res->fetch_assoc())) {
    fwrite(STDERR, "ERROR: could not read sys_ini row 1: " . $m->error . "\n");
    exit(1);
}

$config = $parser->parse_ini_string((string)$row['config']);
if(!is_array($config)) {
    fwrite(STDERR, "ERROR: sys_ini config did not parse — refusing to write\n");
    exit(1);
}

$did = array();

if(isset($config['branding'])) {
    unset($config['branding']);
    $did[] = "dropped [branding] section";
}
foreach(array('company_name', 'custom_login_text', 'custom_login_link') as $k) {
    if(isset($config['misc'][$k]) && $config['misc'][$k] !== '') {
        $config['misc'][$k] = '';
        $did[] = "blanked [misc] $k";
    }
}

if(count($did) > 0) {
    $config_str = $parser->get_ini_string($config);
    $stmt = $m->prepare("UPDATE sys_ini SET config = ? WHERE sysini_id = 1");
    if(!$stmt) {
        fwrite(STDERR, "ERROR: prepare failed: " . $m->error . "\n");
        exit(1);
    }
    $stmt->bind_param('s', $config_str);
    if(!$stmt->execute()) {
        fwrite(STDERR, "ERROR: update failed: " . $stmt->error . "\n");
        exit(1);
    }
    $stmt->close();
}

if((int)$row['logo_len'] > 0) {
    $m->query("UPDATE sys_ini SET custom_logo = '' WHERE sysini_id = 1");
    $did[] = "cleared custom_logo";
}

if(count($did) === 0) {
    echo "  nothing to purge — no branding values were set\n";
} else {
    foreach($did as $d) echo "  - $d\n";
}
$m->close();
