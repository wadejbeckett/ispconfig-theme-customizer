<?php
/**
 * interface/web/customizer/templates/customizer_edit.htm — the page's SHAPE.
 *
 * The template cannot be rendered here: vlibTemplate needs the framework, and
 * the values come from a database this environment has no driver for. What can
 * be asserted, and is worth more than a render, is the CONTRACT between the
 * three halves of the file — the markup, the style block and the inline script
 * — because every one of those is a promise the other two depend on and none of
 * them is checked by php -l.
 *
 * Parsed as TEXT and never executed, the same rule .github/scripts/lang_check.php
 * follows: this file is markup with PHP-less template tags in it, and there is
 * nothing here to run.
 *
 * Run through tests/brand/run.php.
 */

require_once __DIR__ . '/harness.php';

$path = __DIR__ . '/../../interface/web/customizer/templates/customizer_edit.htm';
$src  = @file_get_contents($path);
t_ok('the template exists and is readable', $src !== false);
if ($src === false) t_done();

/* ---- the JS contract: every hook the inline script binds ------------------
 * The script reaches for these by name. A rename in the markup is invisible to
 * every other check in this repository and shows up as one silently dead
 * feature on an admin page.
 */
$ids = array(
    // carried over, unchanged
    'nz-msg-slot', 'nz-brandpage', 'company_name',
    'accent_hex', 'accent_hex_pick', 'rail_hex', 'rail_hex_pick',
    'rail_hex_light', 'rail_hex_light_pick', 'login_bg', 'login_bg_pick',
    'logo_url', 'logo_url_on_dark', 'favicon_url',
    'logo_variant_nav', 'logo_variant_login',
    'used_logo', 'used_logo_more', 'used_logo_on_dark', 'used_logo_on_dark_more',
    'used_favicon', 'file', 'file_on_dark', 'file_favicon',
    'nz-logo-upload', 'nz-logo-upload-on-dark', 'nz-favicon-upload',
    'nz-logo-remove', 'nz-logo-remove-on-dark', 'nz-favicon-remove',
    'nz-rail-ratio', 'nz-rail-light-ratio', 'nz-rail-light-inherited',
    // new with the redesign
    'nz-accent-ratio', 'nz-login-ratio', 'nz-save-flash',
    'nz-fact-design', 'nz-fact-marks', 'nz-fact-favicon',
    'nz-drop-line-logo', 'nz-drop-line-logo-on-dark', 'nz-drop-line-favicon',
);
foreach ($ids as $id) {
    t_ok("id=\"$id\" is in the markup", strpos($src, 'id="' . $id . '"') !== false);
}

$classes = array(
    'nz-prev-rail', 'nz-prev-rail-light', 'nz-prev-login', 'nz-prev-accent',
    'nz-prev-accent-rule', 'nz-prev-name', 'nz-prev-nav-brand',
    'nz-prev-nav-brand-light', 'nz-prev-light-pane', 'nz-prevframe',
    'nz-brandpreview', 'nz-actions', 'nz-hexfield', 'nz-hexpick',
    // new with the redesign
    'nz-prev-login-brand', 'nz-brandchip-hex', 'nz-drop', 'nz-drop-line',
);
foreach ($classes as $cls) {
    t_ok("class $cls is in the markup", strpos($src, $cls) !== false);
}

//* The named fields. tform posts by NAME, so a rename here is a field that
//* silently stops being saved.
foreach (array('company_name', 'logo_url', 'logo_url_on_dark', 'favicon_url',
               'logo_variant_nav', 'logo_variant_login', 'accent_hex', 'rail_hex',
               'rail_hex_light', 'login_bg', 'custom_login_text', 'custom_login_link',
               'file', 'file_on_dark', 'file_favicon', 'id') as $name) {
    t_ok("name=\"$name\" is in the markup", strpos($src, 'name="' . $name . '"') !== false);
}

//* The tform button contract, and the three data attributes the script reads
//* off the page host.
t_ok('Save still submits pageForm to this module',
    strpos($src, 'data-submit-form="pageForm"') !== false
    && strpos($src, 'data-form-action="customizer/customizer_edit.php"') !== false);
t_ok('Cancel still loads the dashboard',
    strpos($src, 'data-load-content="dashboard/dashboard.php"') !== false);
foreach (array('data-field-errors', 'data-preview-failed', 'data-rail-light-inherited') as $attr) {
    t_ok("$attr is carried on the page host", strpos($src, $attr) !== false);
}

/* ---- every field row keeps the shape markField() walks ------------------- */
t_ok('the 3/9 row shape survives',
    substr_count($src, 'class="col-sm-3 control-label"') >= 12
    && substr_count($src, 'class="col-sm-9"') >= 12);

