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
$module['title']     = 'top_menu_customizer';              // resolved from lib/lang/<lang>.lng
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
