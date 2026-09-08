<?php
/**
 * The frozen region of interface/web/customizer/templates/customizer_edit.htm.
 *
 * From the "// The iframe uploader injects" comment to EOF the file is FROZEN:
 * the message-relocation observer, and below it the two-step upload driver whose
 * click-time CSRF mint is the one part of this page that must never be
 * reimplemented (the page-render token can be silently erased by ISPConfig's
 * lock-free, REPLACE-based session store, which is the whole reason the mint
 * exists). Work on this page therefore happens ABOVE that comment and the region
 * is expected back byte-identical.
 *
 * probe_page.php asserts that thirteen needles are PRESENT in the script, which
 * a reformat or an inserted line would survive. This asserts the bytes.
 *
 * Parsed as TEXT and never executed, like probe_page.php beside it.
 *
 * REGENERATING THE HASH is a deliberate act, not a fix for a red build. The
 * region is frozen because changing it is a security decision; if the change is
 * genuinely intended and reviewed on that basis, take the new value from:
 *
 *   sed -n "$(grep -n '// The iframe uploader injects' \
 *     interface/web/customizer/templates/customizer_edit.htm | cut -d: -f1),\$p" \
 *     interface/web/customizer/templates/customizer_edit.htm | sha256sum
 *
 * and say in the commit message what moved and why it was safe to move it.
 *
 * Run through tests/brand/run.php.
 */

require_once __DIR__ . '/harness.php';

//* The opening comment of the frozen region. Located by content rather than by
//* line number so that edits ABOVE it — which are the normal case — do not have
//* to update this file.
define('FROZEN_MARKER', '  // The iframe uploader injects #OKMsg/#errorMsg just above the hidden id input');

//* sha256 of the marker through EOF, inclusive.
define('FROZEN_SHA256', '888458b5a7107de6ddeba26e03f819e4a7d94a6c96fe2c6c21006ea3eec83daf');

$path = __DIR__ . '/../../interface/web/customizer/templates/customizer_edit.htm';
$src  = @file_get_contents($path);
t_ok('the template exists and is readable', $src !== false);
if ($src === false) t_done();

$at = strpos($src, FROZEN_MARKER);
t_ok('the frozen region still opens with its marker comment', $at !== false);
if ($at === false) t_done();

//* Once only: two matches would mean the marker no longer names one region.
t_ok('...and the marker appears exactly once',
    strpos($src, FROZEN_MARKER, $at + 1) === false);

$region = substr($src, $at);
$got    = hash('sha256', $region);

//* A guard that hashes almost nothing would pass forever. The region is ~4.5KB
//* of script; assert it is at least a plausible fraction of that.
t_ok('the region reaches EOF and is not a truncated slice', strlen($region) > 2000,
    strlen($region) . ' bytes');

t_ok('the frozen region is byte-identical to the reviewed one', $got === FROZEN_SHA256,
    'got ' . $got . ', want ' . FROZEN_SHA256
    . ' — see the regeneration note at the top of this file');

//* The reason the freeze exists, asserted so a rewrite that kept the byte count
//* honest could not also quietly pass by coincidence of a regenerated hash.
t_ok('the click-time CSRF mint is still inside the frozen region',
    strpos($region, 'csrf_id') !== false && strpos($region, 'csrf_key') !== false);

t_done();