/* ---- DOM order: the pulls come before the chips --------------------------
 * paintSurfacePane() uses querySelector — first match only. If a colour chip
 * in the legend came first, the server's measured backdrop and ink would be
 * painted onto a 56px swatch and the pull would keep whatever it had.
 */
$first_rail  = strpos($src, 'nz-prevnav-rail nz-prev-rail"');
$chip_rail   = strpos($src, 'nz-brandchip-swatch nz-prev-rail"');
t_ok('the panel pull is the first .nz-prev-rail',
    $first_rail !== false && $chip_rail !== false && $first_rail < $chip_rail,
    "pull at $first_rail, chip at $chip_rail");
$first_light = strpos($src, 'nz-prevnav-rail nz-prev-rail-light"');
$chip_light  = strpos($src, 'nz-brandchip-swatch nz-prev-rail-light"');
t_ok('the light-rail sample is the first .nz-prev-rail-light',
    $first_light !== false && $chip_light !== false && $first_light < $chip_light,
    "sample at $first_light, chip at $chip_light");
t_ok('the login pull is the only .nz-prev-login', substr_count($src, 'nz-prev-login"') === 1);

/* ---- the collapsing slots ------------------------------------------------
 * :empty is the mechanism, so a slot must hold its tmpl_var and NOTHING else —
 * one space of whitespace defeats the selector and opens a blank row under the
 * block.
 */
foreach (array('used_logo_more', 'used_logo_on_dark_more') as $slot) {
    t_ok("#$slot holds its tmpl_var and nothing else",
        preg_match('#<div class="nz-upload-more" id="' . $slot . '">\{tmpl_var name=\'' . $slot . '\'\}</div>#', $src) === 1);
}
//* ...and the label above it is OUTSIDE the slot, or the first preview refresh
//* would replace the slot's innerHTML and delete it.
t_ok('the "Also used on" label is outside the slot it names',
    strpos($src, "<p class=\"nz-surfacelabel\">{tmpl_var name='also_used_on_txt'}</p>") !== false);

/* ---- the light pane ships hidden ----------------------------------------- */
t_ok('the light-rail pane ships hidden for the script to reveal',
    preg_match('/nz-prev-light-pane[^>]*\shidden/', $src) === 1);

/* ---- the mini panel's sample chrome --------------------------------------
 * The pane draws a panel, so it carries a panel's labels: three nav items, a
 * search field and a card heading. They are the module's OWN wordbook strings
 * and are translated in all seven locales — an English literal here would ship
 * "Home" into a Portuguese panel, and a blank bar would leave the accent marker
 * standing beside nothing, reading as a text cursor rather than as a marker.
 */
foreach (array('preview_nav_home_txt', 'preview_nav_sites_txt', 'preview_nav_email_txt',
               'preview_search_txt', 'preview_card_title_txt', 'preview_signin_txt') as $key) {
    t_ok("the pane draws {$key}, not an English literal",
        strpos($src, "{tmpl_var name='" . $key . "'}") !== false);
}
//* ...which is what earns the marker its place ON the active item: it marks a
//* row that says something. It stays an element, because the script paints it
//* through style.color and the rail itself already carries the measured ink on
//* that property.
t_ok('the accent marker sits on the active nav item',
    strpos($src, '<div class="nz-prevnav-item is-active"><span class="nz-prev-accent-rule"') !== false);

/* ---- the style block, and the house rules it must obey ------------------- */
$a = strpos($src, '<style>');
$b = strpos($src, '</style>');
t_ok('there is exactly one style block', $a !== false && $b !== false
    && substr_count($src, '<style>') === 1);
//* Sliced from AFTER the opening tag. '<style>' carries no brace of its own, so
//* left in it would be swallowed into the first rule's selector and reported as
//* an unprefixed one on every possible stylesheet.
$open  = strlen('<style>');
$style = ($a !== false && $b !== false) ? substr($src, $a + $open, $b - $a - $open) : '';
//* Comments out, first and always. This block's own comments discuss the rules
//* below them — "rather than with !important", "a design that sets
//* `figure { display: block }`" — so a scan that read them would fail on the
//* documentation of the very rule it enforces, and the brace pair inside that
//* sentence would parse as a rule with an unprefixed selector.
$style = preg_replace('#/\*.*?\*/#s', '', $style);

