<?php
/**
 * interface/web/customizer/lib/preview.inc.php — the module's copy of the
 * logo-variant model, plus the two POST helpers the edit page leans on.
 *
 * This file is a plain library of function definitions, so unlike the two theme
 * endpoints it can simply be required.
 */

require_once __DIR__ . '/harness.php';
require_once __DIR__ . '/../../interface/web/customizer/lib/preview.inc.php';
require_once __DIR__ . '/../../interface/web/customizer/lib/dashlets.inc.php';

/* ---- the donation dashlet toggle ----------------------------------------
 * ISPConfig shows its donation appeal to ADMINS only, and it already has a
 * "Hide" button that writes a one-year timeout into
 * sys_config['interface']['hide_donation_dashlet'] (dashboard.php:37-47) and
 * reads it back at dashboard.php:224-228. The toggle drives that same row
 * rather than hiding the dashlet with CSS, so the panel never renders something
 * the operator has switched off and core's own button keeps working.
 *
 * These two functions are the whole decision, kept pure so the comparison can
 * be tested against core's semantics without a database.
 */
$now = 1787000000; // a fixed "now"; the helpers must never call time() themselves

t_ok('customizer_donation_shown() exists', function_exists('customizer_donation_shown'));
if (function_exists('customizer_donation_shown')) {
    //* Core: shown unless a row exists AND its value is still in the future.
    t_eq('no row at all means shown', customizer_donation_shown(null, $now), true);
    t_eq('an expired timeout means shown', customizer_donation_shown((string)($now - 1), $now), true);
    t_eq('a future timeout means hidden', customizer_donation_shown((string)($now + 1), $now), false);

    //* The exact boundary core uses is `value < time()`, so value == now is NOT
    //* yet expired and the dashlet stays hidden. Matching it matters: a toggle
    //* that disagreed with core by one second would render a checkbox that
    //* contradicts the dashboard beside it.
    t_eq('a timeout exactly at now is still hidden', customizer_donation_shown((string)$now, $now), false);

    //* Core's own Hide button writes now + 31536000.
    t_eq('core\'s one-year Hide reads as hidden', customizer_donation_shown((string)($now + 31536000), $now), false);

    //* A hand-edited or malformed value must not be fatal, and "shown" is the
    //* safe answer — it is what an untouched panel does.
    t_eq('an empty value means shown', customizer_donation_shown('', $now), true);
    t_eq('a non-numeric value means shown', customizer_donation_shown('later', $now), true);
    t_eq('zero means shown', customizer_donation_shown('0', $now), true);
}

t_ok('customizer_donation_hide_value() exists', function_exists('customizer_donation_hide_value'));
if (function_exists('customizer_donation_hide_value')) {
    //* Switching the dashlet ON must write a value core reads as expired.
    $on = customizer_donation_hide_value(true, $now);
    t_ok('showing writes a value core treats as not hidden', customizer_donation_shown($on, $now), $on);

    //* Switching it OFF must outlast core's own one-year Hide, or the operator's
    //* deliberate choice would silently expire and the appeal would come back.
    $off = customizer_donation_hide_value(false, $now);
    t_ok('hiding writes a value core treats as hidden', !customizer_donation_shown($off, $now), $off);
    t_ok('hiding outlasts core\'s own one-year button', (int)$off > $now + 31536000, $off);
    t_ok('hiding stays inside a 64-bit timestamp', (int)$off < 4102444800, $off); // < year 2100

    //* Round trip, which is the property the settings page depends on.
    t_eq('off then on is shown again',
        customizer_donation_shown(customizer_donation_hide_value(true, $now), $now), true);
    t_ok('the written value is a string, as sys_config stores it', is_string($off) && is_string($on));
}

/* ---- the news-feed toggle round trip ------------------------------------
 * "Off" has to blank the three CORE-owned [misc] dashboard_atom_url_* keys,
 * because core hides the feed for a role whose URL is empty. Blanking without a
 * copy destroys an operator's private feed URL and their deliberate choice to
 * leave a role blank; refilling all three with the ISPConfig default on the way
 * back re-leaks ISPConfig branding to exactly the roles a white-label panel must
 * not show it to. So each non-empty URL is stashed into a module-owned
 * [branding] key and restored PER ROLE.
 *
 * Per role is the fix. The old rule restored only when ALL THREE were empty and
 * then dropped every stash entry regardless, so a single role's URL returning by
 * any other route deleted the other two stashes without ever restoring them.
 */
