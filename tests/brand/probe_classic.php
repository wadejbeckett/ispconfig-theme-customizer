<?php
/**
 * themes/classic/brand.php — the pure helpers.
 *
 * Run through tests/brand/run.php, which gives this file a process of its own.
 */

require_once __DIR__ . '/harness.php';
$classic_src = __DIR__ . '/../../themes/classic/brand.php';
load_helpers($classic_src);

/* ---- one resolver signature across all three copies ----------------------
 * classic took ($surface, $branding, $bg_hex, $default) and composed the key
 * itself while its docblock told maintainers it was byte-identical to clarity's
 * ($stored, $bg_hex, $default). Acting on that instruction turned every explicit
 * preference on classic into a no-op, because $stored would receive 'nav'. One
 * signature is what makes the documented audit — diff the three copies — a thing
 * that can actually be run.
 */
t_eq('explicit choice beats a contradicting background',
    brand_logo_variant_pref('on_dark', '#FFFFFF', 'on_light'), 'on_dark');
t_eq('an unrecognised stored value is automatic',
    brand_logo_variant_pref('garbage', '#FFFFFF', 'on_dark'), 'on_light');
t_eq('a trailing newline does not make a hex valid',
    brand_logo_variant_pref('', "#FF0000\n", 'on_dark'), 'on_dark');

/* ---- the CI contract check has to have something to find -----------------
 * ci.yml greps every themes/*&#47;brand.php for logo_variant_nav and
 * logo_variant_login so a rename on the writer side cannot pass unnoticed. While
 * classic composed 'logo_variant_' . $surface, the only matches in this file
 * were in prose, and a rename would have kept CI green while classic silently
 * ignored the operator forever. The keys have to appear in CODE.
 */
// Tokenised, not regex-stripped: this file EMITS CSS, so it holds "/*" inside
// string literals and a comment-stripping regex pairs one of those with a real
// "*/" further down and swallows the code in between — which is how the first
// version of this very test managed to report the call site missing while it was
// sitting there in plain sight.
$code = '';
foreach (token_get_all(file_get_contents($classic_src)) as $tok) {
    if (is_array($tok)) {
        if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) continue;
        $code .= $tok[1];
        continue;
    }
    $code .= $tok;
}
foreach (array('logo_variant_nav', 'logo_variant_login', 'rail_hex_light') as $key) {
    t_ok("classic reads '$key' in code, not only in a comment", strpos($code, $key) !== false);
}

/* ---- and the no-op is a no-op -------------------------------------------
 * classic renders one colour mode, so rail_hex_light has nothing to paint here.
 * That is a documented answer to the contract, not an oversight — but it has to
 * stay an answer: if this key ever reached a rule, the value would be painting
 * classic's single rail with a colour the operator chose for a light MODE they
 * do not have on this design.
 */
$uses = 0;
foreach (token_get_all(file_get_contents($classic_src)) as $tok) {
    if (is_array($tok) && $tok[0] === T_VARIABLE && $tok[1] === '$rail_light_noop') $uses++;
}
//* Exactly two: the assignment, and the unset() that says so out loud. A third
//* appearance is the value being used, which is what "no-op" forbids.
t_eq('the light-rail read is assigned and discarded, and used nowhere else', $uses, 2);

/* ---- luminance is defined once ------------------------------------------
 * brand_is_dark() re-implemented brand_luminance() line for line, eighty lines
 * from it in the same file, so a gamma-curve fix in one would silently leave the
 * other behind and the two would then disagree about the same colour on the same
 * page.
 */
foreach (h_rails() as $hex) {
    t_ok("luminance agrees with the spec for $hex", abs(brand_luminance($hex) - h_lum($hex)) < 1e-9);
    t_eq("brand_is_dark is brand_luminance < 0.5 for $hex", brand_is_dark($hex), brand_luminance($hex) < 0.5);
}

/* ---- one hex pattern, one anchor, in every copy --------------------------
 * brand_hex() is the gate every colour on this design goes through — accent,
 * rail, login background, and now the light-rail read above — and its pattern
 * was the one copy in this project without /D. Without it PCRE's `$` also
 * matches immediately before a final newline, so a stored "#FFFFFF\n" validated
 * here and the raw LF was emitted straight into a text/css response, corrupting
 * the declaration it landed in on a PRE-AUTH endpoint. clarity's copy has always
 * carried /D, so the two readers disagreed about the same stored value — which
 * is the class of drift this whole suite exists to catch.
 *
 * The twin of this block is in probe_clarity.php, so neither copy can move
 * without the other.
 */
t_ok('brand_hex() exists', function_exists('brand_hex'));
if (function_exists('brand_hex')) {
    t_eq('a valid hex is returned as stored',
        brand_hex(array('rail_hex' => '#01243D'), 'rail_hex'), '#01243D');
    t_eq('lower case is returned as stored, not normalised',
        brand_hex(array('rail_hex' => '#01243d'), 'rail_hex'), '#01243d');
    t_eq('an absent key is empty', brand_hex(array(), 'rail_hex'), '');
    t_eq('a missing hash is refused', brand_hex(array('rail_hex' => '01243D'), 'rail_hex'), '');
    t_eq('a short value is refused', brand_hex(array('rail_hex' => '#0124D'), 'rail_hex'), '');
    t_eq('a long value is refused', brand_hex(array('rail_hex' => '#01243DE'), 'rail_hex'), '');
    t_eq('a named colour is refused', brand_hex(array('rail_hex' => 'red'), 'rail_hex'), '');
    t_eq('a leading newline is refused', brand_hex(array('rail_hex' => "\n#01243D"), 'rail_hex'), '');
    t_eq('a trailing space is refused', brand_hex(array('rail_hex' => '#01243D '), 'rail_hex'), '');
    //* The /D case, and the only one PCRE lets through without it.
    t_eq('a TRAILING newline is refused', brand_hex(array('rail_hex' => "#01243D\n"), 'rail_hex'), '');
}

/* ---- classic's own contrast helpers still hold --------------------------- */
t_ok('black on white is ~21:1', abs(brand_contrast('#000000', '#FFFFFF') - 21.0) < 0.05);
t_ok('brand_readable walks to the ratio it was asked for',
    brand_contrast(brand_readable('#FFD700', 50, '#FFFFFF', 4.5), '#FFFFFF') >= 4.5);

$matrix = array();
foreach (h_variant_matrix() as $row) {
    $matrix[] = brand_logo_variant_pref($row[0], $row[1], $row[2]);
}
echo 'MATRIX ' . json_encode($matrix) . "\n";

t_done();
