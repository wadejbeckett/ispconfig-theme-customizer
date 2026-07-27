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
 *   - restores the three core-owned dashboard_atom_url_* keys from the module's
 *     own [branding] news_url_* stash, so a purge never leaves the dashboard
 *     news feed switched off (must happen BEFORE [branding] is dropped)
 *   - drops the module-owned [branding] section from sys_ini.config, which takes
 *     the uploaded dark-background logo ([branding] logo_on_dark), the uploaded
 *     favicon ([branding] favicon) and every *_url reference with them
 *     (no live core code reads that section — its only consumer is a
 *     brand-aware theme; with the section gone each design's favicon.php serves
 *     that design's own shipped icon again, which is what it does on a panel
 *     that was never branded)
 *   - blanks the three core-owned [misc] keys (blank, never delete: they are
 *     stock fields of System > Interface Config)
 *   - clears sys_ini.custom_logo, the uploaded light-background logo
 *     (the theme/login fall back to their defaults)
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

//* Pin the connection charset to whatever the panel itself uses. ISPConfig's db class
//* issues SET NAMES $conf['db_charset'] (utf8mb4) on every connect; a raw mysqli
//* connection instead inherits mysqli.default_charset, which is utf8mb3 on older PHP
//* builds and latin1 where PHP links against libmysqlclient with a [client] setting in
//* my.cnf. That matters here more than anywhere else in this project: sys_ini.config is
//* a utf8mb4 column holding the WHOLE System > Interface Config blob — including values
//* the module has no business touching, such as [mail] admin_name and smtp_pass — and
//* this script reads that blob, edits three keys and writes all of it back. Under a
//* narrower connection charset MySQL substitutes a literal '?' for every character it
//* cannot represent on the way out, and that '?' is what we would persist. Same guard
//* the theme's brand.php endpoint already applies to its own raw mysqli connection.
$db_charset = !empty($conf['db_charset']) ? $conf['db_charset'] : 'utf8mb4';
if(!@$m->set_charset($db_charset) && $db_charset !== 'utf8mb4') {
    @$m->set_charset('utf8mb4');
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

//* News feed first, because the evidence needed to restore it lives inside the section
//* we are about to delete.
//*
//* The module's news-feed toggle does not own dashboard_atom_url_admin/_reseller/
//* _client — they are stock System > Interface Config fields — so switching the toggle
//* off stashes each non-empty URL into the module-owned [branding] keys news_url_admin/
//* _reseller/_client and then blanks the core keys (customizer_edit.php onUpdateSave).
//* Core gates the dashboard feed on a non-empty URL and has no render-time fallback, so
//* a purge that merely dropped [branding] would throw the stash away and leave the feed
//* dark for every role — on a panel the uninstaller has just reported as de-branded,
//* with the module UI that could flip it back already deleted.
//*
//* Restore per role, and only into a key that is still empty: empty + stashed is exactly
//* the state the module created, whereas a key holding a URL again has been set since
//* (by hand under System > Interface Config) and must not be overwritten. A role that
//* never had a URL has no stash and correctly stays empty — blanket-refilling with the
//* ISPConfig default would resurrect a feed the admin deliberately turned off before the
//* module existed, and re-leak ISPConfig branding to roles a white-label panel must not
//* show it to. Dropping [branding] below then removes the stash keys themselves.
$atom_stash = array(
    'dashboard_atom_url_admin'    => 'news_url_admin',
    'dashboard_atom_url_reseller' => 'news_url_reseller',
    'dashboard_atom_url_client'   => 'news_url_client',
);
//* $unrestorable must be collected HERE, while [branding] still exists — the
//* section (and with it the stash) is dropped a few lines below.
$unrestorable = array();
foreach($atom_stash as $k => $stash) {
    $has_stash = isset($config['branding'][$stash]) && $config['branding'][$stash] !== '';
    $is_empty  = !isset($config['misc'][$k]) || $config['misc'][$k] === '';
    if(!$is_empty) continue;              // already has a URL — never overwrite
    if($has_stash) {
        $config['misc'][$k] = $config['branding'][$stash];
        $did[] = "restored [misc] $k";
    } else {
        $unrestorable[] = $k;
    }
}

//* Report the uploaded dark-background logo separately, before the section that
//* holds it is dropped. Dropping [branding] does clear it — but "dropped
//* [branding] section" reads as settings housekeeping, and this is a visible
//* brand asset whose removal the operator must be able to see confirmed, exactly
//* as "cleared custom_logo" confirms the light-background one below. The two
//* uploaded logos live in different places purely because core has one column
//* and we may not add a second; the uninstall report should not make that
//* implementation detail the operator's problem.
//*
//* Checked for a non-empty value rather than mere presence, so an already-purged
//* panel does not claim to have removed something.
if(isset($config['branding']['logo_on_dark']) && $config['branding']['logo_on_dark'] !== '') {
    $did[] = "cleared dark-background logo ([branding] logo_on_dark)";
}

//* Same reasoning for the uploaded favicon, and the same stakes: it is the one
//* brand surface that renders on EVERY page including the login screen, so an
//* operator reading this report must see it named. Dropping [branding] below
//* clears it (and favicon_url with it), after which each design's favicon.php
//* falls back to its own shipped icon.
if(isset($config['branding']['favicon']) && $config['branding']['favicon'] !== '') {
    $did[] = "cleared uploaded favicon ([branding] favicon)";
}

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

//* Check the return value like every other write here does. mysqli_report(MYSQLI_REPORT_OFF)
//* above deliberately disables PHP 8.1's throw-on-error, so a failed query() is silent: an
//* InnoDB lock-wait timeout on sys_ini row 1 (an admin saving System > Interface Config
//* writes the same row), a read-only replica or a full disk would leave the logo in place
//* while we printed "cleared custom_logo" and exited 0. uninstall.sh runs with -e and
//* rm -rf's the module directory straight after us, so an unnoticed failure here deletes
//* the only UI that could have cleared the logo while it is still rendering on the stock
//* login page. Failing loudly instead aborts the uninstall with the module still installed.
if((int)$row['logo_len'] > 0) {
    if(!$m->query("UPDATE sys_ini SET custom_logo = '' WHERE sysini_id = 1")) {
        fwrite(STDERR, "ERROR: clearing custom_logo failed: " . $m->error . "\n");
        exit(1);
    }
    $did[] = "cleared custom_logo (light-background logo)";
}

if(count($did) === 0) {
    echo "  nothing to purge — no branding values were set\n";
} else {
    foreach($did as $d) echo "  - $d\n";
}

//* Panels branded before the stash existed (module <= v1.0.12) had their atom keys
//* blanked with nothing recorded, so there is nothing to restore — and an empty key
//* is indistinguishable from one the admin blanked by hand under System > Interface
//* Config long before the module was installed. Writing the ISPConfig default back
//* would resurrect a feed that may have been switched off deliberately, and re-leak
//* ISPConfig branding to roles a white-label panel exists to hide it from. So report
//* rather than guess: the operator knows their own history and can restore in one step.
if(count($unrestorable) > 0) {
    echo "\n";
    echo "  NOTE: the dashboard news feed is OFF for: " . implode(', ', str_replace('dashboard_atom_url_', '', $unrestorable)) . "\n";
    echo "        No saved URL was found for those roles, so nothing was restored. If the\n";
    echo "        module turned the feed off and you want ISPConfig's stock feed back, set\n";
    echo "        the relevant dashboard_atom_url_* field under System > Interface Config to:\n";
    echo "          https://www.ispconfig.org/atom\n";
    echo "        If you had already turned that feed off yourself, ignore this.\n";
}
$m->close();