t_ok('customizer_news_feed_keys() exists', function_exists('customizer_news_feed_keys'));
t_ok('customizer_news_feed_apply() exists', function_exists('customizer_news_feed_apply'));
if (function_exists('customizer_news_feed_apply')) {
    $A = 'dashboard_atom_url_admin';
    $R = 'dashboard_atom_url_reseller';
    $C = 'dashboard_atom_url_client';
    $sA = 'news_url_admin';
    $sR = 'news_url_reseller';
    $sC = 'news_url_client';
    $stock = 'https://www.ispconfig.org/atom';

    //* OFF: every non-empty URL is copied out before it is blanked, and a role
    //* the operator deliberately left empty stays empty rather than gaining one.
    $off = customizer_news_feed_apply(
        array($A => 'https://ops.example/feed', $R => '', $C => ''),
        array($sA => '', $sR => '', $sC => ''), '0');
    t_eq('off blanks the admin URL', $off['misc'][$A], '');
    t_eq('off stashes the admin URL', $off['stash'][$sA], 'https://ops.example/feed');
    t_eq('off leaves an already-empty role empty', $off['misc'][$R], '');
    t_eq('off stashes nothing for an empty role', $off['stash'][$sR], '');

    //* ON: each role is restored from its OWN stash, and the stash entry is
    //* dropped only once that role holds a URL again.
    $on = customizer_news_feed_apply(
        array($A => '', $R => '', $C => ''),
        array($sA => 'https://ops.example/feed', $sR => '', $sC => ''), '1');
    t_eq('on restores the admin URL', $on['misc'][$A], 'https://ops.example/feed');
    t_eq('on drops the consumed stash', $on['stash'][$sA], '');
    t_eq('a role that never had a URL is not given one', $on['misc'][$R], '');

    //* THE BUG: one role's URL back by another route must not destroy the other
    //* roles' stashes. Under the old all-or-nothing gate this returned
    //* stash = empty for all three with reseller and client never restored.
    $mixed = customizer_news_feed_apply(
        array($A => 'https://set-elsewhere.example/feed', $R => '', $C => ''),
        array($sA => 'https://ops.example/a', $sR => 'https://ops.example/r', $sC => ''), '1');
    t_eq('a URL set elsewhere is left alone', $mixed['misc'][$A], 'https://set-elsewhere.example/feed');
    t_eq('...and its now-superseded stash is dropped', $mixed['stash'][$sA], '');
    t_eq('...while another role IS restored from its own stash', $mixed['misc'][$R], 'https://ops.example/r');
    t_eq('...and only then is that stash dropped', $mixed['stash'][$sR], '');
    t_eq('a role with neither a URL nor a stash stays empty', $mixed['misc'][$C], '');

    //* First-ever enable: nothing set and nothing stashed anywhere, so seed the
    //* stock feed for all three — and ONLY then.
    $first = customizer_news_feed_apply(
        array($A => '', $R => '', $C => ''), array($sA => '', $sR => '', $sC => ''), '1');
    t_eq('a first-ever enable seeds the stock feed for admin', $first['misc'][$A], $stock);
    t_eq('...for reseller', $first['misc'][$R], $stock);
    t_eq('...for client', $first['misc'][$C], $stock);

    //* Already on, nothing stashed: an unrelated save must change nothing.
    $noop = customizer_news_feed_apply(
        array($A => 'https://ops.example/a', $R => '', $C => ''),
        array($sA => '', $sR => '', $sC => ''), '1');
    t_eq('an unrelated save leaves a set URL alone', $noop['misc'][$A], 'https://ops.example/a');
    t_eq('an unrelated save does not refill a deliberately blank role', $noop['misc'][$R], '');

    //* Off then on is lossless for every role that had a URL — the property the
    //* field's hint text has always promised.
    $start = array($A => 'https://a.example/f', $R => 'https://r.example/f', $C => '');
    $step1 = customizer_news_feed_apply($start, array($sA => '', $sR => '', $sC => ''), '0');
    $step2 = customizer_news_feed_apply($step1['misc'], $step1['stash'], '1');
    t_eq('off then on restores admin', $step2['misc'][$A], 'https://a.example/f');
    t_eq('off then on restores reseller', $step2['misc'][$R], 'https://r.example/f');
    t_eq('off then on leaves the blank role blank', $step2['misc'][$C], '');
    t_eq('off then on leaves no stash behind', $step2['stash'], array($sA => '', $sR => '', $sC => ''));

    //* A non-string in either map is not fatal and is read as empty.
    $junk = customizer_news_feed_apply(array($A => null, $R => array('x'), $C => ''),
        array($sA => 7, $sR => '', $sC => ''), '0');
    t_eq('a non-string URL is read as empty', $junk['misc'][$A], '');
    t_eq('a non-string stash is read as empty', $junk['stash'][$sA], '');
}

