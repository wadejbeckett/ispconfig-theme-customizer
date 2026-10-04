<?php
/**
 * Load the installed panel's core, including when this module is a symlink.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../../../../LICENSE.
 *
 * PHP may resolve the script's working directory into the external clone.
 * Relative ../../lib paths then address the clone, not the installed panel.
 * Only use server-provided paths, and require that the candidate panel actually
 * contains this module. Resolve a symlinked document root before taking its
 * parent; strip SCRIPT_FILENAME lexically before resolving its module symlink.
 */
$_customizer_roots = array();
if (!empty($_SERVER['DOCUMENT_ROOT'])) {
    $_customizer_web = realpath($_SERVER['DOCUMENT_ROOT']);
    if ($_customizer_web !== false) $_customizer_roots[] = dirname($_customizer_web);
}
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $_customizer_roots[] = dirname($_SERVER['SCRIPT_FILENAME'], 3);
}
// Copy installs and CLI invocations can also locate core from this file.
$_customizer_roots[] = dirname(__DIR__, 3);
$_customizer_core = false;
foreach ($_customizer_roots as $_customizer_root) {
    $_customizer_root = realpath($_customizer_root);
    if ($_customizer_root === false
        || realpath($_customizer_root . '/web/customizer') !== dirname(__DIR__)) {
        continue;
    }
    // A matched installation is authoritative. If incomplete, do not fall
    // through to a different config next to the external clone.
    if (is_readable($_customizer_root . '/lib/config.inc.php')
        && is_readable($_customizer_root . '/lib/app.inc.php')) {
        $_customizer_core = $_customizer_root . '/lib';
    }
    break;
}
if ($_customizer_core === false) {
    error_log('Customizer bootstrap: cannot locate readable ISPConfig core files for this module.');
    http_response_code(500);
    exit('Unable to load ISPConfig. Check the panel error log and module installation.');
}

// Include at top level: core defines $conf and $app for the endpoint's scope.
require_once $_customizer_core . '/config.inc.php';
require_once $_customizer_core . '/app.inc.php';
unset($_customizer_roots, $_customizer_web, $_customizer_root, $_customizer_core);