t_ok('no rule uses !important', strpos($style, '!important') === false);
//* Exactly one focus rule, and it is the drop zone's — the control it belongs
//* to is deliberately clipped, so the design's own indicator would paint
//* off-screen. Every other control on this page keeps whatever indicator the
//* active design gives it, which is why any second occurrence is a failure.
t_ok('there is exactly one focus rule', substr_count($style, ':focus') === 1);
t_ok('...and it is the drop zone\'s', strpos($style, '.nz-drop:focus-within') !== false);
t_ok('nothing sets an outline', strpos($style, 'outline') === false);

//* A colour row is a flex line, and markField() appends its message to the
//* rejected input's parentNode — which on those four rows IS that line. Without
//* a full-width basis the message renders beside the contrast readout instead of
//* under the row, measured in Chromium at the one-column collapse.
t_ok('a rejected colour\'s message takes the whole row',
    preg_match('/#nz-brandpage \.nz-colourrow > \.nz-fielderror \{[^}]*flex:\s*1 0 100%/', $style) === 1);

//* Containment goes on OUR wrapper. #pageContent is core's element and giving
//* it a containment context changes the layout of every other module's page.
t_ok('the container is #nz-brandpage', strpos($style, '#nz-brandpage { container-type: inline-size') !== false);
t_ok('#pageContent is never given containment',
    preg_match('/#pageContent[^{]*\{[^}]*container-type/', $style) !== 1);
t_ok('there is a viewport fallback for @container',
    strpos($style, '@supports not (container-type: inline-size)') !== false);

//* Every selector is prefixed, so the page beats a design's own rules on
//* specificity rather than with !important.
preg_match_all('/([^{}@]+)\{([^{}]*)\}/', $style, $rules, PREG_SET_ORDER);
t_ok('the style block parses into rules', count($rules) > 60, count($rules) . ' rules');
$unprefixed = array();
foreach ($rules as $r) {
    foreach (explode(',', $r[1]) as $sel) {
        $sel = trim($sel);
        if ($sel === '' || $sel[0] === '@' || strpos($sel, 'from') === 0 || strpos($sel, 'to') === 0) continue;
        if (strpos($sel, '#nz-brandpage') === 0 || strpos($sel, '#pageContent') === 0) continue;
        $unprefixed[] = $sel;
    }
}
t_eq('every selector is prefixed #nz-brandpage or #pageContent', $unprefixed, array());

//* Every colour is a token chain: phosphor's, then clarity's, then a
//* design-neutral literal. A raw hex would look right under the design it was
//* written for and wrong under the other two. The single exception is the
//* light-mode rail sample's ground, which DEPICTS a light colour mode and
//* would otherwise be painted dark by a dark design's own page token.
$raw = array();
foreach ($rules as $r) {
    foreach (explode(';', $r[2]) as $decl) {
        if (!preg_match('/#[0-9A-Fa-f]{6}\b/', $decl)) continue;
        if (strpos($decl, 'var(--nz-') !== false) continue;      // a fallback argument
        if (strpos($r[1], 'nz-prev-lightbody') !== false) continue;
        $raw[] = trim($r[1]) . ' {' . trim($decl) . ' }';
    }
}
t_eq('no colour is named outside a token chain', $raw, array());
t_ok('...and every chain leads with phosphor\'s token',
    substr_count($style, 'var(--nz-') === substr_count($style, ', var(--nz-'),
    substr_count($style, 'var(--nz-') . ' clarity tokens, '
        . substr_count($style, ', var(--nz-') . ' of them inside a --pz- chain');

/* ---- the inline script --------------------------------------------------- */
$sa = strpos($src, '<script>');
$sb = strpos($src, '</script>');
t_ok('there is exactly one script block', $sa !== false && $sb !== false
    && substr_count($src, '<script>') === 1);
$js = ($sa !== false && $sb !== false) ? substr($src, $sa, $sb - $sa) : '';

/* The frozen region. The click-time CSRF mint inside wireUpload() exists
 * because ISPConfig's session store has no locking — a page-render token can be
 * silently erased by a concurrent request — and it is the one part of this page
 * that must never be reimplemented. wireRemove() and the #nz-msg-slot observer
 * are frozen with it: both self-disconnect or re-read state in ways that are
 * easy to break and impossible to see break. */