/* ---- luminance is defined once, here too --------------------------------- */
t_ok('customizer_luminance() exists', function_exists('customizer_luminance'));
if (function_exists('customizer_luminance')) {
    foreach (h_rails() as $hex) {
        t_ok("luminance agrees with the spec for $hex", abs(customizer_luminance($hex) - h_lum($hex)) < 1e-9);
        t_eq("customizer_hex_is_dark is customizer_luminance < 0.5 for $hex",
            customizer_hex_is_dark($hex), customizer_luminance($hex) < 0.5);
    }
}

/* ---- a posted variant is REPORTED, never healed --------------------------
 * onBeforeUpdate has to guarantee a string reaches the framework, because an
 * array subject makes preg_match() a TypeError on PHP 8. It was doing that by
 * rewriting the array to '' — which is a VALID value meaning "Automatic", so the
 * REGEX validator passed, the save succeeded, the page said "Settings saved."
 * and the operator's stored choice had been silently reset. The coercion has to
 * produce something the validator REJECTS.
 */
t_ok('customizer_logo_variant_posted() exists', function_exists('customizer_logo_variant_posted'));
if (function_exists('customizer_logo_variant_posted')) {
    $re = '/^(on_light|on_dark)?$/D';
    t_eq('a real choice survives untouched', customizer_logo_variant_posted('on_dark'), 'on_dark');
    t_eq('Automatic survives untouched', customizer_logo_variant_posted(''), '');
    t_eq('an absent field is Automatic', customizer_logo_variant_posted(null), '');
    t_ok('a bad token is left for the validator to reject',
        customizer_logo_variant_posted('nonsense') === 'nonsense');

    $arr = customizer_logo_variant_posted(array('on_dark'));
    t_ok('an array POST becomes a string', is_string($arr), gettype($arr));
    t_ok('...that the validator rejects rather than storing', !preg_match($re, (string)$arr), var_export($arr, true));
}

/* ---- the same guarantee, for every field on the form ---------------------
 * The array guard existed for the two SELECTs alone. A crafted POST of
 * accent_hex[]=x reached trim()/preg_match() with an array subject, which is a
 * TypeError on PHP 8 — a fatal on an admin page rather than a validation error.
 * The generalised helper is the same rule with the same token, so a malformed
 * POST is REPORTED by the field's own validator rather than healed into a valid
 * value or blown up.
 */
