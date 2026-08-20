<?php
/**
 * ispconfig-customizer — dashboard dashlets the operator can switch off.
 * Copyright (c) 2026 Wade Beckett. GPLv3 — see ../../LICENSE.
 *
 * Required by customizer_edit.php.
 *
 * ---- WHY THIS DRIVES CORE'S OWN STATE ------------------------------------
 * Every other visibility switch in this module is CSS: show_version and the two
 * footer credits are rules emitted by the designs' brand.php, because the things
 * they hide are woven into templates a theme may not edit.
 *
 * The donation dashlet is different, and better. ISPConfig already has a
 * first-class mechanism for hiding it — the "Hide" button in the dashlet writes
 * a timeout into sys_config, and the dashboard consults that timeout before it
 * even builds the dashlet:
 *
 *   dashboard.php:37-47   ?hide=donate -> UPDATE/INSERT sys_config
 *                         ('interface', 'hide_donation_dashlet', time()+31536000)
 *   dashboard.php:222-228 admin only, and only when no unexpired row exists:
 *                         array_unshift($leftcol_dashlets, 'donate')
 *
 * Writing that row is therefore working WITH the panel rather than papering over
 * it: the dashlet is never built, never queried, never sent to the browser, and
 * core's own Hide button keeps doing exactly what it always did. A CSS rule
 * would have shipped the markup to every admin page load and hidden it after the
 * fact, and it would have fought the button instead of agreeing with it.
 *
 * ---- WHO SEES THIS AT ALL ------------------------------------------------
 * dashboard.php:223 gates the dashlet on is_admin(), and dashlets/donate.php
 * gates it a second time. It never reaches a reseller or a client. So this
 * switch is NOT a white-label control in the sense the credit and version
 * switches are — nobody's customer is being shown an ISPConfig donation appeal.
 * It only decides whether the panel's own operator keeps seeing it.
 *
 * ---- THE VALUE IS A TIMEOUT, NOT A FLAG ----------------------------------
 * The column stores a unix timestamp and core's test is `value < time()`, so
 * "hidden" is any future instant and "shown" is any past one. Both helpers below
 * take $now rather than calling time(), so the comparison can be tested against
 * core's semantics at the exact boundary instead of approximately.
 */

//* Ten years. Core's own button writes one, which is right for "not now" and
//* wrong for a setting on a preferences page: a switch the operator deliberately
//* turned off must not turn itself back on while they are not looking. Ten keeps
//* the value far inside a 64-bit timestamp, and any later save re-stamps it.
define('CUSTOMIZER_DONATION_HIDE_SECONDS', 315360000);

/**
 * Would ISPConfig show the donation dashlet, given the stored sys_config value?
 *
 * $stored  the `value` column, or null when the row does not exist
 * $now     the instant to compare against
 *
 * Mirrors dashboard.php:224-228 including its boundary: core hides while
 * `value >= now` and shows once `value < now`, so a timeout landing exactly on
 * $now still counts as hidden. Anything unparseable — a hand edit, a value from
 * a future schema — reads as SHOWN, which is what an untouched panel does and
 * the safer of the two guesses: a switch that silently reported "hidden" for a
 * value it did not understand would have the operator looking for a dashlet the
 * page is still rendering.
 */
function customizer_donation_shown($stored, $now) {
    if($stored === null || !is_string($stored) || !preg_match('/^-?[0-9]+$/D', $stored)) return true;
    return ((int)$stored < (int)$now);
}

/**
 * The value to store for a desired state.
 *
 * Showing writes '0' rather than deleting the row: core reads a missing row and
 * an expired one identically, and one UPDATE is both idempotent and free of the
 * DELETE-then-INSERT race that two admins saving at once could otherwise hit.
 */
function customizer_donation_hide_value($show, $now) {
    if($show) return '0';
    return (string)((int)$now + CUSTOMIZER_DONATION_HIDE_SECONDS);
}
