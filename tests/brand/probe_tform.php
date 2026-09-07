<?php
/**
 * interface/web/customizer/form/customizer.tform.php — the validators, driven.
 *
 * The form definition is a plain array assignment with no dependencies, so it
 * can be require()d into a function scope and inspected. Two things are asserted
 * and they are different in kind:
 *
 *   STRUCTURE — that each colour field declares the SAVE-time TRIM filter and
 *   that its pattern carries /D. Both are load-bearing and neither is visible in
 *   behaviour until the day it is missing: without TRIM a pasted trailing space
 *   fails an anchored pattern with an opaque error on a field that looks
 *   correct; without /D a trailing newline VALIDATES and breaks later, off this
 *   page, as a bare 'n' glued to the value.
 *
 *   BEHAVIOUR — that each pattern accepts and rejects what it should, driven
 *   exactly as core drives it: tform_base::validateField() appends 's' to the
 *   regex (tform_base.inc.php:1011) and tform_base::filterField() implements
 *   TRIM as trim(). Those two lines are emulated here, and nowhere else, so the
 *   emulation is one place to correct if core ever changes.
 */

require_once __DIR__ . '/harness.php';

function tform_def() {
    $form = array();
    require __DIR__ . '/../../interface/web/customizer/form/customizer.tform.php';
    return $form;
}

$form = tform_def();
t_ok('the form definition loads', isset($form['tabs']['branding']['fields']));
$fields = $form['tabs']['branding']['fields'];

//* The redesign adds exactly one field and changes no structural key.
t_eq('db_table is untouched', $form['db_table'], 'sys_ini');
t_eq('tab_default is untouched', $form['tab_default'], 'branding');
t_ok('rail_hex_light exists', isset($fields['rail_hex_light']));
t_eq('rail_hex_light is a plain TEXT field',
    isset($fields['rail_hex_light']['formtype']) ? $fields['rail_hex_light']['formtype'] : null, 'TEXT');

/** Does this field declare a SAVE-time filter of this type? */
function has_filter($field, $type) {
    if (!isset($field['filters']) || !is_array($field['filters'])) return false;
    foreach ($field['filters'] as $f) {
        if (isset($f['event'], $f['type']) && $f['event'] === 'SAVE' && $f['type'] === $type) return true;
    }
    return false;
}

/** The one REGEX pattern a field declares, or '' — as core will run it. */
function regex_of($field) {
    if (!isset($field['validators']) || !is_array($field['validators'])) return '';
    foreach ($field['validators'] as $v) {
        if (isset($v['type']) && $v['type'] === 'REGEX') return $v['regex'] . 's'; // tform_base.inc.php:1011
    }
    return '';
}

/** filterField()'s TRIM, applied the way core applies it. */
function filtered($field, $value) {
    return has_filter($field, 'TRIM') ? trim($value) : $value;
}

$colours = array('accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg');

foreach ($colours as $name) {
    if (!t_ok("$name is declared", isset($fields[$name]))) continue;
    $f = $fields[$name];

    t_ok("$name declares the SAVE-time TRIM filter", has_filter($f, 'TRIM'));
    $re = regex_of($f);
    t_ok("$name declares a REGEX validator", $re !== '');
    t_ok("$name's pattern carries /D", strpos($re, 'D') !== false, $re);
    t_ok("$name names an errmsg key", isset($f['validators'][0]['errmsg'])
        && $f['validators'][0]['errmsg'] === $name . '_error_regex',
        isset($f['validators'][0]['errmsg']) ? $f['validators'][0]['errmsg'] : 'none');

    if ($re === '') continue;
    foreach (array(
        ''             => true,   // blank means "keep the design's own value"
        '#0065AB'      => true,
        '#0065ab'      => true,
        '#0065AB '     => true,   // a pasted trailing space — TRIM makes this pass
        "#0065AB\n"    => true,   // a pasted trailing newline — TRIM removes it
        '0065AB'       => false,  // no '#': repaired in onBeforeUpdate, rejected here
        '#0065A'       => false,
        '#0065ABC'     => false,
        'red'          => false,
        '#0065AB;x'    => false,
    ) as $input => $want) {
        $got = (preg_match($re, filtered($f, (string)$input)) === 1);
        t_eq("$name " . var_export((string)$input, true) . ' is ' . ($want ? 'accepted' : 'rejected'), $got, $want);
    }
}

//* The /D is what makes the newline case above a TRIM result rather than a
//* PCRE accident: with the filter removed the raw pattern must still reject it.
foreach ($colours as $name) {
    if (!isset($fields[$name])) continue;
    $re = regex_of($fields[$name]);
    if ($re === '') continue;
    t_ok("$name's pattern rejects a trailing newline on its own",
        preg_match($re, "#0065AB\n") !== 1);
}

//* The reference fields already carried both; assert it so a future edit that
//* "tidies" one of the six copies is caught here too.
foreach (array('logo_url', 'logo_url_on_dark', 'favicon_url') as $name) {
    if (!t_ok("$name is declared", isset($fields[$name]))) continue;
    t_ok("$name keeps its SAVE-time TRIM filter", has_filter($fields[$name], 'TRIM'));
    t_ok("$name keeps /D", strpos(regex_of($fields[$name]), 'D') !== false);
}

t_done();