t_ok('customizer_posted_string() exists', function_exists('customizer_posted_string'));
if (function_exists('customizer_posted_string')) {
    t_eq('a string survives untouched', customizer_posted_string('#0065AB'), '#0065AB');
    t_eq('an empty string survives untouched', customizer_posted_string(''), '');
    t_eq('an absent field becomes empty', customizer_posted_string(null), '');

    foreach (array(
        'array'  => array('#0065AB'),
        'nested' => array('a' => array('b')),
        'int'    => 7,
        'float'  => 1.5,
        'bool'   => true,
        'object' => new stdClass(),
    ) as $label => $raw) {
        $got = customizer_posted_string($raw);
        t_ok("a $label POST becomes a string", is_string($got), gettype($got));
        //* And a string every validator on this form rejects: the hex pattern,
        //* the reference pattern and the variant pattern must all refuse it.
        t_ok("...that the hex validator rejects", !preg_match('/^(#[0-9A-Fa-f]{6})?$/D', $got), $got);
        t_ok("...that the reference validator rejects",
            !preg_match('/^(https:\/\/[^\s"\'<>()\\\\]+|\/(?!\/)[^\s"\'<>()\\\\]+)?$/D', $got), $got);
        t_ok("...that the variant validator rejects", !preg_match('/^(on_light|on_dark)?$/D', $got), $got);
    }

    //* The variant helper keeps its name and its contract, and is now one line
    //* over the general one — two copies of "coerce to a rejectable token"
    //* would be exactly the drift this file exists to prevent.
    t_eq('the variant helper agrees with the general one for an array',
        customizer_logo_variant_posted(array('on_dark')), customizer_posted_string(array('on_dark')));
    t_eq('the variant helper agrees for null',
        customizer_logo_variant_posted(null), customizer_posted_string(null));
}

/* ---- the preview describes what the CONTROLS say, not what is stored ------
 * On a validation error tform redisplays the raw POST, so the two selects show
 * the operator's new choice while render_image_previews() re-read the stored
 * blob and drew the swatches under the OLD one. One page, two answers about the
 * same setting.
 */
t_ok('customizer_branding_with_posted_variants() exists', function_exists('customizer_branding_with_posted_variants'));
if (function_exists('customizer_branding_with_posted_variants')) {
    $stored = array('logo_variant_nav' => 'on_light', 'logo_variant_login' => '', 'rail_hex' => '#01243D');
    $posted = array('logo_variant_nav' => 'on_dark', 'accent_hex' => '#123456');
    $m = customizer_branding_with_posted_variants($stored, $posted);
    t_eq('a posted variant wins over the stored one', $m['logo_variant_nav'], 'on_dark');
    t_eq('a variant absent from the POST keeps the stored value', $m['logo_variant_login'], '');
    t_eq('non-variant keys are never taken from the POST', $m['rail_hex'], '#01243D');
    t_ok('the POST cannot introduce unrelated keys', !isset($m['accent_hex']));
    t_eq('no POST at all leaves the stored blob alone',
        customizer_branding_with_posted_variants($stored, array()), $stored);
    t_ok('a non-array POST is ignored', customizer_branding_with_posted_variants($stored, null) === $stored);
}

/* ---- every design the panel runs, not just the one the admin is looking at
 * logo_variant_* is stored ONCE in [branding] and read by every installed
 * design, but "nav" is navy on clarity and stock's #F2F5F7 on classic. Pinning
 * on_dark to rescue a recoloured clarity rail therefore paints the white mark
 * onto classic's light header — and the preview, which described only
 * $_SESSION['s']['theme'], could not show that happening.
 */
t_ok('customizer_logo_surfaces_all() exists', function_exists('customizer_logo_surfaces_all'));
if (function_exists('customizer_logo_surfaces_all')) {
    $branding = array('logo_variant_nav' => 'on_dark');
    $all = customizer_logo_surfaces_all(array('clarity', 'classic'), $branding,
        array('nav' => 'Navigation', 'login' => 'Login screen'));

    $classic_nav = null;
    foreach ($all as $e) {
        if ($e['design'] === 'classic' && $e['surface'] === 'nav') $classic_nav = $e;
    }
    t_ok('classic\'s header is described even while the admin runs clarity', $classic_nav !== null);
    if ($classic_nav !== null) {
        t_eq('...on stock\'s real header colour', $classic_nav['bg'], '#F2F5F7');
        t_eq('...showing the forced mark the operator chose', $classic_nav['variant'], 'on_dark');
        t_ok('...labelled with the design, so two rows are tellable apart',
            stripos($classic_nav['label'], 'classic') !== false, $classic_nav['label']);
    }

    t_eq('one design alone is not design-labelled',
        customizer_logo_surfaces_all(array('clarity'), array(), array('nav' => 'Navigation'))[0]['label'], 'Navigation');
    t_eq('an unknown design contributes nothing',
        customizer_logo_surfaces_all(array('nosuchdesign'), array(), array()), array());
}

