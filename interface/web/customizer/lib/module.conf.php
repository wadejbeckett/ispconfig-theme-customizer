<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 */

global $conf;

$module['name']      = 'customizer';                       // MUST equal this directory name

//* The dashboard "modules" dashlet translates the title with $app->lng(), which
//* never loads this module's language files — the raw key would render on the
//* tile, truncated to "top_men..". Stock modules dodge this because their keys
//* live in the core global language file, which a third-party module must not
//* edit. So resolve the title ourselves from lib/<lang>.lng (the same wordbook
//* nav.php loads); nav.php's later $app->lng() call passes already-translated
//* text through unchanged. The closure keeps the wordbook's $wb out of the
//* including scope (the dashlet includes this file inside a function that has
//* its own $wb).
$module['title'] = call_user_func(function () {
    $lang = isset($_SESSION['s']['language']) ? $_SESSION['s']['language'] : 'en';
    if(!preg_match('/^[a-z]{2}$/', $lang)) $lang = 'en';
    $file = __DIR__ . '/' . $lang . '.lng';
    if(!is_file($file)) $file = __DIR__ . '/en.lng';
    $wb = array();
    if(is_file($file)) include $file;
    //* fallback deliberately <= 8 chars: the dashboard dashlet truncates longer
    //* titles to 7 chars + '..'
    return isset($wb['top_menu_customizer']) ? $wb['top_menu_customizer'] : 'Branding';
});
$module['template']  = 'module.tpl.htm';                   // standard single-column layout
$module['startpage'] = 'customizer/customizer_edit.php?id=1';
$module['tab_width'] = '';
$module['order']     = '95';
$module['icon']      = 'icon icon-tools';

$items = array();
$items[] = array('title'   => 'Branding',
                 'target'  => 'content',
                 'link'    => 'customizer/customizer_edit.php?id=1',
                 'html_id' => 'customizer_edit');

$module['nav'][] = array('title' => 'White-label',
                         'open'  => 1,
                         'items' => $items);
unset($items);