foreach (array('function wireUpload(', 'function wireRemove(',
               "fetch('customizer/logo_upload.php'", "fd.append('_csrf_id'",
               "fd.append('_csrf_key'", "fd.append('slot', slotName)",
               "wireUpload('nz-logo-upload', 'file', 'on_light')",
               "wireUpload('nz-logo-upload-on-dark', 'file_on_dark', 'on_dark')",
               "wireUpload('nz-favicon-upload', 'file_favicon', 'favicon')",
               "wireRemove('nz-logo-remove', 'on_light')",
               "wireRemove('nz-logo-remove-on-dark', 'on_dark')",
               "wireRemove('nz-favicon-remove', 'favicon')",
               "var slot = document.getElementById('nz-msg-slot')") as $needle) {
    t_ok("the frozen region still contains: $needle", strpos($js, $needle) !== false);
}
//* Exactly two references, both inside wireUpload(): the token mint and the
//* upload itself. A third would be a second uploader.
t_eq('logo_upload.php is reached from exactly two places',
    substr_count($js, 'logo_upload.php'), 2);

/* ES5, matching the block these additions join.
 *
 * Measured on the CODE, not the file: the frozen region's own comments say
 * "…exists to let them check" and quote jQuery's `data` in backticks, and a
 * scan that read those would report two syntax rules broken by prose it is not
 * allowed to edit. The '//' strip spares a '://' so a URL in a string survives.
 */
$code = preg_replace('#/\*.*?\*/#s', '', $js);
$code = preg_replace('#(^|[^:])//[^\n]*#', '$1', $code);
foreach (array('=>', 'const ', 'let ', '`') as $bad) {
    t_ok('the script stays ES5 (no ' . $bad . ')', strpos($code, $bad) === false);
}

/* The four live hooks and the drop forwarding. */
foreach (array("setRatio('nz-rail-ratio'", "setRatio('nz-rail-light-ratio'",
               "setRatio('nz-accent-ratio'", "setRatio('nz-login-ratio'",
               "paintNavBrand('.nz-prev-login-brand'",
               "getElementById('nz-save-flash')", "querySelector('.alert-notification')",
               '.nz-brandchip-hex', 'data-hex-field', 'data.summary',
               "setFact('nz-fact-design'", "setFact('nz-fact-marks'",
               "setFact('nz-fact-favicon'", 'function wireDrop(',
               "wireDrop('file', 'nz-drop-line-logo')",
               "wireDrop('file_on_dark', 'nz-drop-line-logo-on-dark')",
               "wireDrop('file_favicon', 'nz-drop-line-favicon')",
               'dataTransfer') as $needle) {
    t_ok("the script wires: $needle", strpos($js, $needle) !== false);
}
//* The login pull has to be cleared as well as painted, or a mark an earlier
//* response put there survives a change that resolves to none.
t_eq('every nav-brand slot is cleared when nothing resolves',
    substr_count($js, "paintNavBrand('.nz-prev-login-brand', null)"), 1);

//* Controller ruling: the file input's aria-label wins over the label text the
//* drop line lives in, so writing the chosen filename into the line alone is
//* invisible to a screen reader. wireDrop() points the input's description at
//* the line, which is the element that carries both the instruction and, after
//* a choice, the filename.
t_ok('the drop zone names its chosen file to a screen reader',
    strpos($js, "setAttribute('aria-describedby', lineId)") !== false);

/* The file-less drop. dragover is cancelled unconditionally, so the zone has
 * already told the browser it accepts the drag; a drop handler that returned
 * before preventDefault would leave a dragged LINK to the browser's own
 * default, which navigates away from the page being edited — the exact loss
 * the two handlers above it exist to prevent. Order, not presence: both lines
 * are there either way, and only their order decides whether the page survives.
 */
$dropAt  = strpos($js, "zone.addEventListener('drop'");
$handler = ($dropAt !== false) ? substr($js, $dropAt, 800) : '';
$pd      = strpos($handler, 'e.preventDefault();');
$guard   = strpos($handler, 'files.length) return;');
t_ok('a drop is cancelled before the files guard, not after',
    $pd !== false && $guard !== false && $pd < $guard);

/* The chosen filename is written into a line the operator can SEE, and the
 * input's description points at it; neither announces a drop, which moves no
 * focus. aria-live does. It is the drop LINE and nothing else: a live preview
 * column would narrate every keystroke's repaint. */
foreach (array('nz-drop-line-logo', 'nz-drop-line-logo-on-dark',
               'nz-drop-line-favicon') as $id) {
    t_ok("the drop line announces a change: $id",
        strpos($src, 'id="' . $id . '" aria-live="polite"') !== false);
}
t_eq('...and nothing else on the page is a live region',
    substr_count($src, 'aria-live'), 3);
//* A live region announces what is WRITTEN to it, so writing the idle
//* instruction back on load — or the same filename twice — would speak for no
//* reason. Only a real change is written.
t_ok('the drop line is only written when it really changes',
    strpos($js, 'if (line.textContent !== next) line.textContent = next;') !== false);

t_done();