/* ---- which designs the panel actually has --------------------------------
 * install.sh deploys clarity, classic or both, so the preview must describe what
 * is deployed rather than everything this repository ships. ISPC_THEMES_PATH is
 * core's own constant and is defined here against a scratch tree so the check
 * exercises the real filesystem branch.
 */
t_ok('customizer_installed_designs() exists', function_exists('customizer_installed_designs'));
if (function_exists('customizer_installed_designs')) {
    $root = sys_get_temp_dir() . '/brandprobe_' . getmypid();
    @mkdir($root . '/clarity', 0700, true);
    define('ISPC_THEMES_PATH', $root);

    t_eq('only the deployed design is described', customizer_installed_designs('clarity'), array('clarity'));
    t_eq('a design that is not deployed is not described',
        customizer_installed_designs('clarity'), array('clarity'));

    @mkdir($root . '/classic', 0700, true);
    t_eq('both deployed designs are described, active first',
        customizer_installed_designs('clarity'), array('clarity', 'classic'));
    t_eq('the active design leads even when it is not the first known one',
        customizer_installed_designs('classic'), array('classic', 'clarity'));

    //* Stock ISPConfig, or any third-party theme: this extension describes the
    //* designs it ships and knows nothing about that one, which is the honest
    //* answer rather than a guess about someone else's chrome.
    t_eq('a stock or third-party active theme still lists what is deployed',
        customizer_installed_designs('default'), array('clarity', 'classic'));
    t_eq('a non-string active theme is not fatal',
        customizer_installed_designs(null), array('clarity', 'classic'));

    //* The active design is trusted only if it is one this extension ships, so a
    //* tampered session value can never become a path segment.
    t_ok('an arbitrary session theme is not admitted',
        !in_array('../../etc', customizer_installed_designs('../../etc'), true));

    @rmdir($root . '/clarity');
    @rmdir($root . '/classic');
    @rmdir($root);
}

/* ---- characterisation: the two shipped designs' chrome -------------------
 * These are the values the preview has always drawn. They pin the refactor
 * above: whatever changes, what the operator sees for clarity and classic on a
 * default install must not.
 */
$c = customizer_logo_surfaces('clarity', array());
t_eq('clarity: three swatches (nav, and login once per colour mode)', count($c), 3);
t_eq('clarity nav: the dark mark on the navy rail', $c[0], array('surface' => 'nav', 'label' => '', 'variant' => 'on_dark', 'bg' => '#01243D'));
t_eq('clarity login, dark mode', $c[1], array('surface' => 'login', 'label' => '', 'variant' => 'on_dark', 'bg' => '#17252B'));
t_eq('clarity login, light mode', $c[2], array('surface' => 'login', 'label' => '', 'variant' => 'on_light', 'bg' => '#F1F6F8'));

$k = customizer_logo_surfaces('classic', array());
t_eq('classic: two swatches', count($k), 2);
t_eq('classic nav: the light mark on stock\'s header', $k[0], array('surface' => 'nav', 'label' => '', 'variant' => 'on_light', 'bg' => '#F2F5F7'));
t_eq('classic login: the light mark on the panel heading', $k[1], array('surface' => 'login', 'label' => '', 'variant' => 'on_light', 'bg' => '#EEF0F2'));

t_eq('a rail_hex the operator set is what the nav swatch is drawn on',
    customizer_logo_surfaces('clarity', array('rail_hex' => '#FFFFFF'))[0],
    array('surface' => 'nav', 'label' => '', 'variant' => 'on_light', 'bg' => '#FFFFFF'));

t_eq('an unknown design is described as nothing rather than guessed at',
    customizer_logo_surfaces('nosuchdesign', array()), array());

/* ---- one resolver signature across all three copies ---------------------- */
t_eq('explicit choice beats a contradicting background',
    customizer_logo_variant_for_surface('on_dark', '#FFFFFF', 'on_light'), 'on_dark');
t_eq('an unrecognised stored value is automatic',
    customizer_logo_variant_for_surface('garbage', '#FFFFFF', 'on_dark'), 'on_light');
