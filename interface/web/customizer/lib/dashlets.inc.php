<?php
/**
 * ispconfig-customizer — dashboard dashlets the operator can switch off.
 * Copyright (c) 2026 Wade Beckett. MIT License — see ../../LICENSE.
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

/**
 * The three core-owned news-feed keys, mapped to the module-owned key each one
 * is stashed under while the feed is switched off.
 *
 * One map, in one place: customizer_edit.php reads it to move values, and
 * bin/purge_branding.php's restore uses the same pairing. Two copies of a
 * key-name mapping is how a stash outlives the key it belongs to.
 */
function customizer_news_feed_keys() {
    return array(
        'dashboard_atom_url_admin'    => 'news_url_admin',
        'dashboard_atom_url_reseller' => 'news_url_reseller',
        'dashboard_atom_url_client'   => 'news_url_client',
    );
}

/**
 * The news-feed toggle's whole decision, as data.
 *
 * $misc   the three [misc] dashboard_atom_url_* values, keyed by their real key
 *         names; anything not a string reads as ''
 * $stash  the three [branding] news_url_* values, keyed by their stash names;
 *         anything not a string reads as ''
 * $show   '1' (feed on) or '0' (feed off)
 *
 * Returns array('misc' => …, 'stash' => …) — the values to write back, both
 * keyed as they came in. A stash entry of '' means "no stash": the caller
 * unsets the key rather than writing an empty one.
 *
 * OFF copies each non-empty URL into its stash and blanks it. A role that was
 * already empty stays empty and gains no stash — the operator may deliberately
 * have left reseller and client blank so those roles see nothing at all, and
 * restoring a URL there later would be inventing a choice they never made.
 *
 * ON restores PER ROLE: a role whose URL is empty and whose stash holds
 * something gets it back, and the stash entry is dropped only once that role
 * holds a URL again. The all-or-nothing gate this replaces ("restore only if
 * all three are empty") then dropped every stash unconditionally, so one role's
 * URL returning by any other route deleted the other two stashes having never
 * restored them — a permanent loss of a core-owned value this module blanked.
 *
 * The trade that rule makes, stated: with the toggle ON, a role the operator
 * blanked by hand under System > Interface Config is refilled from its stash if
 * one still exists. A stash only exists because this toggle was OFF while that
 * role had a URL, so the value being restored is one the operator did have; the
 * alternative loses it for good, which is worse.
 *
 * The ISPConfig default feed is seeded only when nothing is set and nothing is
 * stashed anywhere — a first-ever enable. Seeding on any other path would
 * re-leak ISPConfig branding to the roles a white-label panel must not show it
 * to, which is the whole point of the toggle.
 */
function customizer_news_feed_apply($misc, $stash, $show) {
    $keys = customizer_news_feed_keys();

    $m = array();
    $s = array();
    foreach($keys as $k => $stash_key) {
        $m[$k]         = (is_array($misc)  && isset($misc[$k])         && is_string($misc[$k]))         ? $misc[$k]         : '';
        $s[$stash_key] = (is_array($stash) && isset($stash[$stash_key]) && is_string($stash[$stash_key])) ? $stash[$stash_key] : '';
    }

    if($show === '0') {
        foreach($keys as $k => $stash_key) {
            if($m[$k] !== '') {
                $s[$stash_key] = $m[$k];
                $m[$k] = '';
            }
        }
        return array('misc' => $m, 'stash' => $s);
    }

    $anything = false;
    foreach($keys as $k => $stash_key) {
        if($m[$k] === '' && $s[$stash_key] !== '') $m[$k] = $s[$stash_key];
        if($m[$k] !== '') {
            $s[$stash_key] = '';
            $anything = true;
        }
    }

    //* Nothing was set and nothing was stashed: the feed has never been on.
    if(!$anything) {
        foreach($keys as $k => $stash_key) {
            $m[$k] = 'https://www.ispconfig.org/atom';
        }
    }

    return array('misc' => $m, 'stash' => $s);
}