t_eq('a trailing newline does not make a hex valid',
    customizer_logo_variant_for_surface('', "#FF0000\n", 'on_dark'), 'on_dark');

$matrix = array();
foreach (h_variant_matrix() as $row) {
    $matrix[] = customizer_logo_variant_for_surface($row[0], $row[1], $row[2]);
}
echo 'MATRIX ' . json_encode($matrix) . "\n";

/* ---- onBeforeUpdate's guard runs on the SAVE path, not the display path --
 * customizer_edit.php cannot simply be require()d: it die()s outside an admin
 * session and touches $_SESSION/$app at the top level. So this is a TEXT-level
 * probe on the source, the same kind probe_tform.php already uses for
 * structural checks that have no runtime surface of their own.
 *
 * $this->active_tab is set by tform_actions ONLY in onShow() (the display
 * path). The save path is onLoad()->onSubmit()->onUpdate()->onBeforeUpdate(),
 * which never sets it — so a guard keyed on active_tab silently iterates
 * nothing on every real save, and the colour-normalisation loop right after it
 * then runs trim()/preg_match() on whatever a crafted POST handed it,
 * un-guarded. getCurrentTab() reads the session tab pin this file sets at the
 * top (the same one onUpdateSave() already keys its own formDef lookup on),
 * so that — not active_tab — is what onBeforeUpdate's guard must be built on.
 */
$edit_src = file_get_contents(__DIR__ . '/../../interface/web/customizer/customizer_edit.php');
t_ok('customizer_edit.php is readable for the source probes below', $edit_src !== false);

if ($edit_src !== false) {
    $start = strpos($edit_src, 'function onBeforeUpdate()');
    t_ok('onBeforeUpdate() is found', $start !== false);

    if ($start !== false) {
        //* Body ends at the next top-level "function " after this one.
        $next  = strpos($edit_src, 'function ', $start + strlen('function onBeforeUpdate()'));
        $body  = ($next !== false) ? substr($edit_src, $start, $next - $start) : substr($edit_src, $start);

        //* The explanatory comment above the fix names active_tab on purpose
        //* (it explains what NOT to key the guard on), so the probe checks for
        //* the executable pattern rather than for the bare string: no
        //* formDef[...][$this->active_tab] lookup left anywhere in the body.
        t_ok('the guard is keyed on getCurrentTab(), not active_tab',
            strpos($body, 'getCurrentTab()') !== false
                && strpos($body, "['tabs'][\$this->active_tab]") === false,
            $body);

        //* The colour-normalisation loop must still cover exactly these four
        //* keys — accent_hex, rail_hex, rail_hex_light and login_bg — in one
        //* place, so a fifth colour field added later cannot be forgotten here
        //* independently of the fields it is meant to guard.
        t_ok('the colour-normalisation loop still lists all four colour keys',
            (bool)preg_match(
                "/foreach\\(array\\('accent_hex',\\s*'rail_hex',\\s*'rail_hex_light',\\s*'login_bg'\\)\\s*as\\s*\\\$k\\)/",
                $body
            ),
            $body);
    }
}

/* ---- bin/purge_branding.php must not keep its own copy of the news-feed map
 * customizer_news_feed_keys() in lib/dashlets.inc.php IS that mapping. A second,
 * inline literal of the same 'dashboard_atom_url_*' => 'news_url_*' pairs here
 * would be exactly the drift the comment above customizer_news_feed_keys()
 * claims does not exist — two copies of a key-name mapping that can silently
 * fall out of step. Text-level, like the onBeforeUpdate probes above: this
 * script die()s outside a real ISPConfig install and cannot simply be required.
 */
$purge_src = file_get_contents(__DIR__ . '/../../bin/purge_branding.php');
t_ok('bin/purge_branding.php is readable for the source probe below', $purge_src !== false);

if ($purge_src !== false) {
    t_ok('purge_branding.php has no inline duplicate of the atom-key map',
        !preg_match("/'dashboard_atom_url_[a-z]+'\\s*=>\\s*'news_url_[a-z]+'/", $purge_src));
    t_ok('purge_branding.php uses customizer_news_feed_keys() instead',
        strpos($purge_src, 'customizer_news_feed_keys()') !== false);
}

t_done();
