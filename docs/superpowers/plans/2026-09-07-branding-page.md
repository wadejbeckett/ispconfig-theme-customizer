# Branding Page Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `interface/web/customizer/`'s Branding page as a two-column page with a sticky live preview, add the `rail_hex_light` brand key end-to-end, and add a read-only admin-only `preview.php` endpoint so variant resolution stays in PHP.

**Architecture:** The page stays a stock ISPConfig tform module — same form definition, same `db_table`, same auth preset, one new field. The redesign is markup plus an inline `<style>` and `<script>` inside `templates/customizer_edit.htm`; every colour in that CSS is `var(--pz-…, var(--nz-…, <stock fallback>))`, so the page inherits phosphor's look, clarity's, or stock's from whichever design is active. Live colour, panel-name and rail-ink feedback is painted in JS from the same measured-contrast rule the readers use; anything that depends on logo-variant resolution is fetched from the new `preview.php`, which runs the existing PHP resolvers and returns JSON — no fourth (JavaScript) copy of the resolver is created.

**Tech Stack:** PHP 8.x (ISPConfig 3.3.1p1 tform framework, vlibTemplate), vanilla ES5-compatible JS with `fetch`/`FormData`/`DOMParser`, plain CSS with custom properties and one container query, `tests/brand/` (hand-rolled TAP-ish probes run by `php tests/brand/run.php`), GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-09-07-phosphor-design.md` — sections "Branding page redesign", "Testing", "Accessibility" and the token table. The phosphor design itself is a **sibling plan** and is out of scope here.

## Global Constraints

- **No ISPConfig core file is modified.** Everything ships under `interface/web/customizer/` and `themes/<design>/`.
- **The page renders through the active design.** It uses the stock tform templates plus this module's own `templates/customizer_edit.htm` and a small inline CSS/JS block. No design-specific colour may appear: every colour is `var(--pz-…, var(--nz-…, <stock fallback>))`. It must look right under clarity, classic and phosphor.
- **CSS class prefix is `nz-`**, this module's own prefix — *not* `pz-`. The mockup uses `pz-` because it is phosphor's own stylesheet; the shipping page is design-agnostic. `--pz-*` appears only as the first link of a `var()` fallback chain.
- **The session-tab pin stays exactly where it is:** `$_SESSION['s']['form']['tab'] = 'branding';` before `$app->tform_actions->onLoad()` in `customizer_edit.php`. Do not move, wrap or condition it.
- **The two-step fetch uploader stays.** `wireUpload()` and `wireRemove()` in the template, and the GET token-minting branch of `logo_upload.php`, are carried over verbatim. The click-time CSRF mint exists because ISPConfig's session store has no locking; it must never be reimplemented or replaced with the stock iframe uploader.
- **`preview.php` is read-only.** It starts the session through `app.inc.php`, runs the same three admin checks in the same order, takes the form's current values as POST, returns JSON, and writes nothing — no `datalogUpdate`, no `UPDATE`/`INSERT`/`REPLACE`/`DELETE`, no `$app->conf()` write, no filesystem write, and **no CSRF token mint** (minting writes the session on every keystroke, which is the exact race the uploader works around).
- **`rail_hex_light` joins CI's Brand-token contract list** (open question 1 is answered "yes"), so every `themes/*/brand.php` must read it in code or document the no-op in code.
- **Seven locales**: `interface/web/customizer/lib/lang/<lang>_customizer.lng` for `de, en, es, fr, it, nl, pt`. Tooling parses `.lng` files as **text** and never `include()`s them. Values use the typographic apostrophe `’`, never an unescaped ASCII `'` inside a single-quoted PHP string, and never contain markup (wordbook values are emitted unescaped in places).
- **No new formtype**, and no change to `db_table`, `db_table_idx`, `tab_default`, `list_default` or the `auth_preset` block.
- **Local vs CI.** Local PHP is 8.3 CLI **without `mysqli`, `dom` or `mbstring`**. These run locally: `php -l`, `php tests/brand/run.php`, `php .github/scripts/lang_check.php`. These run **only in CI** (or on a test panel): `php tests/svg/run.php` (needs `dom`), everything that touches the database, and every browser check.
- **Commit at the end of every task. Do not push.**

---

## File Structure

**Modified**

- `interface/web/customizer/form/customizer.tform.php` — adds the `rail_hex_light` field; adds the `TRIM` filter and the `/D` modifier to all four colour validators.
- `interface/web/customizer/customizer_edit.php` — plumbs `rail_hex_light`; generalises the array-POST guard to every non-CHECKBOX field; verifies the config write by read-back; delegates the news-feed transition to a pure helper; publishes the field→error-text map the template's inline validation needs.
- `interface/web/customizer/lib/preview.inc.php` — gains the contrast/rail-ink pair, the widened candidate overlay, the per-colour preview block, the preview payload builder, and clarity's second nav backdrop in the chrome table.
- `interface/web/customizer/lib/dashlets.inc.php` — gains the news-feed key map and the pure transition helper (it already holds this page's non-INI toggle decisions).
- `interface/web/customizer/templates/customizer_edit.htm` — the redesign: four fieldsets, inline uploader blocks, the sticky preview column, the module CSS and the extended JS.
- `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}_customizer.lng` — new keys, three retired keys, two changed values.
- `themes/clarity/brand.php` — reads `rail_hex_light`, emits the rail token family into the light scope, and makes the nav mark mode-aware when the two rails differ.
- `themes/classic/brand.php` — reads `rail_hex_light` in code and documents the no-op.
- `.github/workflows/ci.yml` — `rail_hex_light` joins the contract key list.
- `tests/brand/run.php` — a second cross-compared matrix (`INKMATRIX`) and a third probe list for endpoint checks.
- `tests/brand/probe_module.php`, `tests/brand/probe_clarity.php`, `tests/brand/probe_classic.php`, `tests/brand/probe_render.php` — new cases.
- `README.md`, `SECURITY.md`, `UPGRADING.md`, `CONTRIBUTING.md` — the key table, the endpoint inventory, the upgrade note, the contributor tables.

**Created**

- `interface/web/customizer/preview.php` — the read-only JSON preview endpoint (auth + IO shell only; all logic lives in `lib/preview.inc.php`).
- `tests/brand/probe_tform.php` — drives every REGEX validator in the form definition over an adversarial grid and asserts the declared filters.
- `tests/brand/probe_preview.php` — structural probe of `preview.php`: the three checks in order, no write verbs, the JSON/no-store headers, the method and header gates.

**Not touched:** `install.sh` (the module directory is deployed wholesale by `deploy "$SRC" "$DEST" …` at `install.sh:681`, so a new file in it needs no installer change — verify, do not assume), `logo_upload.php`, `logo_delete.php`, `lib/svg_guard.inc.php`, `lib/module.conf.php`, `lib/<lang>.lng` (the flat nav wordbook), `mockup/`.

---

## Task 1: `rail_hex_light` in the form definition, and the colour validators' missing TRIM and `/D`

**Files:**
- Modify: `interface/web/customizer/form/customizer.tform.php` (the `accent_hex`, `rail_hex`, `login_bg` field blocks; a new `rail_hex_light` block after `rail_hex`)
- Modify: `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}_customizer.lng` (one new key each)
- Create: `tests/brand/probe_tform.php`
- Modify: `tests/brand/run.php` (register the new probe)

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: the form field `rail_hex_light` (datatype `VARCHAR`, formtype `TEXT`, default `''`, validator `REGEX` `/^(#[0-9A-Fa-f]{6})?$/D`, errmsg key `rail_hex_light_error_regex`); the wordbook key `rail_hex_light_error_regex`; `tests/brand/probe_tform.php` runnable standalone as `php tests/brand/probe_tform.php`.

**Background the implementer needs:**

`tform_base::validateField()` does `$validator['regex'] .= 's';` (`.refs/ispconfig3/interface/lib/classes/tform_base.inc.php:1011`) before `preg_match`, so a pattern written `/…/D` becomes `/…/Ds` — both modifiers are valid together and order is irrelevant. `tform_base::filterField()` implements `TRIM` as plain `trim()`. Without `/D`, PCRE's `$` also matches immediately before a trailing newline, so `"#FF0000\n"` would validate; `db->quote()` then turns the LF into a literal `\n` and the readers' `stripslashes()` collapses it to a bare `n`. `logo_url` already carries both the filter and the modifier with that reasoning written out at `customizer.tform.php:73-104`; the three colour fields were the odd ones out. `encode()` runs twice per save in this module, so a SAVE filter is applied twice — `trim()` is idempotent, which is why `logo_url` can already carry it.

**One deliberate departure from the spec.** The spec's Branding-page section says "Server-side tform validators remain authoritative and unchanged." They stay authoritative — nothing on the client can block or bypass a save. But *unchanged* is not kept: the 2026-09-06 review found the three colour validators missing the `TRIM` filter and the `/D` anchor that every other pattern in this module carries and documents as load-bearing, and this task is the one that touches that code. Widening the fix to a fourth, brand-new field at the same moment is cheaper and safer than shipping the new field with the same defect and correcting all four later.

- [ ] **Step 1: Write the failing test**

Create `tests/brand/probe_tform.php`:

```php
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
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php tests/brand/probe_tform.php`
Expected: FAIL — `rail_hex_light exists` fails, and `accent_hex declares the SAVE-time TRIM filter`, `accent_hex's pattern carries /D` (and the `rail_hex` / `login_bg` equivalents) fail. Exit status 1.

- [ ] **Step 3: Add the new field and fix the three existing validators**

In `interface/web/customizer/form/customizer.tform.php`, replace the `accent_hex`, `rail_hex` and `login_bg` blocks with the versions below, and insert `rail_hex_light` between `rail_hex` and `login_bg`:

```php
        //* The four colours share one filter and one anchored pattern, for the
        //* reasons logo_url spells out above and which apply here unchanged.
        //* TRIM because a browser does not strip a trailing space from a text
        //* input and a pasted "#0065AB " would otherwise fail an anchored
        //* pattern with an opaque error on a field that looks correct; /D
        //* because tform_base.inc.php:1011 appends only "s", and without /D
        //* PCRE's "$" also matches just before a final newline — so "#0065AB\n"
        //* would validate, db->quote() would store a literal backslash-n, and
        //* the readers' stripslashes() would collapse it to "#0065ABn": a hex
        //* that fails every reader's regex, with no error ever shown. encode()
        //* runs twice per save in this module; trim() is idempotent, which is
        //* why the same filter is safe here as on logo_url.
        'accent_hex' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'TRIM'),
            ),
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/D', 'errmsg' => 'accent_hex_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'rail_hex' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'TRIM'),
            ),
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/D', 'errmsg' => 'rail_hex_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        //* The light-mode twin of rail_hex, and the ONE key this redesign adds.
        //*
        //* A design with a light colour mode paints its rail from this value
        //* there and falls back to rail_hex when it is empty — clarity does
        //* exactly that. A design with no light scope (classic; phosphor when it
        //* lands) READS the key and documents the no-op, which is what keeps
        //* CI's contract list a list of keys every design has answered for
        //* rather than a list some designs quietly ignore.
        //*
        //* Same filter and the same anchored pattern as the other three colours,
        //* character for character; only the errmsg differs, so the operator is
        //* told which field they got wrong.
        'rail_hex_light' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'TRIM'),
            ),
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/D', 'errmsg' => 'rail_hex_light_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),

        'login_bg' => array(
            'datatype' => 'VARCHAR',
            'formtype' => 'TEXT',
            'filters'  => array(
                0 => array('event' => 'SAVE', 'type' => 'TRIM'),
            ),
            'validators' => array(
                0 => array('type' => 'REGEX', 'regex' => '/^(#[0-9A-Fa-f]{6})?$/D', 'errmsg' => 'login_bg_error_regex'),
            ),
            'default' => '',
            'value'   => ''
        ),
```

Also extend the `[branding]` key list in the file's own header docblock (`customizer.tform.php:19-22`) so it reads `… favicon_url, accent_hex, rail_hex, rail_hex_light, login_bg, show_ispconfig_credit …`.

- [ ] **Step 4: Add the error-message key to all seven wordbooks**

Add one line to each `interface/web/customizer/lib/lang/<lang>_customizer.lng`, immediately after that file's `rail_hex_error_regex` line:

```php
// en
$wb['rail_hex_light_error_regex'] = 'Sidebar colour (light mode) must be a hex value like #E7EBF0.';
// de
$wb['rail_hex_light_error_regex'] = 'Die Farbe der Seitenleiste (heller Modus) muss ein Hex-Wert wie #E7EBF0 sein.';
// es
$wb['rail_hex_light_error_regex'] = 'El color de la barra lateral (modo claro) debe ser un valor hexadecimal como #E7EBF0.';
// fr
$wb['rail_hex_light_error_regex'] = 'La couleur de la barre latérale (mode clair) doit être une valeur hexadécimale comme #E7EBF0.';
// it
$wb['rail_hex_light_error_regex'] = 'Il colore della barra laterale (modalità chiara) deve essere un valore esadecimale come #E7EBF0.';
// nl
$wb['rail_hex_light_error_regex'] = 'De zijbalkkleur (lichte modus) moet een hex-waarde zoals #E7EBF0 zijn.';
// pt
$wb['rail_hex_light_error_regex'] = 'A cor da barra lateral (modo claro) tem de ser um valor hexadecimal como #E7EBF0.';
```

- [ ] **Step 5: Register the probe with the runner**

In `tests/brand/run.php`, after the `$renders` array, add a third list and run it the same way. Insert the array declaration:

```php
// Probes that assert STRUCTURE rather than a decision: they emit no matrix and
// take no argument. Kept separate from $probes so run.php never demands a
// decision matrix from a file that has no decision to report.
$checks = array(
    'tform' => __DIR__ . '/probe_tform.php',
);
```

and immediately after the `foreach ($renders as $name => $spec) { … }` loop, add:

```php
foreach ($checks as $name => $path) {
    echo "\n== $name ==\n";
    $out = array();
    $rc  = 0;
    exec(escapeshellarg($php) . ' ' . escapeshellarg($path) . ' 2>&1', $out, $rc);
    foreach ($out as $line) {
        echo "$line\n";
    }
    if ($rc !== 0) {
        $fail++;
        echo "-- $name probe exited $rc\n";
    }
}
```

- [ ] **Step 6: Run the tests and confirm they pass**

Run:
```bash
php -l interface/web/customizer/form/customizer.tform.php
find interface -name '*.lng' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
php .github/scripts/lang_check.php
php tests/brand/probe_tform.php
php tests/brand/run.php
```
Expected: `No syntax errors detected` for the tform file; no `LINT FAIL` lines; `language files OK` with `tform wordbook: 7 file(s) match en_customizer.lng (80 keys)`; the tform probe ends `# N passed, 0 failed`; the suite ends `brand suite passed`.

- [ ] **Step 7: Commit**

```bash
git add interface/web/customizer/form/customizer.tform.php \
        interface/web/customizer/lib/lang/*_customizer.lng \
        tests/brand/probe_tform.php tests/brand/run.php
git commit -m "Add rail_hex_light, and the TRIM + /D the colour validators were missing"
```

---

## Task 2: Plumb `rail_hex_light` through the controller, and guard every field against an array POST

**Files:**
- Modify: `interface/web/customizer/lib/preview.inc.php` (add `customizer_posted_string()`; `customizer_logo_variant_posted()` delegates to it)
- Modify: `interface/web/customizer/customizer_edit.php` (`$branding_keys`, `onShowEdit()`'s `dataRecord`, `onBeforeUpdate()`'s normalisation and guard loops)
- Modify: `tests/brand/probe_module.php`

**Interfaces:**
- Consumes: the form field `rail_hex_light` from Task 1.
- Produces: `customizer_posted_string($raw)` → `''` for `null`, the value unchanged for a string, and the literal `'invalid'` for anything else. `rail_hex_light` is read into `$this->dataRecord` on display and written into `[branding]` on save.

**Background:** `onBeforeUpdate()` already guarantees a string reaches the framework for the two SELECTs, because an array subject makes `preg_match()` a `TypeError` on PHP 8 — a fatal on an admin page instead of a validation error. The identical hazard is unguarded on the seven validated/filtered TEXT fields (`strip_tags(array)` and `trim(array)` are also PHP 8 TypeErrors). CHECKBOX fields are deliberately excluded: `tform_base::_encode` already converts arrays to strings for RADIO and CHECKBOX (`tform_base.inc.php:819-823`), and the two existing CHECKBOX-only loops must stay CHECKBOX-only for the reasons written beside them.

- [ ] **Step 1: Write the failing test**

Append to `tests/brand/probe_module.php`, immediately after the existing `customizer_logo_variant_posted()` block:

```php
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
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php tests/brand/probe_module.php`
Expected: FAIL — `customizer_posted_string() exists` fails and the guarded block is skipped; exit status 1.

- [ ] **Step 3: Add the helper**

In `interface/web/customizer/lib/preview.inc.php`, insert this function immediately **above** `customizer_logo_variant_posted()`, and rewrite that function's body to delegate:

```php
/**
 * A POSTed field value, guaranteed to be a string, and guaranteed not to have
 * been healed into a valid one.
 *
 * Every validated field on this form is one crafted POST away from a fatal: a
 * VARCHAR field posted as an array (accent_hex[]=x) reaches tform_base's
 * filters and validators with an array subject, and trim(), strip_tags() and
 * preg_match() are all TypeErrors on PHP 8 — an admin page that dies instead of
 * reporting a bad value. Neither case below is reachable from a browser; both
 * are one request away from an authenticated admin.
 *
 * The coercion has to produce something the field's own validator REJECTS.
 * Rewriting an array to '' satisfied the string requirement by writing a VALID
 * value — the validator then passed, the save succeeded, and the page said
 * "saved" having quietly reset the operator's choice. 'invalid' matches none of
 * this form's three patterns (the hex, the reference and the variant one), so a
 * malformed POST is reported like any other bad value.
 *
 * A genuinely ABSENT field still becomes '': there is nothing to report in that
 * case, and the framework already treats a missing VARCHAR as '' one layer down
 * (tform_base.inc.php:830).
 *
 * CHECKBOX fields are deliberately NOT put through this — see the caller.
 */
function customizer_posted_string($raw) {
    if($raw === null)   return '';
    if(is_string($raw)) return $raw;
    return 'invalid';
}

/**
 * The logo-variant flavour of the above, kept as its own name because the two
 * SELECTs' contract is documented under it and probe_module tests it by name.
 * One line, so the two can never disagree about the token.
 */
function customizer_logo_variant_posted($raw) {
    return customizer_posted_string($raw);
}
```

Leave the long docblock that currently sits above `customizer_logo_variant_posted()` where it is; it explains the contract and still applies.

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php tests/brand/probe_module.php`
Expected: PASS — `# N passed, 0 failed`.

- [ ] **Step 5: Plumb `rail_hex_light` and widen the guard in the controller**

In `interface/web/customizer/customizer_edit.php`:

(a) add the key to `$branding_keys` (it is a form field, so it belongs on the wholesale-overwrite list):

```php
    private $branding_keys = array('logo_url', 'logo_url_on_dark', 'logo_variant_nav', 'logo_variant_login', 'favicon_url', 'accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg', 'show_ispconfig_credit', 'show_theme_credit', 'show_version', 'show_design_picker');
```

(b) in `onShowEdit()`, add the read immediately after the `rail_hex` line:

```php
                'rail_hex'              => isset($branding['rail_hex']) ? $branding['rail_hex'] : '',
                'rail_hex_light'        => isset($branding['rail_hex_light']) ? $branding['rail_hex_light'] : '',
```

(c) in `onBeforeUpdate()`, replace the colour-normalisation loop and the two-field variant loop with these two loops:

```php
        //* Guarantee a STRING reaches the framework for every field it is about
        //* to filter and validate. CHECKBOX is excluded on purpose: _encode
        //* already converts arrays to strings for RADIO and CHECKBOX
        //* (tform_base.inc.php:819-823), and the two CHECKBOX-only loops
        //* elsewhere in this file must stay CHECKBOX-only for the reasons
        //* written beside them. This loop is their exact complement.
        //*
        //* A STRING is left untouched on purpose: the validator must stay the
        //* thing that rejects a bad token, so a wrong value is reported rather
        //* than silently healed. '' is a legitimate posted value for several of
        //* these fields and must survive unchanged.
        foreach($app->tform->formDef['tabs'][$this->active_tab]['fields'] as $key => $field) {
            if($field['formtype'] === 'CHECKBOX') continue;
            $this->dataRecord[$key] = customizer_posted_string(
                isset($this->dataRecord[$key]) ? $this->dataRecord[$key] : null
            );
        }

        //* Users paste colours without the leading '#', and colour pickers hand
        //* back lowercase — normalise those into what the REGEX validators
        //* accept. /D on both patterns for the same reason the validators carry
        //* it: without it "$" also matches before a final newline, so a pasted
        //* trailing LF would be '#'-prefixed and upper-cased here and then pass
        //* a validator that should have rejected it. The SAVE-time TRIM filter
        //* runs later, inside encode(); this runs on the raw POST, so it does
        //* its own trim() first.
        foreach(array('accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg') as $k) {
            $v = trim($this->dataRecord[$k]);
            if(preg_match('/^[0-9A-Fa-f]{6}$/D', $v)) $v = '#' . $v;
            if(preg_match('/^#[0-9A-Fa-f]{6}$/D', $v)) $v = strtoupper($v);
            $this->dataRecord[$k] = $v;
        }
```

Note the second loop no longer needs `isset()`/`is_string()` guards: the first loop has already made every non-CHECKBOX field a string. `$this->active_tab` is what the surrounding method already uses.

- [ ] **Step 6: Verify**

Run:
```bash
php -l interface/web/customizer/customizer_edit.php
php -l interface/web/customizer/lib/preview.inc.php
php tests/brand/run.php
grep -n "\$_SESSION\['s'\]\['form'\]\['tab'\] = 'branding';" interface/web/customizer/customizer_edit.php
```
Expected: no syntax errors; `brand suite passed`; the grep prints the session-tab pin line, proving it is still there and still above `onLoad()`.

CI/panel only (state this in the commit body, do not attempt locally): saving the form with a `rail_hex_light` value and re-opening the page shows it retained.

- [ ] **Step 7: Commit**

```bash
git add interface/web/customizer/customizer_edit.php \
        interface/web/customizer/lib/preview.inc.php \
        tests/brand/probe_module.php
git commit -m "Store and re-read rail_hex_light; make every posted field a string before tform sees it"
```

---

## Task 3: A save that cannot lie, and a news-feed stash that cannot be lost

**Files:**
- Modify: `interface/web/customizer/lib/dashlets.inc.php` (add `customizer_news_feed_keys()` and `customizer_news_feed_apply()`)
- Modify: `interface/web/customizer/customizer_edit.php` (`onUpdateSave()`)
- Modify: `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}_customizer.lng` (one new key each)
- Modify: `tests/brand/probe_module.php`

**Interfaces:**
- Consumes: nothing from Tasks 1–2 beyond the file being open.
- Produces: `customizer_news_feed_keys()` → `array('dashboard_atom_url_admin' => 'news_url_admin', 'dashboard_atom_url_reseller' => 'news_url_reseller', 'dashboard_atom_url_client' => 'news_url_client')`; `customizer_news_feed_apply(array $misc, array $stash, $show)` → `array('misc' => array(<atom key> => string), 'stash' => array(<stash key> => string))`; the wordbook key `save_failed_txt`.

**Background — two defects, both in `onUpdateSave()`:**

1. `$app->db->datalogUpdate()` discards its own `query()`'s return value and unconditionally `return true;` (`.refs/ispconfig3/interface/lib/classes/db_mysql.inc.php:811-843`), and `tform_actions::onUpdate()` redirects to `list_default` — `customizer_edit.php?id=1&msg=saved` — unconditionally at its line 167. So a failed write printed "Settings saved." over values that were never stored. Raising `errorMessage` here cannot help: the framework tested it *before* calling this hook. `$app->error()` renders the error template and `die()`s (`.refs/ispconfig3/interface/lib/app.inc.php`), which is the only way to stop the lie.
2. The news-feed off→on branch gates restoration on an all-or-nothing `$any_set` flag but then drops every stash entry unconditionally. If any single role's URL comes back by another route, the remaining stash entries are deleted having never been restored — permanently losing an operator's private feed URL, a core-owned value this module blanked. `bin/purge_branding.php` already uses the per-role rule this task adopts.

- [ ] **Step 1: Write the failing test**

Append to `tests/brand/probe_module.php`, after the donation-dashlet block:

```php
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
```

- [ ] **Step 2: Run it and watch it fail**

Run: `php tests/brand/probe_module.php`
Expected: FAIL — `customizer_news_feed_keys() exists` and `customizer_news_feed_apply() exists` fail; exit status 1.

- [ ] **Step 3: Add the two helpers**

Append to `interface/web/customizer/lib/dashlets.inc.php` (this file already holds the Branding page's pure decisions about state core owns — the donation dashlet — and the news feed is the other one):

```php
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
```

- [ ] **Step 4: Run the test and confirm it passes**

Run: `php tests/brand/probe_module.php`
Expected: PASS — `# N passed, 0 failed`.

- [ ] **Step 5: Use the helper, and verify the write**

In `interface/web/customizer/customizer_edit.php`'s `onUpdateSave()`, replace the whole `$atom_keys = array(…);` block through the closing `}` of the restore branch with:

```php
        //* News feed toggle -> the three stock per-role [misc] atom keys, and
        //* the module-owned stash that makes the round trip lossless. The whole
        //* decision is customizer_news_feed_apply() in lib/dashlets.inc.php,
        //* where it can be tested without a database; this is the plumbing.
        $atom_keys = customizer_news_feed_keys();
        $misc_in   = array();
        $stash_in  = array();
        foreach($atom_keys as $k => $stash_key) {
            $misc_in[$k]        = isset($config['misc'][$k]) ? $config['misc'][$k] : '';
            $stash_in[$stash_key] = isset($config['branding'][$stash_key]) ? $config['branding'][$stash_key] : '';
        }
        $news = customizer_news_feed_apply($misc_in, $stash_in,
            isset($clean['show_news_feed']) ? $clean['show_news_feed'] : '1');

        foreach($atom_keys as $k => $stash_key) {
            $config['misc'][$k] = $news['misc'][$k];
            //* An empty stash is an ABSENT key, not a key with an empty value:
            //* the stash is ours and it should not appear in the blob at all
            //* once it has been consumed.
            if($news['stash'][$stash_key] === '') {
                unset($config['branding'][$stash_key]);
            } else {
                $config['branding'][$stash_key] = $news['stash'][$stash_key];
            }
        }
```

Then replace the write block at the end of the method with:

```php
        $config_str = $app->ini_parser->get_ini_string($config);
        if($conf['demo_mode'] != true) {
            $app->db->datalogUpdate('sys_ini', array("config" => $config_str), 'sysini_id', 1);

            //* datalogUpdate() CANNOT report a failure: it discards the return
            //* value of its own query() and returns true unconditionally
            //* (db_mysql.inc.php:811-843). And tform_actions::onUpdate()
            //* redirects to list_default — "customizer_edit.php?id=1&msg=saved"
            //* — unconditionally at its line 167, so a write that never landed
            //* printed "Changes saved." over values that were never stored.
            //*
            //* Read the column back and compare it byte for byte. Raising
            //* errorMessage here would not help — the framework tested it
            //* BEFORE calling this hook, which is the same trap the demo-mode
            //* refusal in onBeforeUpdate is placed early to avoid. $app->error()
            //* renders core's error template and die()s, which is the only thing
            //* at this point in the flow that can stop the page claiming
            //* success. It also stops before save_donation_dashlet(), which is
            //* correct: the config write is the one that failed.
            $after = $app->db->queryOneRecord("SELECT config FROM sys_ini WHERE sysini_id = 1");
            if(!is_array($after) || !isset($after['config']) || (string)$after['config'] !== $config_str) {
                $app->error($app->tform->lng('save_failed_txt'));
            }

            //* Not part of the INI blob: this one lives in sys_config, because
            //* that row is what ISPConfig reads before it builds the dashlet.
            $this->save_donation_dashlet(isset($clean['show_donation_dashlet']) ? $clean['show_donation_dashlet'] : '1');
        }
```

- [ ] **Step 6: Add `save_failed_txt` to all seven wordbooks**

Add one line to each `<lang>_customizer.lng`, immediately after that file's `settings_saved_txt` line:

```php
// en
$wb['save_failed_txt'] = 'The settings were not saved — the database write did not take effect. Nothing on this page was changed; please try again, and check the panel error log if it happens twice.';
// de
$wb['save_failed_txt'] = 'Die Einstellungen wurden nicht gespeichert — der Datenbankschreibvorgang ist nicht wirksam geworden. Auf dieser Seite wurde nichts geändert; bitte erneut versuchen und bei wiederholtem Auftreten das Fehlerprotokoll des Panels prüfen.';
// es
$wb['save_failed_txt'] = 'Los ajustes no se guardaron: la escritura en la base de datos no tuvo efecto. No se cambió nada en esta página; inténtelo de nuevo y revise el registro de errores del panel si vuelve a ocurrir.';
// fr
$wb['save_failed_txt'] = 'Les paramètres n’ont pas été enregistrés : l’écriture en base de données n’a pas pris effet. Rien n’a été modifié sur cette page ; réessayez, et consultez le journal d’erreurs du panneau si cela se reproduit.';
// it
$wb['save_failed_txt'] = 'Le impostazioni non sono state salvate: la scrittura nel database non ha avuto effetto. In questa pagina non è stato modificato nulla; riprovi e, se accade di nuovo, controlli il registro degli errori del pannello.';
// nl
$wb['save_failed_txt'] = 'De instellingen zijn niet opgeslagen — de databaseschrijfactie heeft geen effect gehad. Er is niets op deze pagina gewijzigd; probeer het opnieuw en bekijk het foutenlogboek van het paneel als het nogmaals gebeurt.';
// pt
$wb['save_failed_txt'] = 'As definições não foram guardadas — a escrita na base de dados não teve efeito. Nada nesta página foi alterado; tente novamente e consulte o registo de erros do painel se voltar a acontecer.';
```

- [ ] **Step 7: Verify**

Run:
```bash
php -l interface/web/customizer/customizer_edit.php
php -l interface/web/customizer/lib/dashlets.inc.php
find interface -name '*.lng' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
php .github/scripts/lang_check.php
php tests/brand/run.php
```
Expected: no syntax errors; no `LINT FAIL`; `language files OK` (81 tform keys); `brand suite passed`.

CI/panel only: toggling the news feed off and on with a custom admin feed URL set, then confirming the URL survives; and confirming a save still shows the confirmation banner.

- [ ] **Step 8: Commit**

```bash
git add interface/web/customizer/customizer_edit.php \
        interface/web/customizer/lib/dashlets.inc.php \
        interface/web/customizer/lib/lang/*_customizer.lng \
        tests/brand/probe_module.php
git commit -m "Verify the settings write, and restore news-feed URLs per role instead of all-or-nothing"
```

---

## Task 4: The preview's colour model — measured rail ink, candidate values, and the payload

**Files:**
- Modify: `interface/web/customizer/lib/preview.inc.php` (four new functions)
- Modify: `tests/brand/probe_module.php` (behaviour + an `INKMATRIX` line)
- Modify: `tests/brand/probe_clarity.php` (an `INKMATRIX` line derived from the shipped output)
- Modify: `tests/brand/run.php` (cross-compare `INKMATRIX` across the probes that emit one)

**Interfaces:**
- Consumes: `customizer_luminance()`, `customizer_logo_resolve()`, `customizer_logo_surfaces_all()`, `customizer_favicon_resolve()`, `customizer_logo_preview_html()`, `customizer_favicon_preview_html()` — all already in `lib/preview.inc.php`.
- Produces, all in `lib/preview.inc.php`:
  - `customizer_contrast($a, $b)` → float, WCAG 2.x ratio of two validated `#rrggbb`.
  - `customizer_rail_ink($hex)` → `'#FFFFFF'`, `'#000000'`, or `''` for an invalid hex.
  - `customizer_branding_with_posted_candidates($stored, $posted)` → the `[branding]` array with the ten form-owned candidate keys overlaid, each only if it is a string that passes that key's own rule.
  - `customizer_preview_colour($branding, $key)` → `array('hex' => string, 'ink' => string, 'ratio' => float|null)`.
  - `customizer_preview_payload($stored_branding, $custom_logo, $posted, $designs, $labels, $texts)` → the JSON-shaped array documented in the code below. Task 7's `preview.php` calls exactly this.

**Background:** clarity's `brand_rail_vars()` chooses ink DIRECTION by comparing the white and black contrast ratios rather than pivoting on a lightness constant (`themes/clarity/brand.php`, the `brand_contrast('#FFFFFF', $rail) >= brand_contrast('#000000', $rail)` branch). The module cannot call that function — `brand.php` is a pre-auth endpoint that opens a database connection and prints a stylesheet on include — so the preview carries its own copy of the *direction* rule, and the tests cross-compare the two on every rail in `h_rails()`. It deliberately does **not** copy the ink VALUES: clarity walks those out of the rail's own hue up an alpha ladder, and the preview shows the direction and the measured ratio, not the exact token. **This equivalence was checked against the shipped `brand_rail_vars()` before this plan was written: all 17 rails in `h_rails()` agree.**

- [ ] **Step 1: Write the failing test — behaviour**

Append to `tests/brand/probe_module.php`, before the `MATRIX` block at the end of the file:

```php
/* ---- rail ink, measured rather than pivoted ------------------------------
 * The Branding page has to tell the operator, while they are typing, what
 * colour their sidebar text will be. That decision is clarity's
 * brand_rail_vars(), which compares the white and black contrast ratios rather
 * than pivoting on a lightness constant — a rail like #767676, sitting between
 * the two common pivots, is handed the WORSE ink by any rule that rounds.
 *
 * brand.php cannot be included (it is a pre-auth endpoint that connects to the
 * database and prints CSS on include), so the DIRECTION rule is copied here and
 * run.php cross-compares the two copies over h_rails() via the INKMATRIX line at
 * the bottom of this file. The ink VALUES are deliberately not copied: clarity
 * walks those out of the rail's own hue up an alpha ladder, and this preview
 * promises the direction and the ratio, not the token.
 */
t_ok('customizer_contrast() exists', function_exists('customizer_contrast'));
if (function_exists('customizer_contrast')) {
    t_ok('black on white is ~21:1', abs(customizer_contrast('#000000', '#FFFFFF') - 21.0) < 0.05);
    t_ok('a colour against itself is 1:1', abs(customizer_contrast('#0065AB', '#0065AB') - 1.0) < 1e-9);
    foreach (h_rails() as $hex) {
        t_ok("contrast against white agrees with the spec for $hex",
            abs(customizer_contrast('#FFFFFF', $hex) - h_contrast('#FFFFFF', $hex)) < 1e-9);
    }
}

t_ok('customizer_rail_ink() exists', function_exists('customizer_rail_ink'));
if (function_exists('customizer_rail_ink')) {
    t_eq('a navy rail takes white ink', customizer_rail_ink('#01243D'), '#FFFFFF');
    t_eq('a white rail takes dark ink', customizer_rail_ink('#FFFFFF'), '#000000');
    t_eq('a mid grey takes the ink that measures better', customizer_rail_ink('#767676'), '#000000');
    t_eq('an invalid hex answers nothing rather than guessing', customizer_rail_ink('red'), '');
    t_eq('a trailing newline is not a valid hex', customizer_rail_ink("#FFFFFF\n"), '');
    t_eq('a non-string is not fatal', customizer_rail_ink(array('#FFFFFF')), '');
    foreach (h_rails() as $hex) {
        $ink = customizer_rail_ink($hex);
        t_ok("$hex: the chosen ink is the better-measuring of the two",
            customizer_contrast($ink, $hex) >= customizer_contrast(($ink === '#FFFFFF' ? '#000000' : '#FFFFFF'), $hex),
            "$ink on $hex");
    }
}

/* ---- candidate values from the form, not from the store ------------------
 * The live preview describes the values the operator is LOOKING AT, which are
 * not yet stored. The overlay is allowlisted key by key and each candidate is
 * accepted only if it passes that key's own rule — an invalid candidate falls
 * back to the stored value, which is exactly what a save would leave in place,
 * because a save with an invalid value is refused outright.
 *
 * This is deliberately a SECOND, wider function beside
 * customizer_branding_with_posted_variants(): that one takes the two variant
 * keys alone and is used on the validation-error redisplay, where letting
 * arbitrary POST keys into the blob would be a much wider contract than that
 * page needs. This one is the preview endpoint's, and is wider on purpose.
 */
t_ok('customizer_branding_with_posted_candidates() exists',
    function_exists('customizer_branding_with_posted_candidates'));
if (function_exists('customizer_branding_with_posted_candidates')) {
    $stored = array('rail_hex' => '#01243D', 'accent_hex' => '#0065AB',
                    'logo_url' => '/themes/custom/a.svg', 'logo_variant_nav' => '');

    $m = customizer_branding_with_posted_candidates($stored, array('rail_hex' => '#FFFFFF'));
    t_eq('a valid candidate colour wins', $m['rail_hex'], '#FFFFFF');
    t_eq('an untouched key keeps its stored value', $m['accent_hex'], '#0065AB');

    t_eq('a hex without its hash is repaired, as onBeforeUpdate repairs it',
        customizer_branding_with_posted_candidates($stored, array('rail_hex' => 'ffffff'))['rail_hex'], '#FFFFFF');
    t_eq('a hex with surrounding space is trimmed',
        customizer_branding_with_posted_candidates($stored, array('rail_hex' => ' #ffffff '))['rail_hex'], '#FFFFFF');
    t_eq('an invalid candidate colour leaves the stored one showing',
        customizer_branding_with_posted_candidates($stored, array('rail_hex' => 'nonsense'))['rail_hex'], '#01243D');
    t_eq('a trailing newline does not make a candidate valid',
        customizer_branding_with_posted_candidates($stored, array('rail_hex' => "#FFFFFF\n"))['rail_hex'], '#01243D');
    t_eq('a blank candidate is a real value — it means "the design\'s own"',
        customizer_branding_with_posted_candidates($stored, array('rail_hex' => ''))['rail_hex'], '');

    t_eq('the new light rail is carried',
        customizer_branding_with_posted_candidates($stored, array('rail_hex_light' => '#E7EBF0'))['rail_hex_light'], '#E7EBF0');

    t_eq('a valid reference path is carried',
        customizer_branding_with_posted_candidates($stored, array('logo_url' => '/themes/custom/b.svg'))['logo_url'],
        '/themes/custom/b.svg');
    t_eq('a protocol-relative reference is refused',
        customizer_branding_with_posted_candidates($stored, array('logo_url' => '//evil.example/b.svg'))['logo_url'],
        '/themes/custom/a.svg');
    t_eq('an http reference is refused',
        customizer_branding_with_posted_candidates($stored, array('logo_url' => 'http://x.example/b.svg'))['logo_url'],
        '/themes/custom/a.svg');

    t_eq('a variant candidate is carried',
        customizer_branding_with_posted_candidates($stored, array('logo_variant_nav' => 'on_dark'))['logo_variant_nav'], 'on_dark');
    t_eq('an unrecognised variant is refused',
        customizer_branding_with_posted_candidates($stored, array('logo_variant_nav' => 'garbage'))['logo_variant_nav'], '');

    $wide = customizer_branding_with_posted_candidates($stored,
        array('logo_on_dark' => 'data:image/png;base64,AA', 'favicon' => 'data:image/png;base64,AA',
              'custom_logo' => 'data:image/png;base64,AA', 'show_version' => '0'));
    t_ok('an uploaded image cannot be introduced by a POST', !isset($wide['logo_on_dark']) && !isset($wide['favicon']));
    t_ok('an unrelated key cannot be introduced by a POST', !isset($wide['show_version']));
    t_ok('a non-array POST is ignored',
        customizer_branding_with_posted_candidates($stored, null) === $stored);
}

/* ---- one colour, as the preview column needs it -------------------------- */
t_ok('customizer_preview_colour() exists', function_exists('customizer_preview_colour'));
if (function_exists('customizer_preview_colour')) {
    $c = customizer_preview_colour(array('rail_hex' => '#01243D'), 'rail_hex');
    t_eq('the hex comes back normalised', $c['hex'], '#01243D');
    t_eq('with the ink that will be printed on it', $c['ink'], '#FFFFFF');
    t_ok('and the measured ratio', abs($c['ratio'] - round(h_contrast('#FFFFFF', '#01243D'), 2)) < 1e-9, $c['ratio']);

    $u = customizer_preview_colour(array(), 'rail_hex');
    t_eq('an unset colour says so rather than inventing one', $u,
        array('hex' => '', 'ink' => '', 'ratio' => null));
    t_eq('an invalid stored colour is treated as unset',
        customizer_preview_colour(array('rail_hex' => 'nonsense'), 'rail_hex'),
        array('hex' => '', 'ink' => '', 'ratio' => null));
}

/* ---- the whole payload the endpoint returns ------------------------------
 * Built here rather than in preview.php so it can be tested without a database,
 * an HTTP request or a session. preview.php is then an auth-and-IO shell with no
 * decision of its own in it.
 */
t_ok('customizer_preview_payload() exists', function_exists('customizer_preview_payload'));
if (function_exists('customizer_preview_payload')) {
    $png = 'data:image/png;base64,iVBORw0KGgo=';
    $texts = array('no_logo' => 'NOLOGO', 'fallback_from_dark' => 'FBDARK',
                   'fallback_from_light' => 'FBLIGHT', 'no_favicon' => 'NOFAV',
                   'favicon_url_wins' => 'FAVURL');

    $p = customizer_preview_payload(
        array('rail_hex' => '#01243D'), $png,
        array('rail_hex' => '#FFFFFF'), array('clarity'),
        array('nav' => 'Navigation', 'login' => 'Login screen'), $texts);

    t_ok('the three preview rows are rendered', isset($p['previews']['used_logo'],
        $p['previews']['used_logo_on_dark'], $p['previews']['used_favicon']));
    t_ok('the logo row carries the stored artwork', strpos($p['previews']['used_logo'], $png) !== false);
    t_ok('the favicon row says nothing is set', strpos($p['previews']['used_favicon'], 'NOFAV') !== false);

    t_eq('the candidate rail is what the colours block reports', $p['colours']['rail']['hex'], '#FFFFFF');
    t_eq('...with the ink measured for it', $p['colours']['rail']['ink'], '#000000');
    t_eq('an unset accent is reported unset', $p['colours']['accent']['hex'], '');

    //* rail_hex_light falls back to rail_hex: a design with a light scope paints
    //* the same rail in both modes until the operator says otherwise, and the
    //* preview must show the operator that, not an empty box.
    t_eq('the light rail inherits the rail when it is unset', $p['colours']['rail_light']['hex'], '#FFFFFF');
    t_eq('...and says that it inherited', $p['colours']['rail_light']['inherited'], true);
    $p2 = customizer_preview_payload(array(), '', array('rail_hex' => '#01243D', 'rail_hex_light' => '#E7EBF0'),
        array('clarity'), array(), $texts);
    t_eq('a set light rail is its own value', $p2['colours']['rail_light']['hex'], '#E7EBF0');
    t_eq('...and says it did not inherit', $p2['colours']['rail_light']['inherited'], false);

    //* The surfaces list is the resolver's own output, so the caller can show
    //* which mark each surface of each installed design ends up with.
    t_ok('the surfaces list is carried', is_array($p['surfaces']) && count($p['surfaces']) > 0);
    foreach ($p['surfaces'] as $s) {
        t_ok('every surface entry names a design, a surface, a variant and a colour',
            isset($s['design'], $s['surface'], $s['variant'], $s['bg']));
    }

    //* Nothing the operator typed as free text is echoed back. The page renders
    //* the panel name itself, from the input, as text — a name round-tripped
    //* through JSON into innerHTML would be a new path for no gain.
    t_ok('no free text is echoed back', !isset($p['company_name']));

    //* A POST that is not an array, and one carrying nothing this reads.
    $p3 = customizer_preview_payload(array('rail_hex' => '#01243D'), '', null, array('clarity'), array(), $texts);
    t_eq('a non-array POST previews the stored values', $p3['colours']['rail']['hex'], '#01243D');
}
```

- [ ] **Step 2: Add the `INKMATRIX` line to `probe_module.php`**

Immediately before the existing `echo 'MATRIX ' . json_encode($matrix) . "\n";` at the end of the file, add:

```php
/* The ink DIRECTION on every rail in the shared grid, cross-compared by run.php
 * against the direction clarity's shipped brand_rail_vars() actually emits.
 * 'white' or 'dark' — not the token, because the two are allowed to differ in
 * value and must never differ in direction. */
$ink = array();
foreach (h_rails() as $rail) {
    $ink[] = (customizer_rail_ink($rail) === '#FFFFFF') ? 'white' : 'dark';
}
echo 'INKMATRIX ' . json_encode($ink) . "\n";
```

- [ ] **Step 3: Add the `INKMATRIX` line to `probe_clarity.php`**

Immediately before the existing `echo 'MATRIX ' . json_encode($matrix) . "\n";` at the end of that file, add:

```php
/* The same direction, read off the SHIPPED output rather than restated: flatten
 * --nz-rail-text onto the rail and ask whether the ink is lighter or darker than
 * the thing it is printed on. Restating the branch condition here would be a
 * test of the test; this is a property of what brand_rail_vars() emits, which is
 * what the module's preview has to agree with. Any legible ink differs from its
 * backdrop by far more than a rounding error, so the comparison is unambiguous. */
$ink = array();
foreach (h_rails() as $rail) {
    $d    = h_decls(brand_rail_vars($rail));
    $flat = h_flatten($d['--nz-rail-text'], $rail);
    $ink[] = (h_lum($flat) > h_lum($rail)) ? 'white' : 'dark';
}
echo 'INKMATRIX ' . json_encode($ink) . "\n";
```

- [ ] **Step 4: Cross-compare it in `run.php`**

In `tests/brand/run.php`: add `$inkmatrix = array();` beside the existing `$matrix = array();`, extend the probe output loop to capture the new line, and add a second comparison section.

In the `foreach ($probes as $name => $path)` loop, add this branch beside the existing `MATRIX ` branch, **before** it (`INKMATRIX ` also begins with no space that would confuse the `MATRIX ` test, but ordering it first keeps the two tests independent of prefix subtleties):

```php
        if (strpos($line, 'INKMATRIX ') === 0) {
            $inkmatrix[$name] = json_decode(substr($line, 10), true);
            continue;
        }
```

After the existing `== resolver parity ==` section, add:

```php
echo "\n== rail-ink parity ==\n";
/* Opt-in, unlike the resolver matrix: classic derives its rail ink from stock's
 * own ink colour rather than from black, so it has no comparable direction to
 * report and emits no INKMATRIX. Two emitters is the minimum for a comparison
 * to mean anything, so fewer is a failure rather than a skip — that is how a
 * renamed probe silently stops being compared. */
if (count($inkmatrix) < 2) {
    $fail++;
    echo "FAIL fewer than two probes reported a rail-ink matrix (" . count($inkmatrix) . ")\n";
} else {
    $ref = null;
    $refname = '';
    foreach ($inkmatrix as $name => $rows) {
        if ($ref === null) { $ref = $rows; $refname = $name; continue; }
        if ($rows === $ref) {
            echo "ok   $name chooses the same ink direction as $refname on all " . count($ref) . " rails\n";
            continue;
        }
        $fail++;
        echo "FAIL $name disagrees with $refname about the ink direction\n";
        foreach ($ref as $i => $want) {
            $got = isset($rows[$i]) ? $rows[$i] : null;
            if ($got === $want) continue;
            echo sprintf("     rail #%d: %s says %s, %s says %s\n",
                $i, $refname, var_export($want, true), $name, var_export($got, true));
        }
    }
}
```

- [ ] **Step 5: Run the tests and watch them fail**

Run: `php tests/brand/run.php`
Expected: FAIL — `customizer_contrast() exists`, `customizer_rail_ink() exists`, `customizer_branding_with_posted_candidates() exists`, `customizer_preview_colour() exists` and `customizer_preview_payload() exists` all fail in the `module` probe, and the run ends `BRAND SUITE FAILED`.

- [ ] **Step 6: Implement the four functions**

In `interface/web/customizer/lib/preview.inc.php`, add `customizer_contrast()` and `customizer_rail_ink()` immediately after `customizer_luminance()`:

```php
/**
 * WCAG 2.x contrast ratio between two validated #rrggbb colours.
 *
 * Built on customizer_luminance() rather than carrying its own transfer
 * function, for the same reason customizer_hex_is_dark() is: a second copy of
 * the same arithmetic in the same file is a drift hazard with nothing to catch
 * it. Trusts its input, like the function it is built on.
 */
function customizer_contrast($a, $b) {
    $la = customizer_luminance($a);
    $lb = customizer_luminance($b);
    return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
}

/**
 * Which ink a rail of this colour will carry: '#FFFFFF' or '#000000', or '' when
 * the value is not a valid hex.
 *
 * This is the DIRECTION half of brand_rail_vars() in themes/clarity/brand.php,
 * and it is a copy for the same reason every other copy in this file is one:
 * brand.php is a PRE-AUTH endpoint that opens a database connection and prints a
 * stylesheet the moment it is included, so nothing here can call into it.
 *
 * Direction by MEASUREMENT, not by a lightness pivot. The crossover where black
 * and white read equally well sits at luminance 0.1791, close enough to the
 * 0.184 figure usually quoted that rails between the two — #767676 is one — are
 * handed the WORSE ink by any rule that rounds. Comparing the two ratios is
 * exact and needs no constant. It is a different question from
 * customizer_hex_is_dark()'s 0.5, which asks "is this background more dark than
 * light" in order to choose between two finished ARTWORKS; choosing INK has to
 * respect a contrast ratio, and the two thresholds must not be merged.
 *
 * What is deliberately NOT copied is the ink VALUE. clarity walks its inks out
 * of the rail's own hue up an alpha ladder so a branded rail still looks
 * branded, and reproducing that here would be a fourth implementation of
 * something with no contract to hold it. The Branding page promises the
 * direction and the measured ratio, which is what an operator needs before they
 * commit a colour — and tests/brand/run.php's rail-ink parity step compares this
 * direction against the one clarity's shipped output actually takes, on every
 * rail in the shared grid.
 */
function customizer_rail_ink($hex) {
    if(!is_string($hex) || preg_match('/^#[0-9A-Fa-f]{6}$/D', $hex) !== 1) return '';
    return (customizer_contrast('#FFFFFF', $hex) >= customizer_contrast('#000000', $hex)) ? '#FFFFFF' : '#000000';
}
```

Then add the three preview-endpoint functions immediately after `customizer_branding_with_posted_variants()`:

```php
/**
 * The stored [branding] with the form's CANDIDATE values overlaid — what the
 * operator is looking at, not what is stored.
 *
 * The live preview is only worth having if it describes the unsaved page, so
 * this overlay is wider than customizer_branding_with_posted_variants(), which
 * takes the two variant keys alone and is used on the validation-error
 * redisplay. Both are allowlists, and both stay allowlists: the result is handed
 * to the resolvers and rendered into an admin page, so a POST must never be able
 * to introduce a key nobody expected.
 *
 * What it will NOT take from a POST, deliberately: the three uploaded images
 * (custom_logo, logo_on_dark, favicon). Those are not form fields — they are
 * written by logo_upload.php and are refreshed from the SERVER's own response —
 * so a posted one would be an image the panel does not have, previewed as
 * though it did.
 *
 * Each candidate is accepted only if it passes that key's own rule, and an
 * invalid one leaves the STORED value showing. That is exactly right rather than
 * merely safe: a save carrying an invalid value is refused outright, so the
 * stored value is what the panel would still be rendering.
 *
 * The hex repair here — trim, add a missing '#', upper-case — is the same repair
 * customizer_edit.php's onBeforeUpdate applies to a real save, so the preview
 * and the save agree about a pasted "0065ab".
 */
function customizer_branding_with_posted_candidates($stored, $posted) {
    if(!is_array($stored)) $stored = array();
    if(!is_array($posted)) return $stored;

    foreach(array('accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg') as $key) {
        if(!isset($posted[$key]) || !is_string($posted[$key])) continue;
        $v = trim($posted[$key]);
        if(preg_match('/^[0-9A-Fa-f]{6}$/D', $v) === 1) $v = '#' . $v;
        if($v === '' || preg_match('/^#[0-9A-Fa-f]{6}$/D', $v) === 1) $stored[$key] = strtoupper($v);
    }

    foreach(array('logo_url', 'logo_url_on_dark', 'favicon_url') as $key) {
        if(!isset($posted[$key]) || !is_string($posted[$key])) continue;
        $v = trim($posted[$key]);
        //* customizer_logo_ref_ok() is the same anchored allowlist the tform
        //* validator and both theme readers use; '' is separately legitimate and
        //* means "no reference", which that function reports as not-ok.
        if($v === '' || customizer_logo_ref_ok($v)) $stored[$key] = $v;
    }

    foreach(array('logo_variant_nav', 'logo_variant_login') as $key) {
        if(!isset($posted[$key]) || !is_string($posted[$key])) continue;
        if($posted[$key] === '' || $posted[$key] === 'on_light' || $posted[$key] === 'on_dark') {
            $stored[$key] = $posted[$key];
        }
    }

    return $stored;
}

/**
 * One colour as the preview column needs it: the value, the ink that will be
 * printed on it, and the ratio the two measure.
 *
 * An unset or invalid value returns hex '' with a null ratio rather than a
 * default, because "the design's own colour stands" is a different statement
 * from "this colour", and the page says so in words rather than drawing a
 * swatch of a colour the operator never chose.
 */
function customizer_preview_colour($branding, $key) {
    $hex = '';
    if(is_array($branding) && isset($branding[$key]) && is_string($branding[$key])
        && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$key]) === 1) {
        $hex = strtoupper($branding[$key]);
    }
    if($hex === '') return array('hex' => '', 'ink' => '', 'ratio' => null);

    $ink = customizer_rail_ink($hex);
    return array('hex' => $hex, 'ink' => $ink, 'ratio' => round(customizer_contrast($ink, $hex), 2));
}

/**
 * Everything preview.php answers with, built without touching a database, an
 * HTTP request or a session — so it can be tested, and so the endpoint itself
 * holds no decision of its own.
 *
 * $stored_branding  the [branding] section as stored
 * $custom_logo      sys_ini.custom_logo as stored (the light-background upload)
 * $posted           the raw $_POST
 * $designs          which designs to describe, from customizer_installed_designs()
 * $labels           array('nav' => …, 'login' => …), already localised
 * $texts            the five already-localised preview strings, keyed
 *                   'no_logo', 'fallback_from_dark', 'fallback_from_light',
 *                   'no_favicon', 'favicon_url_wins'
 *
 * Returns:
 *   'previews' => array('used_logo' => html, 'used_logo_on_dark' => html,
 *                       'used_favicon' => html)   — the same renderers the page
 *                       and logo_upload.php use, so the three agree by
 *                       construction rather than by inspection
 *   'surfaces' => customizer_logo_surfaces_all()'s list
 *   'colours'  => 'accent' | 'rail' | 'rail_light' | 'login', each a
 *                 customizer_preview_colour() block; 'rail_light' additionally
 *                 carries 'inherited' => bool
 *
 * Nothing the operator typed as free text comes back. The panel name is rendered
 * into the preview by the page itself, from the input, as text — round-tripping
 * it through JSON and into innerHTML would add a path for no gain.
 */
function customizer_preview_payload($stored_branding, $custom_logo, $posted, $designs, $labels, $texts) {
    $branding = customizer_branding_with_posted_candidates($stored_branding, $posted);
    if(!is_array($texts)) $texts = array();
    $txt = function($key) use ($texts) {
        return (isset($texts[$key]) && is_string($texts[$key])) ? $texts[$key] : '';
    };

    $resolved = customizer_logo_resolve(array(
        'custom_logo'      => is_string($custom_logo) ? $custom_logo : '',
        'logo_on_dark'     => isset($branding['logo_on_dark']) ? $branding['logo_on_dark'] : '',
        'logo_url'         => isset($branding['logo_url']) ? $branding['logo_url'] : '',
        'logo_url_on_dark' => isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    ));

    $surfaces = customizer_logo_surfaces_all($designs, $branding, $labels);

    //* The light rail falls back to the rail: a design with a light scope paints
    //* the same colour in both modes until the operator sets this one, and the
    //* preview has to show that rather than an empty pane. 'inherited' is what
    //* lets the page say WHICH of the two it is showing.
    $rail_light_key = (isset($branding['rail_hex_light']) && is_string($branding['rail_hex_light'])
        && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding['rail_hex_light']) === 1) ? 'rail_hex_light' : 'rail_hex';
    $rail_light = customizer_preview_colour($branding, $rail_light_key);
    $rail_light['inherited'] = ($rail_light_key === 'rail_hex');

    return array(
        'previews' => array(
            'used_logo' => customizer_logo_preview_html($resolved['on_light'], 'on_light',
                $txt('no_logo'), $txt('fallback_from_dark'), $surfaces),
            'used_logo_on_dark' => customizer_logo_preview_html($resolved['on_dark'], 'on_dark',
                $txt('no_logo'), $txt('fallback_from_light'), $surfaces),
            'used_favicon' => customizer_favicon_preview_html(
                customizer_favicon_resolve(array(
                    'favicon'     => isset($branding['favicon']) ? $branding['favicon'] : '',
                    'favicon_url' => isset($branding['favicon_url']) ? $branding['favicon_url'] : '',
                )),
                $txt('no_favicon'), $txt('favicon_url_wins')),
        ),
        'surfaces' => $surfaces,
        'colours'  => array(
            'accent'     => customizer_preview_colour($branding, 'accent_hex'),
            'rail'       => customizer_preview_colour($branding, 'rail_hex'),
            'rail_light' => $rail_light,
            'login'      => customizer_preview_colour($branding, 'login_bg'),
        ),
    );
}
```

- [ ] **Step 7: Run the tests and confirm they pass**

Run:
```bash
php -l interface/web/customizer/lib/preview.inc.php
php tests/brand/run.php
```
Expected: no syntax errors; the run now prints `== rail-ink parity ==` followed by `ok   module chooses the same ink direction as clarity on all 17 rails`, and ends `brand suite passed`.

- [ ] **Step 8: Commit**

```bash
git add interface/web/customizer/lib/preview.inc.php \
        tests/brand/probe_module.php tests/brand/probe_clarity.php tests/brand/run.php
git commit -m "Add the preview's measured rail ink, candidate overlay and payload, cross-compared with clarity"
```

---

## Task 5: clarity reads `rail_hex_light`

**Files:**
- Modify: `themes/clarity/brand.php` (contract docblock, `$rail_light`, the light-scope rail block, the mode-aware nav mark)
- Modify: `interface/web/customizer/lib/preview.inc.php` (`customizer_logo_surfaces()`'s chrome table: clarity's nav gains a second backdrop)
- Modify: `tests/brand/probe_render.php` (new render cases)
- Modify: `tests/brand/probe_module.php` (surfaces characterisation)

**Interfaces:**
- Consumes: the stored key `rail_hex_light` (Tasks 1–2), `customizer_preview_colour()` (Task 4, not called here but in the same file).
- Produces: clarity emits `:root[data-nz-theme='light'] { --nz-rail…: … }` whenever `rail_hex_light` is set; `customizer_logo_surfaces('clarity', …)` returns **four** entries when `rail_hex_light` is set and differs from `rail_hex`, and the existing **three** otherwise.

**Background:** `--nz-rail` is declared exactly once, at `tokens.css:88`, and clarity's light scope (`tokens.css:215-302`) never redeclares it — the rail is navy in both colour modes by design. `brand.php`'s nav block says so in a comment and concludes "this surface is MODE-INVARIANT and one fixed rule is always right". `rail_hex_light` falsifies that, and only for the nav surface: when the two rails differ, the mark that reads on one may not read on the other. This task keeps that comment true by making it conditional. The ETag already covers the new key — it hashes `serialize($branding)`, the whole section.

- [ ] **Step 1: Write the failing tests**

(a) In `tests/brand/probe_render.php`, add four cases to the `$cases` array:

```php
    'light rail only'      => array('rail_hex_light' => '#E7EBF0'),
    'both rails'           => array('rail_hex' => '#01243D', 'rail_hex_light' => '#E7EBF0'),
    'both rails, same'     => array('rail_hex' => '#01243D', 'rail_hex_light' => '#01243D'),
    'light rail + accent'  => array('rail_hex_light' => '#FFFFFF', 'accent_hex' => '#0065AB'),
```

and add this block inside the existing `if (function_exists('brand_rail_vars')) { … }` section, after the white-rail assertion:

```php
    //* rail_hex_light is clarity's light-mode rail. Unset, the light scope
    //* inherits whatever :root said, which is the documented fallback and is why
    //* nothing is emitted for it.
    $none = render($path, array('rail_hex' => '#01243D'));
    t_ok('clarity: an unset light rail emits no light-scope rail block',
        strpos($none, "[data-nz-theme='light'] {\n  --nz-rail:") === false, $none);

    $both = render($path, array('rail_hex' => '#01243D', 'rail_hex_light' => '#E7EBF0'));
    t_ok('clarity: a light rail is emitted into the light scope',
        strpos($both, "[data-nz-theme='light'] {\n  --nz-rail: #E7EBF0;") !== false, $both);
    t_ok('clarity: the dark scope keeps the dark rail',
        strpos($both, "  --nz-rail: #01243D;\n") !== false, $both);
    if (preg_match_all('/--nz-rail-text:\s*([^;]+);/', $both, $m)) {
        //* Two rails of opposite brightness must produce two DIFFERENT inks, or
        //* the light scope is repainting the background and leaving the text
        //* behind — the exact bug the rail ink family exists to fix.
        t_ok('clarity: the two rails get different inks', count(array_unique($m[1])) === 2,
            implode(' | ', $m[1]));
        //* And the light one must read on the light rail.
        $light_ink = h_flatten(trim($m[1][count($m[1]) - 1]), '#E7EBF0');
        $c = h_contrast($light_ink, '#E7EBF0');
        t_ok(sprintf('clarity: the light-mode rail ink reads at %.2f:1', $c), $c >= 4.5, $light_ink);
    }

    //* The nav MARK follows, or the operator gets the white wordmark on the
    //* light rail in light mode — a logo that disappears in one colour mode
    //* with nothing on the page to explain it.
    $marks = render($path, array('rail_hex' => '#01243D', 'rail_hex_light' => '#FFFFFF',
        'logo_on_dark' => $png2), $png);
    t_ok('clarity: opposite rails give the nav mark a light-mode rule',
        strpos($marks, "[data-nz-theme='light'] #logo img") !== false, $marks);
    $same = render($path, array('rail_hex' => '#01243D', 'rail_hex_light' => '#0B1B2A',
        'logo_on_dark' => $png2), $png);
    t_ok('clarity: two dark rails need no light-mode mark rule',
        strpos($same, "[data-nz-theme='light'] #logo img") === false, $same);
```

(b) In `tests/brand/probe_module.php`, add to the clarity chrome characterisation block:

```php
//* rail_hex_light gives clarity's nav a SECOND backdrop, exactly as the login
//* slot has always had one per colour mode — and only when the two rails
//* actually differ, so a panel that has not set it sees no change at all.
$cl = customizer_logo_surfaces('clarity', array('rail_hex' => '#01243D', 'rail_hex_light' => '#FFFFFF'));
t_eq('clarity with two rails: four swatches', count($cl), 4);
t_eq('clarity nav, dark mode', $cl[0],
    array('surface' => 'nav', 'label' => '', 'variant' => 'on_dark', 'bg' => '#01243D'));
t_eq('clarity nav, light mode', $cl[1],
    array('surface' => 'nav', 'label' => '', 'variant' => 'on_light', 'bg' => '#FFFFFF'));

t_eq('two identical rails are one backdrop, not two',
    count(customizer_logo_surfaces('clarity', array('rail_hex' => '#01243D', 'rail_hex_light' => '#01243D'))), 3);
t_eq('a light rail alone still describes both modes',
    count(customizer_logo_surfaces('clarity', array('rail_hex_light' => '#FFFFFF'))), 4);
t_eq('an explicit choice is obeyed on both nav backdrops',
    customizer_logo_surfaces('clarity',
        array('rail_hex' => '#01243D', 'rail_hex_light' => '#FFFFFF', 'logo_variant_nav' => 'on_dark'))[1]['variant'],
    'on_dark');
t_eq('an invalid light rail changes nothing',
    count(customizer_logo_surfaces('clarity', array('rail_hex' => '#01243D', 'rail_hex_light' => 'nonsense'))), 3);
t_eq('classic is untouched by the new key',
    customizer_logo_surfaces('classic', array('rail_hex_light' => '#FFFFFF')),
    customizer_logo_surfaces('classic', array()));
```

- [ ] **Step 2: Run them and watch them fail**

Run: `php tests/brand/run.php`
Expected: FAIL — `clarity: a light rail is emitted into the light scope` and `clarity with two rails: four swatches` both fail; the run ends `BRAND SUITE FAILED`.

- [ ] **Step 3: Read and emit the key in clarity**

In `themes/clarity/brand.php`:

(a) add a line to the contract docblock, after the `rail_hex` line:

```
 *   config [branding] rail_hex_light -> the rail in LIGHT colour mode; unset,
 *                                       the light scope inherits rail_hex
```

(b) read it beside the others:

```php
$rail       = brand_hex($branding, 'rail_hex');
$rail_light = brand_hex($branding, 'rail_hex_light');
```

(c) after the `if ($accent !== '') { … } elseif ($rail !== '') { … }` colour block, add:

```php
/* ---- the light-mode rail ----------------------------------------------------
 * --nz-rail is declared exactly once (tokens.css:88) and the light scope
 * (tokens.css:215-302) never redeclares it: the shipped rail is navy in BOTH
 * colour modes by design. rail_hex_light is the operator's way of saying
 * otherwise, and it is emitted as its own light-scope block rather than folded
 * into the accent one above so that it works with or without an accent.
 *
 * Unset, nothing is emitted and the light scope inherits whatever :root said —
 * which IS the documented fallback to rail_hex, expressed as the cascade rather
 * than as a second copy of the same values. :root[data-nz-theme='light'] is
 * (0,2,0) against :root's (0,1,0), so this wins wherever it is emitted,
 * regardless of source order.
 *
 * The accent handed to brand_rail_vars() is the light scope's own accent role —
 * the base (blue-700) colour the light block above uses for its tints — not the
 * bright (blue-400) one the dark scope uses, so the rail indicator matches the
 * ramp emitted beside it in the same scope. */
if ($rail_light !== '') {
    $css .= ":root[data-nz-theme='light'] {\n"
          . brand_rail_vars($rail_light, ($accent !== '') ? brand_shade($accent, 34) : '')
          . "}\n";
}
```

(d) make the nav mark mode-aware. Replace the `$nav_pref = brand_logo_variant_pref(…);` call and its preceding comment's final sentence with:

```php
// NAV — #logo img on the rail and .nz-topbar-brand img on the mobile header
// chip. Both are painted var(--nz-rail) (app.css:121 and app.css:710), which
// rail_hex overrides through brand_rail_vars() above.
//
// This surface used to be MODE-INVARIANT — one fixed rule was always right,
// because --nz-rail was declared once and never redeclared in the light scope.
// rail_hex_light is exactly the value that falsifies it: with a dark rail in one
// mode and a light rail in the other, the mark that reads on one is the mark
// that disappears on the other. So the light mode is resolved separately, from
// the colour that will really be behind the mark THERE, and a rule is emitted
// only when the answer actually differs — a panel that has not set the key emits
// what it always did, byte for byte.
$nav_stored = isset($branding['logo_variant_nav']) ? $branding['logo_variant_nav'] : '';
$nav_pref   = brand_logo_variant_pref($nav_stored, $rail, 'on_dark');
$nav_light_pref = brand_logo_variant_pref($nav_stored,
    ($rail_light !== '') ? $rail_light : $rail, 'on_dark');
```

(e) inside the `if ($has_logo) { … }` branch, after `$light_var = brand_logo_var($light_src, $logo_vars);`, add the nav light variable, and after the `.nz-topbar-brand img` rule add the conditional light rule:

```php
    $nav_light_src = brand_logo_for_pref($nav_light_pref, $logo_on_light, $logo_on_dark);
    $nav_light_var = brand_logo_var($nav_light_src, $logo_vars);
```

```php
    //* Only when light mode genuinely wants a different artwork. brand_logo_var()
    //* reuses the property for an identical source, so this cannot add a second
    //* copy of a data URI to the sheet even when it is emitted.
    if ($nav_light_src !== $nav_src) {
        $css .= ":root[data-nz-theme='light'] #logo img, :root[data-nz-theme='light'] .nz-topbar-brand img { content: var({$nav_light_var}); }\n";
    }
```

Place the two `$nav_light_*` assignments beside the existing `$nav_var` / `$login_var` / `$light_var` block so all four resolve before any rule is written.

- [ ] **Step 4: Give clarity's nav its second backdrop in the preview model**

In `interface/web/customizer/lib/preview.inc.php`, in `customizer_logo_surfaces()`:

(a) extend the docblock's citation list with:

```
 *   clarity nav    --nz-rail, declared once at tokens.css:88 … and, since
 *                  rail_hex_light exists, redeclared in the light scope by
 *                  brand.php whenever the operator sets that key. So this
 *                  surface has ONE backdrop until the two rails differ and TWO
 *                  after, which is why it carries 'bg_key_light' rather than the
 *                  static 'modes' map the login slot uses: the login slot's two
 *                  backdrops are the DESIGN's own colours, and the nav slot's
 *                  are the OPERATOR's.
```

(b) change clarity's `nav` entry, adding one key:

```php
        'clarity' => array(
            'nav'   => array('default' => 'on_dark', 'bg_key' => 'rail_hex', 'bg' => '#01243D',
                             'bg_key_light' => 'rail_hex_light', 'modes' => array()),
            'login' => array('default' => '', 'bg_key' => 'login_bg', 'bg' => '',
                             'modes'   => array('on_dark' => '#17252B', 'on_light' => '#F1F6F8')),
        ),
```

(c) give every other entry the same key with an empty value so the loop can read it unguarded — add `'bg_key_light' => ''` to clarity's `login` entry and to both of classic's entries.

(d) in the loop, after `$bg_hex` is resolved and before the `modes` branch, add:

```php
        //* A slot whose backdrop the operator can set PER COLOUR MODE. It is
        //* resolved like the base one and only produces a second entry when it
        //* is set AND differs — an operator who has not touched it, or who set
        //* the same colour twice, sees exactly what they saw before.
        $bg_light = '';
        if($spec['bg_key_light'] !== '' && isset($branding[$spec['bg_key_light']])
            && is_string($branding[$spec['bg_key_light']])
            && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$spec['bg_key_light']]) === 1) {
            $bg_light = $branding[$spec['bg_key_light']];
        }
        //* With no base colour set the design's own is what light mode differs
        //* FROM, so the comparison is against the swatch that will be drawn.
        $bg_base = ($bg_hex !== '') ? $bg_hex : $spec['bg'];
        if($bg_light !== '' && strcasecmp($bg_light, $bg_base) !== 0) {
            //* Key ORDER matters and must match every other entry this function
            //* emits: probe_module compares whole entries with ===, which in PHP
            //* is order-sensitive for arrays.
            $out[] = array('surface' => $surface, 'label' => $label,
                           'variant' => customizer_logo_variant_for_surface(
                               customizer_logo_variant_stored($surface, $branding), $bg_base, $spec['default']),
                           'bg' => $bg_base);
            $out[] = array('surface' => $surface, 'label' => $label,
                           'variant' => customizer_logo_variant_for_surface(
                               customizer_logo_variant_stored($surface, $branding), $bg_light, $spec['default']),
                           'bg' => $bg_light);
            continue;
        }
```

- [ ] **Step 5: Run the tests and confirm they pass**

Run:
```bash
php -l themes/clarity/brand.php
php -l interface/web/customizer/lib/preview.inc.php
php tests/brand/run.php
```
Expected: no syntax errors; `brand suite passed`, including the new render and surfaces assertions, the unchanged 168-input resolver parity, and the 17-rail ink parity.

- [ ] **Step 6: Confirm nothing moved for a panel that has not set the key**

A panel that has never set `rail_hex_light` must get exactly the stylesheet it got before. Two assertions already added in Step 1 carry that guarantee — `clarity: an unset light rail emits no light-scope rail block` and `clarity: two dark rails need no light-mode mark rule` — and `probe_clarity.php`'s existing shipped-navy block pins the token values themselves. Confirm both ran rather than being skipped:

```bash
php tests/brand/probe_render.php clarity | grep -c 'unset light rail emits no light-scope rail block'
php tests/brand/probe_clarity.php | grep -c "navy rail: text keeps tokens.css's exact value"
php tests/brand/run.php | grep -E '^(FAIL|-- )' | wc -l
```
Expected: `1`, `1`, and `0`.

- [ ] **Step 7: Commit**

```bash
git add themes/clarity/brand.php interface/web/customizer/lib/preview.inc.php \
        tests/brand/probe_render.php tests/brand/probe_module.php
git commit -m "clarity: paint the light-mode rail from rail_hex_light and follow it with the nav mark"
```

---

## Task 6: classic documents the no-op, matches the shared hex anchor, and CI holds every design to the key

**Files:**
- Modify: `themes/classic/brand.php` (contract docblock + the read; and `brand_hex()`'s missing `/D` at `themes/classic/brand.php:885`)
- Modify: `.github/workflows/ci.yml` (the contract key list)
- Modify: `CONTRIBUTING.md` (the key list and its count)
- Modify: `tests/brand/probe_classic.php` (the key must appear in code, not only in prose; `brand_hex()` driven)
- Modify: `tests/brand/probe_clarity.php` (the same `brand_hex()` assertions, so the pair cannot drift again in either direction)

**Interfaces:**
- Consumes: `rail_hex_light` as a stored key.
- Produces: `themes/*/brand.php` all satisfy a twelve-key contract list; the CI step's `for key in …` list gains `rail_hex_light`.

**Background:** CI's "Brand-token contract parity" step greps each `themes/*/brand.php` for every key on a hard-coded list. `probe_classic.php` tokenises classic's source and asserts the two variant keys appear in **code** rather than in a comment, because classic once composed its key name (`'logo_variant_' . $surface`) and the grep matched only prose while the design silently ignored the operator. The same trap applies to a key a design does not act on: a comment satisfies the grep, so the probe is what makes the read real.

**The second change in this task, and why it belongs here.** `brand_hex()` is the gate every colour on classic goes through — `accent_hex`, `rail_hex`, `login_bg`, and now the `rail_hex_light` read this task adds — and its pattern at `themes/classic/brand.php:885` is the **one copy in the project without `/D`**:

```php
if (isset($branding[$key]) && preg_match('/^#[0-9A-Fa-f]{6}$/', $branding[$key])) {
```

clarity's copy (`themes/clarity/brand.php:1040`) has always carried it. Without `/D`, PCRE's `$` also matches immediately before a final newline, so a stored `"#FFFFFF\n"` validates here and the raw LF is emitted into a `text/css` response — corrupting the declaration it lands in, on a **pre-auth** endpoint — while clarity ignores the same stored value entirely. Two readers disagreeing about one stored value is exactly the class of drift the whole `tests/brand/` suite exists to catch, and this task is the one that adds a fourth caller to that function. Verified before this plan was written: `grep -n -F '#[0-9A-Fa-f]{6}$/' themes/*/brand.php interface/web/customizer/lib/preview.inc.php | grep -v -F '$/D'` returns exactly one line, `themes/classic/brand.php:885`.

- [ ] **Step 1: Write the tests — two that must fail, one guard that must not**

In `tests/brand/probe_classic.php`, extend the key list in the tokenised block:

```php
foreach (array('logo_variant_nav', 'logo_variant_login', 'rail_hex_light') as $key) {
    t_ok("classic reads '$key' in code, not only in a comment", strpos($code, $key) !== false);
}
```

and add, immediately after it:

```php
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
```

Then add this block to `tests/brand/probe_classic.php`, immediately above its `/* ---- classic's own contrast helpers still hold ---- */` section:

```php
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
```

and this to `tests/brand/probe_clarity.php`, immediately above its `/* ---- the light-mode login filter ---- */` section. It passes from the moment it is written — clarity's copy already carries `/D` — and it is worth having anyway: without it, only one of the two copies is held to the anchor, which is how they came apart in the first place.

```php
/* ---- the same hex gate, and the same anchor -----------------------------
 * The twin of the block in probe_classic.php. classic's copy of brand_hex() was
 * missing /D and let "#FFFFFF\n" through into a text/css response; this one has
 * always carried it. Both are asserted from now on, so the pair cannot drift
 * again in either direction.
 */
t_ok('brand_hex() exists', function_exists('brand_hex'));
if (function_exists('brand_hex')) {
    t_eq('a valid hex is returned as stored',
        brand_hex(array('rail_hex' => '#01243D'), 'rail_hex'), '#01243D');
    t_eq('an absent key is empty', brand_hex(array(), 'rail_hex'), '');
    t_eq('a missing hash is refused', brand_hex(array('rail_hex' => '01243D'), 'rail_hex'), '');
    t_eq('a trailing space is refused', brand_hex(array('rail_hex' => '#01243D '), 'rail_hex'), '');
    t_eq('a trailing newline is refused', brand_hex(array('rail_hex' => "#01243D\n"), 'rail_hex'), '');
}
```

- [ ] **Step 2: Run them and watch the right ones fail**

Run:
```bash
php tests/brand/probe_classic.php
php tests/brand/probe_clarity.php
```
Expected: `probe_classic.php` FAILS on exactly three assertions and no others —

```
FAIL classic reads 'rail_hex_light' in code, not only in a comment
FAIL the light-rail read is assigned and discarded, and used nowhere else  -- got 0, want 2
FAIL a TRAILING newline is refused  -- got '#01243D\n', want ''
```

— and exits 1. `probe_clarity.php` PASSES: its copy already carries `/D`, and an assertion that never fails first is still the guard that stops it being removed later.

- [ ] **Step 3: Read the key in classic**

In `themes/classic/brand.php`:

(a) add to the contract docblock, after the `rail_hex` line:

```
 *   config [branding] rail_hex_light -> read, and deliberately a NO-OP here:
 *                                       classic renders one colour mode, so
 *                                       there is no light scope to paint. See
 *                                       the read below.
```

(b) add the read beside the other three:

```php
$login_bg = brand_hex($branding, 'login_bg');

//* rail_hex_light is read here and used for nothing, on purpose.
//*
//* It is on CI's brand-token contract list, which every design under themes/
//* must satisfy — and the check greps this file, so a comment alone would
//* satisfy it while the design said nothing about the key at all. Reading it in
//* CODE is the honest answer: this design has ONE colour mode, its rail is
//* #main-navigation and .pushy, and there is no light scope for a second rail
//* colour to live in. Acting on the value instead would paint classic's only
//* rail with a colour the operator chose for a mode they do not have here,
//* which is worse than ignoring it.
//*
//* tests/brand/probe_classic.php holds both halves: that the key appears in code
//* rather than in prose, and that the value never reaches a rule.
$rail_light_noop = brand_hex($branding, 'rail_hex_light');
unset($rail_light_noop);
```

- [ ] **Step 4: Give `brand_hex()` the `/D` every other copy carries**

In `themes/classic/brand.php`, replace the function at `:883-889` with:

```php
/**
 * A validated #rrggbb from the contract, or ''.
 *
 * /D is not decoration and is not optional. tform_base appends only "s" to a
 * validator, and PCRE's `$` matches immediately before a final newline as well
 * as at the true end of the subject — so without /D a stored "#FFFFFF\n" passes
 * here and the raw LF is emitted into a text/css response, corrupting the
 * declaration it lands in, on an endpoint that answers with no session.
 * themes/clarity/brand.php's copy of this function has always carried it, so
 * this one was the only place in the project where two readers disagreed about
 * the same stored value. Every anchored pattern in this project carries /D;
 * tests/brand/probe_classic.php and probe_clarity.php now hold both copies to it.
 */
function brand_hex($branding, $key)
{
    if (isset($branding[$key]) && preg_match('/^#[0-9A-Fa-f]{6}$/D', $branding[$key])) {
        return $branding[$key];
    }
    return '';
}
```

Nothing else changes: every caller passes a stored config value, every legitimate value still validates, and the only inputs whose answer moves are the ones that were being emitted raw into CSS.

- [ ] **Step 5: Add the key to CI's contract list**

In `.github/workflows/ci.yml`, in the "Brand-token contract parity" step, extend the `for key in …` line:

```yaml
            for key in accent_hex rail_hex rail_hex_light login_bg logo_url logo_url_on_dark logo_on_dark logo_variant_nav logo_variant_login show_version show_design_picker company_name; do
```

and add a paragraph to that step's comment block, above the `for key` line:

```yaml
            # rail_hex_light is the light-mode rail. It is on this list even
            # though only a design WITH a light colour mode can act on it: a
            # design without one reads it and documents the no-op in code, which
            # is a smaller cost than a contract list that means "the keys some
            # designs happen to use". The grep proves presence, never behaviour —
            # tests/brand/probe_classic.php is what proves classic's read is code
            # and not a comment, and probe_render.php is what proves clarity's is
            # a rule.
```

- [ ] **Step 6: Update CONTRIBUTING.md**

In `CONTRIBUTING.md`, in the "Brand-token contract parity" paragraph, change `each of the eleven keys` to `each of the twelve keys` and add `rail_hex_light` to the parenthesised list after `rail_hex`. Add one sentence after the `logo_variant_*` explanation:

```markdown
`rail_hex_light` is the light-mode rail colour, and it is on the list even
though only a design that *has* a light colour mode can paint anything with it —
clarity does; classic reads it and documents the no-op in code, which
`tests/brand/probe_classic.php` checks is a read and not a comment. A list that
excused a design from a key it happens not to use would stop being a contract.
```

- [ ] **Step 7: Verify**

Run:
```bash
php -l themes/classic/brand.php
php tests/brand/run.php
grep -n -F '#[0-9A-Fa-f]{6}$/' themes/*/brand.php interface/web/customizer/lib/preview.inc.php | grep -v -F '$/D'
for reader in themes/*/brand.php; do
  for key in accent_hex rail_hex rail_hex_light login_bg logo_url logo_url_on_dark logo_on_dark logo_variant_nav logo_variant_login show_version show_design_picker company_name; do
    grep -q "$key" "$reader" || echo "MISSING $key in $reader"
  done
done
```
Expected: no syntax errors; `brand suite passed`; the `/D` grep prints **nothing** (before this task it printed exactly one line, `themes/classic/brand.php:885`, and a `grep` with no match exits 1, so run it on its own rather than under `set -e`); the key loop — the CI step's own logic, run by hand — prints nothing.

- [ ] **Step 8: Commit**

```bash
git add themes/classic/brand.php .github/workflows/ci.yml CONTRIBUTING.md \
        tests/brand/probe_classic.php tests/brand/probe_clarity.php
git commit -m "Put rail_hex_light on the contract list; classic reads it, documents the no-op, and anchors its hex pattern like every other copy"
```

---

## Task 7: `preview.php` — the read-only JSON endpoint

**Files:**
- Create: `interface/web/customizer/preview.php`
- Create: `tests/brand/probe_preview.php`
- Modify: `tests/brand/run.php` (register the probe)
- Modify: `SECURITY.md` (the module endpoint inventory, the trust-boundary list, the write-surface section, and the stale intro count)
- Modify: `CONTRIBUTING.md` (the module file table)

**Interfaces:**
- Consumes: `customizer_preview_payload()`, `customizer_installed_designs()` (Task 4 and existing).
- Produces: `POST customizer/preview.php` with `X-Requested-With: XMLHttpRequest` → `application/json`, `Cache-Control: no-store`, body = `customizer_preview_payload()`'s array. Task 8b's JS is the only caller.

**Why this endpoint exists:** the logo-variant resolver lives in exactly three PHP copies that CI proves agree. A fourth copy in JavaScript would break that guarantee — and the preview exists precisely to stop the page promising a mark the panel will not render. So anything depending on variant resolution is answered by PHP, over one debounced request.

**Design decisions that must not be quietly changed:**

- **The same three admin checks, in the same order, before anything else.** `check_module_permissions('customizer')`, then `check_security_permissions('admin_allow_system_config')`, then an `is_admin()` guard that dies. This is the rule `CONTRIBUTING.md` states for any new endpoint.
- **No CSRF token is minted, and none is checked.** Minting one writes the session — a whole-row `REPLACE` in a store with no locking — and this endpoint fires on a debounce while the operator types, so minting would *cause* the race the uploader was built to avoid. Nothing is written, so there is nothing for a forged request to change; and the response is unreadable cross-origin. The gate is the same `X-Requested-With: XMLHttpRequest` header `logo_upload.php`'s GET branch uses, which cannot be attached cross-origin without a CORS preflight.
- **POST, not GET.** The spec's draft put candidate values in the query string; they include the panel's colours and reference paths, and a query string lands in access logs and in `Referer`. A POST body does not, and the same-origin XHR is identical work either way.
- **Read-only, and provably so.** The probe scans the token stream for every write verb.
- **`getconf::get_global_config()` is fine here** and must not be replaced with the raw-column read `onUpdateSave()` uses: its `stripslashes()` has no missing counterpart on a pure READ path. The comment in `customizer_edit.php::render_image_previews()` says exactly this.

- [ ] **Step 1: Write the failing test**

Create `tests/brand/probe_preview.php`:

```php
<?php
/**
 * interface/web/customizer/preview.php — the endpoint's SHAPE.
 *
 * The endpoint's decisions all live in lib/preview.inc.php and are tested by
 * probe_module.php against real inputs. What cannot be tested that way is what
 * makes this file safe to add: that it authenticates the same way its three
 * siblings do, before anything else; that it writes nothing; and that it answers
 * as JSON that is never cached. Those are properties of the SOURCE, so they are
 * asserted against the token stream — tokenised rather than regexed because this
 * module's files carry "/*" inside string literals, which is how an earlier
 * comment-stripping regex in probe_classic.php swallowed the very code it was
 * looking for.
 *
 * Run through tests/brand/run.php. Nothing here executes the endpoint: it
 * requires ISPConfig's app bootstrap, a database and a session, none of which
 * exist in CI or on a developer's machine.
 */

require_once __DIR__ . '/harness.php';

$path = __DIR__ . '/../../interface/web/customizer/preview.php';
$src  = @file_get_contents($path);
t_ok('preview.php exists and is readable', $src !== false);
if ($src === false) t_done();

//* Code only: comments must never satisfy any assertion below.
$code = '';
$order = array();
foreach (token_get_all($src) as $tok) {
    if (is_array($tok)) {
        if ($tok[0] === T_COMMENT || $tok[0] === T_DOC_COMMENT) continue;
        $code .= $tok[1];
        continue;
    }
    $code .= $tok;
}

/* ---- the three checks, in the documented order --------------------------- */
$checks = array(
    "check_module_permissions('customizer')",
    "check_security_permissions('admin_allow_system_config')",
    'is_admin()',
);
$at = -1;
foreach ($checks as $needle) {
    $pos = strpos($code, $needle);
    if (!t_ok("preview.php performs $needle", $pos !== false)) continue;
    t_ok("...after the check before it", $pos > $at, "at $pos, previous at $at");
    $at = $pos;
}
t_ok('the is_admin() guard dies rather than continuing',
    preg_match('/if\s*\(\s*!\s*\$app->auth->is_admin\(\)\s*\)\s*die\s*\(/', $code) === 1);

//* Nothing may run before the checks — not a query, not a read of $_POST.
$first_check = strpos($code, "check_module_permissions('customizer')");
$before = substr($code, 0, $first_check);
foreach (array('$_POST', '$_GET', 'queryOneRecord', 'get_global_config', 'echo') as $early) {
    t_ok("nothing reads or emits before the first check ($early)",
        strpos($before, $early) === false, $early);
}

/* ---- read-only, asserted rather than promised ---------------------------- */
foreach (array('datalogUpdate', 'datalogSave', 'datalogInsert', 'datalogDelete',
               'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'ALTER', 'CREATE',
               'file_put_contents', 'fwrite', 'unlink', 'mkdir', 'rename',
               'csrf_token_get', '->conf(') as $verb) {
    t_ok("preview.php contains no '$verb'", stripos($code, $verb) === false);
}
t_ok('the only SQL is a SELECT',
    preg_match_all('/\bSELECT\b/i', $code) >= 1 && stripos($code, 'sys_ini') !== false);

/* ---- the gates ----------------------------------------------------------- */
t_ok('only POST is answered', strpos($code, "REQUEST_METHOD") !== false
    && strpos($code, "'POST'") !== false);
t_ok('the same-origin header gate the uploader uses is applied',
    strpos($code, 'HTTP_X_REQUESTED_WITH') !== false && strpos($code, 'XMLHttpRequest') !== false);

/* ---- the answer ---------------------------------------------------------- */
t_ok('the response is declared JSON', strpos($code, 'application/json') !== false);
t_ok('the response is never cached', strpos($code, 'no-store') !== false);
t_ok('the payload is built by the shared function, not here',
    strpos($code, 'customizer_preview_payload(') !== false);
t_ok('json_encode escapes for an HTML-hostile reader',
    strpos($code, 'JSON_HEX_TAG') !== false && strpos($code, 'JSON_HEX_AMP') !== false
    && strpos($code, 'JSON_HEX_APOS') !== false && strpos($code, 'JSON_HEX_QUOT') !== false);

/* ---- no decision of its own ---------------------------------------------
 * Every rule this endpoint applies has to be one of the shared resolvers, or
 * the three-copies-agree guarantee stops covering what the page shows.
 */
t_ok('the endpoint declares no function of its own',
    preg_match('/\bfunction\s+[a-z_]/i', $code) !== 1, 'a helper here would be a fourth copy');
t_ok('it includes the shared model', strpos($code, "lib/preview.inc.php") !== false);

t_done();
```

- [ ] **Step 2: Register it and run it, and watch it fail**

In `tests/brand/run.php`, extend the `$checks` array added in Task 1:

```php
$checks = array(
    'tform'   => __DIR__ . '/probe_tform.php',
    'preview' => __DIR__ . '/probe_preview.php',
);
```

Run: `php tests/brand/probe_preview.php`
Expected: FAIL — `preview.php exists and is readable`; exit status 1.

- [ ] **Step 3: Write the endpoint**

Create `interface/web/customizer/preview.php`:

```php
<?php
/**
 * ispconfig-customizer — standalone white-label branding for ISPConfig.
 * https://github.com/wadejbeckett/ispconfig-theme-customizer
 * Copyright (c) 2026 Wade Beckett. MIT License — see LICENSE.
 *
 * Built for ISPConfig (ispconfig.org, BSD-3-Clause). Not affiliated with or
 * endorsed by the ISPConfig project.
 *
 * The Branding page's live preview (admin only, READ ONLY).
 *
 * WHY THIS EXISTS. The Branding page previews which logo VARIANT each surface of
 * each installed design will use. That decision lives in three PHP copies —
 * themes/clarity/brand.php, themes/classic/brand.php and lib/preview.inc.php —
 * which tests/brand/run.php proves agree on every input, because the theme
 * endpoints are pre-auth and cannot include the module's code. A JavaScript
 * fourth copy would be outside that guarantee, and a preview that disagrees with
 * the panel is the exact failure the preview exists to prevent. So the page
 * paints colours and rail ink itself, and asks THIS endpoint for anything that
 * depends on variant resolution.
 *
 * WHAT IT DOES. Takes the form's current, unsaved values as POST, overlays the
 * ones that pass their own validation onto the stored [branding], and answers
 * with the rendered preview rows, the surfaces list and the colour blocks —
 * customizer_preview_payload(), which holds every decision and is tested without
 * a database.
 *
 * WHAT IT MUST NEVER DO.
 *   - Write. Not the config blob, not sys_config, not a file. It is called on a
 *     debounce while the operator types; a write path here would be a write per
 *     keystroke, and there is nothing on this page that needs one.
 *   - Mint or check a CSRF token. Minting WRITES the session, and ISPConfig's
 *     session store has no locking (read = SELECT, write = whole-row REPLACE,
 *     last writer wins) — so minting per keystroke would manufacture the exact
 *     race the uploader's click-time mint exists to shrink. There is no state to
 *     protect: nothing is written, and the response is unreadable cross-origin.
 *     The gate is the same X-Requested-With header logo_upload.php's GET branch
 *     uses, which cannot be attached cross-origin without a CORS preflight.
 *   - Decide anything. No function is declared here on purpose; every rule
 *     applied is one of the shared resolvers.
 *
 * POST rather than GET, though it reads nothing: the candidate values are the
 * operator's colours and paths, and a query string lands in access logs and in
 * Referer. The same-origin XHR costs the same either way.
 */

require_once '../../lib/config.inc.php';
require_once '../../lib/app.inc.php';
require_once __DIR__ . '/lib/preview.inc.php';

//* admin-only — the same three checks, in the same order, as customizer_edit.php,
//* logo_upload.php and logo_delete.php. See SECURITY.md for why all three are
//* needed rather than any one of them.
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');

//* Same-origin JS only, and only as a POST. Both gates are cheap and neither is
//* the security boundary — the three checks above are.
if($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    die('Bad request.');
}
if(!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || $_SERVER['HTTP_X_REQUESTED_WITH'] !== 'XMLHttpRequest') {
    die('Bad request.');
}

$app->uses('getconf');

//* The two STORED halves the POST cannot carry: core's own logo column, and the
//* [branding] section (which holds the two uploaded images and the favicon).
//* getconf is the right reader HERE and would be wrong in a save path: its
//* stripslashes() has no counterpart on the way back, so a read-modify-write
//* through it eats a backslash level from every value in the file. This request
//* writes nothing, so that asymmetry cannot bite.
$sys_ini  = $app->db->queryOneRecord("SELECT custom_logo FROM sys_ini WHERE sysini_id = 1");
$branding = $app->getconf->get_global_config('branding');
if(!is_array($branding)) $branding = array();

//* $app->lng() reads this module's own wordbook (lib/lang/<lang>.lng), which
//* app.inc.php auto-loads for a request inside the module directory — the same
//* mechanism logo_upload.php relies on for these very keys. They are passed in
//* already localised because lib/preview.inc.php holds no wordbook of its own.
$payload = customizer_preview_payload(
    $branding,
    (is_array($sys_ini) && isset($sys_ini['custom_logo'])) ? $sys_ini['custom_logo'] : '',
    $_POST,
    customizer_installed_designs(isset($_SESSION['s']['theme']) ? $_SESSION['s']['theme'] : ''),
    array('nav' => $app->lng('surface_nav_txt'), 'login' => $app->lng('surface_login_txt')),
    array(
        'no_logo'             => $app->lng('no_logo_set_txt'),
        'fallback_from_dark'  => $app->lng('logo_fallback_from_dark_txt'),
        'fallback_from_light' => $app->lng('logo_fallback_from_light_txt'),
        'no_favicon'          => $app->lng('no_favicon_set_txt'),
        'favicon_url_wins'    => $app->lng('favicon_url_wins_txt'),
    )
);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
//* The four HEX flags for the same reason themes/*/title.php uses them: the
//* result is handed to a page, and a value that cannot carry <, &, ' or " cannot
//* change the meaning of whatever it lands in.
echo json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
```

- [ ] **Step 4: Run the probe and confirm it passes**

Run:
```bash
php -l interface/web/customizer/preview.php
php tests/brand/probe_preview.php
php tests/brand/run.php
```
Expected: no syntax errors; the preview probe ends `# N passed, 0 failed`; `brand suite passed`.

Note for the implementer: if the `UPDATE`/`DELETE` string assertions trip on a word inside a comment you added, that is the probe working as designed — it scans code only, so a trip means the verb is in code. Do not weaken the probe; remove the verb.

- [ ] **Step 5: Record the endpoint in SECURITY.md**

Four edits, all in `SECURITY.md`:

1. The intro list (around line 8). It currently says the designs have "two endpoints that are reachable **without a session** (`brand.php`, `title.php`), so four in total", which contradicts the "The pre-auth surface" section further down — that section correctly counts **six**, because `favicon.php` is a third pre-auth endpoint per design. Correct the intro:

```markdown
- `themes/clarity/` and `themes/classic/` — the two designs it ships, each with
  three endpoints that are reachable **without a session** (`brand.php`,
  `title.php`, `favicon.php`), so six in total;
```

2. The trust-boundary heading paragraph (around line 43):

```markdown
Every endpoint under `interface/web/customizer/` (`customizer_edit.php`,
`logo_upload.php`, `logo_delete.php`, `preview.php`) opens with the same three
checks, in this order:
```

3. In "Other controls", after the `logo_delete.php` bullet, add:

```markdown
- **`preview.php` mints no token, and that is deliberate.** It is the Branding
  page's live preview: admin-only, the same three checks in the same order, and
  **read-only** — one `SELECT` against `sys_ini` row 1, no write of any kind, no
  filesystem access, and no function of its own (every rule it applies is one of
  the shared resolvers in `lib/preview.inc.php`, so the preview cannot drift from
  what the panel renders). It answers `application/json` with `Cache-Control:
  no-store`, gated on `POST` and on the same `X-Requested-With: XMLHttpRequest`
  header the uploader's token mint uses. A CSRF token would be actively harmful
  here: minting one **writes** the session, ISPConfig's session store does not
  lock, and this endpoint fires on a debounce while an admin types — so minting
  per keystroke would manufacture the race the click-time mint exists to shrink.
  There is nothing for a forged request to change, and the response is
  unreadable cross-origin. `tests/brand/probe_preview.php` asserts all of that
  against the file's token stream on every push: the three checks in order with
  nothing before them, no write verb anywhere, the two gates, the JSON and
  no-store headers, and no locally declared function.
```

4. In "No core file is modified, and this is the whole write surface", after the
table, add one sentence to the paragraph that introduces it:

```markdown
`preview.php` appears nowhere in this table, which is the point of it: it is the
fourth endpoint under `interface/web/customizer/` and the only one that writes
nothing at all.
```

- [ ] **Step 6: Record it in CONTRIBUTING.md**

In the "The Branding page — `interface/web/customizer/`" file table, add a row after the `logo_upload.php, logo_delete.php` row:

```markdown
| `interface/web/customizer/preview.php` | The Branding page's live preview: admin-only and **read-only**. Takes the form's unsaved values as POST and returns JSON built by `lib/preview.inc.php` — the resolved logo variants per surface, the three preview rows and the measured rail ink. It exists so the logo-variant resolver stays in PHP: a JavaScript copy would sit outside the three-copies-agree guarantee CI enforces. It declares no function of its own, and `tests/brand/probe_preview.php` proves it writes nothing. |
```

- [ ] **Step 7: Verify**

Run:
```bash
php tests/brand/run.php
grep -n 'preview.php' SECURITY.md CONTRIBUTING.md
grep -c 'so six in total' SECURITY.md
```
Expected: `brand suite passed`; the greps show the new rows; the count is 1.

- [ ] **Step 8: Commit**

```bash
git add interface/web/customizer/preview.php tests/brand/probe_preview.php \
        tests/brand/run.php SECURITY.md CONTRIBUTING.md
git commit -m "Add the read-only admin-only preview endpoint, and document it"
```

---

## Task 8a: The two-column page — wordbook, markup and module CSS

**Files:**
- Modify: `interface/web/customizer/lib/lang/{de,en,es,fr,it,nl,pt}_customizer.lng` (12 new keys, 3 retired, 2 changed values — all seven files identically)
- Modify: `interface/web/customizer/templates/customizer_edit.htm` (everything except the `<script>` block, which Task 8b extends)

**Interfaces:**
- Consumes: the `rail_hex_light` field (Task 1).
- Produces: the DOM contract Task 8b's JS binds to — `#nz-brandpage`, `.nz-brandgrid`, `.nz-brandpreview`, `#nz-rail-ratio`, `#nz-rail-light-ratio`, `.nz-prev-rail`, `.nz-prev-rail-light`, `.nz-prev-login`, `.nz-prev-accent`, `.nz-prev-accent-rule`, `.nz-prev-name`, `#rail_hex_light` / `#rail_hex_light_pick` — **plus every id the existing JS already binds**, unchanged: `nz-msg-slot`, `used_logo`, `used_logo_on_dark`, `used_favicon`, `file`, `file_on_dark`, `file_favicon`, `nz-logo-upload`, `nz-logo-upload-on-dark`, `nz-favicon-upload`, `nz-logo-remove`, `nz-logo-remove-on-dark`, `nz-favicon-remove`, `accent_hex`/`accent_hex_pick`, `rail_hex`/`rail_hex_pick`, `login_bg`/`login_bg_pick`.

**Rules for this task:**

- Classes are `nz-`, never `pz-`. Colours are `var(--pz-…, var(--nz-…, <stock fallback>))` — three links, always. The existing stock fallback for a neutral hairline in this file is `rgba(128,128,128,0.25)`; keep using it.
- Every rule is prefixed `#pageContent` (or `#nz-brandpage`) so it beats a design's own rules without `!important`, which is what the current file already does.
- **Bootstrap's grid is not changed.** Each field keeps the stock `.form-group > label.col-sm-3.control-label + div.col-sm-9` shape, so the page still looks like every other ISPConfig form under every design. The redesign is the *outer* grid plus the uploader blocks; the only Bootstrap override is scoped inside `.nz-upload-fields`, where the label sits above its control because the block is already narrow.
- The collapse is measured on the **content column**, not the viewport: a rail takes ~232px, so a 1024px window leaves this page far less than 1024px to lay out in. A container query on our own wrapper does that; a viewport media query is the `@supports` fallback. Do **not** put `container-type` on `#pageContent` — that is core's element.
- No `outline: none` anywhere, and no focus styling at all: focus is the design's, and every design ships one.
- The preview column is **not** an ARIA live region. It changes on every keystroke; announcing that is worse than silence. It carries `aria-label` and the ratio readouts are plain text a screen-reader user reaches on demand.

- [ ] **Step 1: Add the twelve new keys, retire three, and change two values — English first**

In `interface/web/customizer/lib/lang/en_customizer.lng`: delete the lines assigning `logo_head_txt`, `brand_head_txt` and `credits_head_txt` (the four fieldsets they titled are gone — `favicon_head_txt` survives as the Tab-icon block heading and `login_head_txt` as its own legend), change two values, and add twelve keys:

```php
$wb['settings_saved_txt'] = 'Changes saved. The panel’s own colours appear after a full page refresh (F5).';
$wb['btn_save_txt'] = 'Save changes';

$wb['identity_head_txt'] = 'Identity';
$wb['colour_head_txt'] = 'Colour';
$wb['visibility_head_txt'] = 'Panel visibility';
$wb['placement_head_txt'] = 'Placement';
$wb['rail_hex_light_txt'] = 'Sidebar colour (light mode)';
$wb['rail_hex_light_hint_txt'] = 'Used by designs that offer a light colour mode; leave it blank and the sidebar colour above is used in both modes. A design with no light mode reads this value and does nothing with it.';
$wb['rail_contrast_txt'] = 'Sidebar text contrast:';
$wb['preview_head_txt'] = 'Preview';
$wb['preview_hint_txt'] = 'Colours and the panel name update as you type. Which logo each surface uses is worked out by the server, so it matches what the panel will really render.';
$wb['preview_nav_txt'] = 'Navigation';
$wb['preview_login_txt'] = 'Login screen';
$wb['preview_tab_txt'] = 'Tab icon';
$wb['preview_failed_txt'] = 'The preview could not be refreshed. What you see is the last version the server confirmed.';
```

- [ ] **Step 2: Make the same change in the other six**

`de_customizer.lng`:

```php
$wb['settings_saved_txt'] = 'Änderungen gespeichert. Die Farben des Panels selbst erscheinen nach einem vollständigen Neuladen der Seite (F5).';
$wb['btn_save_txt'] = 'Änderungen speichern';

$wb['identity_head_txt'] = 'Identität';
$wb['colour_head_txt'] = 'Farben';
$wb['visibility_head_txt'] = 'Sichtbarkeit im Panel';
$wb['placement_head_txt'] = 'Platzierung';
$wb['rail_hex_light_txt'] = 'Farbe der Seitenleiste (heller Modus)';
$wb['rail_hex_light_hint_txt'] = 'Wird von Designs mit hellem Farbmodus verwendet; bleibt das Feld leer, gilt die Farbe der Seitenleiste oben in beiden Modi. Ein Design ohne hellen Modus liest den Wert und tut nichts damit.';
$wb['rail_contrast_txt'] = 'Kontrast des Seitenleistentexts:';
$wb['preview_head_txt'] = 'Vorschau';
$wb['preview_hint_txt'] = 'Farben und der Panel-Name werden während der Eingabe aktualisiert. Welches Logo eine Oberfläche verwendet, ermittelt der Server, damit es dem entspricht, was das Panel tatsächlich anzeigt.';
$wb['preview_nav_txt'] = 'Navigation';
$wb['preview_login_txt'] = 'Anmeldebildschirm';
$wb['preview_tab_txt'] = 'Tab-Symbol';
$wb['preview_failed_txt'] = 'Die Vorschau konnte nicht aktualisiert werden. Sie sehen den zuletzt vom Server bestätigten Stand.';
```

`es_customizer.lng`:

```php
$wb['settings_saved_txt'] = 'Cambios guardados. Los colores del propio panel aparecen tras recargar completamente la página (F5).';
$wb['btn_save_txt'] = 'Guardar cambios';

$wb['identity_head_txt'] = 'Identidad';
$wb['colour_head_txt'] = 'Color';
$wb['visibility_head_txt'] = 'Visibilidad del panel';
$wb['placement_head_txt'] = 'Ubicación';
$wb['rail_hex_light_txt'] = 'Color de la barra lateral (modo claro)';
$wb['rail_hex_light_hint_txt'] = 'Lo usan los diseños que ofrecen modo claro; si lo deja en blanco, el color de la barra lateral de arriba se usa en ambos modos. Un diseño sin modo claro lee el valor y no hace nada con él.';
$wb['rail_contrast_txt'] = 'Contraste del texto de la barra lateral:';
$wb['preview_head_txt'] = 'Vista previa';
$wb['preview_hint_txt'] = 'Los colores y el nombre del panel se actualizan mientras escribe. Qué logotipo usa cada superficie lo resuelve el servidor, así que coincide con lo que el panel mostrará realmente.';
$wb['preview_nav_txt'] = 'Navegación';
$wb['preview_login_txt'] = 'Pantalla de inicio de sesión';
$wb['preview_tab_txt'] = 'Icono de pestaña';
$wb['preview_failed_txt'] = 'No se pudo actualizar la vista previa. Lo que ve es la última versión confirmada por el servidor.';
```

`fr_customizer.lng`:

```php
$wb['settings_saved_txt'] = 'Modifications enregistrées. Les couleurs du panneau lui-même apparaissent après un rechargement complet de la page (F5).';
$wb['btn_save_txt'] = 'Enregistrer les modifications';

$wb['identity_head_txt'] = 'Identité';
$wb['colour_head_txt'] = 'Couleurs';
$wb['visibility_head_txt'] = 'Visibilité dans le panneau';
$wb['placement_head_txt'] = 'Emplacement';
$wb['rail_hex_light_txt'] = 'Couleur de la barre latérale (mode clair)';
$wb['rail_hex_light_hint_txt'] = 'Utilisée par les designs qui proposent un mode clair ; laissez le champ vide et la couleur de la barre latérale ci-dessus s’applique aux deux modes. Un design sans mode clair lit la valeur sans rien en faire.';
$wb['rail_contrast_txt'] = 'Contraste du texte de la barre latérale :';
$wb['preview_head_txt'] = 'Aperçu';
$wb['preview_hint_txt'] = 'Les couleurs et le nom du panneau se mettent à jour pendant la saisie. Le logo utilisé par chaque surface est déterminé par le serveur, afin de correspondre à ce que le panneau affichera réellement.';
$wb['preview_nav_txt'] = 'Navigation';
$wb['preview_login_txt'] = 'Écran de connexion';
$wb['preview_tab_txt'] = 'Icône d’onglet';
$wb['preview_failed_txt'] = 'L’aperçu n’a pas pu être actualisé. Vous voyez la dernière version confirmée par le serveur.';
```

`it_customizer.lng`:

```php
$wb['settings_saved_txt'] = 'Modifiche salvate. I colori del pannello stesso appaiono dopo un ricaricamento completo della pagina (F5).';
$wb['btn_save_txt'] = 'Salva le modifiche';

$wb['identity_head_txt'] = 'Identità';
$wb['colour_head_txt'] = 'Colori';
$wb['visibility_head_txt'] = 'Visibilità nel pannello';
$wb['placement_head_txt'] = 'Posizionamento';
$wb['rail_hex_light_txt'] = 'Colore della barra laterale (modalità chiara)';
$wb['rail_hex_light_hint_txt'] = 'Usato dai design che offrono una modalità chiara; se lo lascia vuoto, il colore della barra laterale sopra vale per entrambe le modalità. Un design senza modalità chiara legge il valore e non ne fa nulla.';
$wb['rail_contrast_txt'] = 'Contrasto del testo della barra laterale:';
$wb['preview_head_txt'] = 'Anteprima';
$wb['preview_hint_txt'] = 'I colori e il nome del pannello si aggiornano mentre digita. Quale logo usa ogni superficie lo decide il server, così corrisponde a ciò che il pannello mostrerà davvero.';
$wb['preview_nav_txt'] = 'Navigazione';
$wb['preview_login_txt'] = 'Schermata di accesso';
$wb['preview_tab_txt'] = 'Icona della scheda';
$wb['preview_failed_txt'] = 'Non è stato possibile aggiornare l’anteprima. Quella mostrata è l’ultima versione confermata dal server.';
```

`nl_customizer.lng`:

```php
$wb['settings_saved_txt'] = 'Wijzigingen opgeslagen. De kleuren van het paneel zelf verschijnen na een volledige vernieuwing van de pagina (F5).';
$wb['btn_save_txt'] = 'Wijzigingen opslaan';

$wb['identity_head_txt'] = 'Identiteit';
$wb['colour_head_txt'] = 'Kleuren';
$wb['visibility_head_txt'] = 'Zichtbaarheid in het paneel';
$wb['placement_head_txt'] = 'Plaatsing';
$wb['rail_hex_light_txt'] = 'Zijbalkkleur (lichte modus)';
$wb['rail_hex_light_hint_txt'] = 'Wordt gebruikt door ontwerpen met een lichte kleurmodus; laat het leeg en de zijbalkkleur hierboven geldt in beide modi. Een ontwerp zonder lichte modus leest de waarde en doet er niets mee.';
$wb['rail_contrast_txt'] = 'Contrast van de zijbalktekst:';
$wb['preview_head_txt'] = 'Voorbeeld';
$wb['preview_hint_txt'] = 'Kleuren en de paneelnaam worden bijgewerkt terwijl u typt. Welk logo elk oppervlak gebruikt, bepaalt de server, zodat het overeenkomt met wat het paneel echt toont.';
$wb['preview_nav_txt'] = 'Navigatie';
$wb['preview_login_txt'] = 'Aanmeldscherm';
$wb['preview_tab_txt'] = 'Tabblad-icoon';
$wb['preview_failed_txt'] = 'Het voorbeeld kon niet worden vernieuwd. U ziet de laatste versie die de server heeft bevestigd.';
```

`pt_customizer.lng`:

```php
$wb['settings_saved_txt'] = 'Alterações guardadas. As cores do próprio painel aparecem após uma atualização completa da página (F5).';
$wb['btn_save_txt'] = 'Guardar alterações';

$wb['identity_head_txt'] = 'Identidade';
$wb['colour_head_txt'] = 'Cores';
$wb['visibility_head_txt'] = 'Visibilidade no painel';
$wb['placement_head_txt'] = 'Posicionamento';
$wb['rail_hex_light_txt'] = 'Cor da barra lateral (modo claro)';
$wb['rail_hex_light_hint_txt'] = 'Usada pelos designs que oferecem modo claro; se ficar em branco, a cor da barra lateral acima vale nos dois modos. Um design sem modo claro lê o valor e não faz nada com ele.';
$wb['rail_contrast_txt'] = 'Contraste do texto da barra lateral:';
$wb['preview_head_txt'] = 'Pré-visualização';
$wb['preview_hint_txt'] = 'As cores e o nome do painel são atualizados enquanto escreve. Que logótipo cada superfície usa é decidido pelo servidor, para corresponder ao que o painel vai mostrar.';
$wb['preview_nav_txt'] = 'Navegação';
$wb['preview_login_txt'] = 'Ecrã de início de sessão';
$wb['preview_tab_txt'] = 'Ícone do separador';
$wb['preview_failed_txt'] = 'Não foi possível atualizar a pré-visualização. O que vê é a última versão confirmada pelo servidor.';
```

- [ ] **Step 3: Check the wordbooks before touching the markup**

Run:
```bash
find interface -name '*.lng' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
php .github/scripts/lang_check.php
grep -c "logo_head_txt\|brand_head_txt\|credits_head_txt" interface/web/customizer/lib/lang/*_customizer.lng
```
Expected: no `LINT FAIL`; `language files OK` reporting `tform wordbook: 7 file(s) match en_customizer.lng (90 keys)`; the grep prints `0` for all seven files. (`credits_hint_txt` is a different key and survives — if the grep is non-zero, check you have not deleted that one.)

- [ ] **Step 4: Replace the page's CSS block**

In `interface/web/customizer/templates/customizer_edit.htm`, replace the whole existing `<style> … </style>` block with:

```html
<style>
  /* This page has no stylesheet of its own, by design. Every colour below is
     var(--pz-…, var(--nz-…, <stock fallback>)) — phosphor's token, then
     clarity's, then a neutral literal — so the page inherits whichever design is
     active and looks deliberate under a design that has neither. No rule here
     may name a colour that belongs to one design.

     Everything is prefixed #nz-brandpage or #pageContent so it beats a design's
     own rules on specificity rather than with !important, and nothing here
     styles focus: every design ships a focus indicator and this page must not
     replace it. */

  /* The two columns. The collapse is measured on the CONTENT column, not the
     viewport: a rail takes ~232px, so a 1024px window leaves this page far less
     than 1024px to lay out in and a viewport query would hold two columns in a
     space that cannot carry them. container-type goes on OUR wrapper — never on
     #pageContent, which is core's element. The viewport query is the fallback
     where @container is unsupported, and is set wide enough that it errs toward
     one column. */
  #nz-brandpage { container-type: inline-size; container-name: nzbrand; }
  #nz-brandpage .nz-brandgrid { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: 28px; align-items: start; }
  @container nzbrand (max-width: 900px) {
    #nz-brandpage .nz-brandgrid { grid-template-columns: minmax(0, 1fr); }
    #nz-brandpage .nz-brandpreview { order: -1; position: static; }
  }
  @supports not (container-type: inline-size) {
    @media (max-width: 1200px) {
      #nz-brandpage .nz-brandgrid { grid-template-columns: minmax(0, 1fr); }
      #nz-brandpage .nz-brandpreview { order: -1; position: static; }
    }
  }
  #nz-brandpage .nz-brandfields { min-width: 0; }

  /* The preview column. Sticky under the topbar, whose height is a design token
     where one exists. */
  #nz-brandpage .nz-brandpreview {
    position: sticky;
    top: calc(var(--pz-topbar-h, var(--nz-topbar-h, 56px)) + 16px);
    min-width: 0;
    padding: 16px;
    border: 1px solid var(--pz-edge, var(--nz-border, rgba(128,128,128,0.25)));
    border-radius: var(--pz-radius-card, var(--nz-radius-card, 8px));
    background: var(--pz-glass, var(--nz-card, transparent));
  }
  #nz-brandpage .nz-brandpreview h2 { margin: 0 0 4px; font-size: 16px; font-weight: 600; }
  #nz-brandpage .nz-brandpreview .help-block { margin: 0 0 14px; }
  #nz-brandpage .nz-prevpane { margin: 0 0 14px; }
  #nz-brandpage .nz-prevpane:last-child { margin-bottom: 0; }
  #nz-brandpage .nz-prevpane figcaption {
    margin: 0 0 6px; font-size: 11px; letter-spacing: 0.06em; text-transform: uppercase;
    color: var(--pz-ink-muted, var(--nz-text-muted, #6c757d));
  }
  #nz-brandpage .nz-prevframe {
    border: 1px solid var(--pz-edge, var(--nz-border, rgba(128,128,128,0.25)));
    border-radius: var(--pz-radius-ctl, var(--nz-radius-ctl, 4px));
    overflow: hidden;
  }
  /* The panes are painted by JS from the operator's own colours; these are the
     shapes only, so an unset colour still draws a recognisable frame. */
  #nz-brandpage .nz-prevnav { display: flex; min-height: 92px; }
  #nz-brandpage .nz-prevnav-rail { flex: none; width: 92px; padding: 9px 0; }
  #nz-brandpage .nz-prevnav-logo { padding: 0 10px 9px; font-size: 11px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  #nz-brandpage .nz-prevnav-item { position: relative; padding: 3px 10px; font-size: 10px; }
  #nz-brandpage .nz-prevnav-item.is-active { font-weight: 600; }
  #nz-brandpage .nz-prevnav-item.is-active::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 2px; background: currentColor; }
  #nz-brandpage .nz-prevnav-body { flex: 1 1 auto; padding: 10px; background: var(--pz-ground, var(--nz-page, #ffffff)); }
  #nz-brandpage .nz-prevbar { height: 7px; margin-bottom: 6px; border-radius: 2px; background: var(--pz-raised, var(--nz-card, rgba(128,128,128,0.18))); }
  #nz-brandpage .nz-prevbar.w60 { width: 60%; }
  #nz-brandpage .nz-prevbar.w40 { width: 40%; }
  #nz-brandpage .nz-prevbtn { display: inline-block; margin-top: 4px; padding: 3px 9px; border-radius: 3px; font-size: 10px; font-weight: 700; }
  #nz-brandpage .nz-prevlogin { padding: 16px; text-align: center; }
  #nz-brandpage .nz-prevlogin-name { margin-bottom: 8px; font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  #nz-brandpage .nz-prevlogin-card {
    padding: 10px; border-radius: var(--pz-radius-ctl, var(--nz-radius-ctl, 4px));
    border: 1px solid var(--pz-edge, var(--nz-border, rgba(128,128,128,0.25)));
  }
  #nz-brandpage .nz-prevfield { height: 18px; margin-bottom: 6px; border-radius: 3px; background: var(--pz-raised, var(--nz-card, rgba(128,128,128,0.18))); }
  #nz-brandpage .nz-prevtab { display: flex; align-items: center; padding: 10px 12px; }
  #nz-brandpage .nz-prevtab-chip {
    display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px;
    border-radius: 5px 5px 0 0; font-size: 11px;
    background: var(--pz-raised, var(--nz-card, rgba(128,128,128,0.18)));
  }
  #nz-brandpage .nz-prevtab-chip img, #nz-brandpage .nz-prevtab-chip .nz-prevtab-icon { width: 16px; height: 16px; flex: none; border-radius: 3px; }
  /* The ratio readouts: a number, tabular so it does not jump while typing. */
  #nz-brandpage .nz-ratio { font-variant-numeric: tabular-nums; }

  /* One block per logo variant: the mark on the background it is FOR, beside the
     file input, the buttons and the by-reference path — so "which artwork, for
     which background" is one decision an operator can check by eye. */
  #nz-brandpage .nz-upload {
    display: grid; grid-template-columns: 108px minmax(0, 1fr); gap: 14px;
    padding: 14px 0; border-top: 1px solid var(--pz-edge-soft, var(--nz-border-soft, rgba(128,128,128,0.25)));
  }
  #nz-brandpage .nz-upload:first-of-type { border-top: 0; padding-top: 4px; }
  #nz-brandpage .nz-upload > h4 { grid-column: 1 / -1; margin: 0 0 2px; font-size: 15px; font-weight: 700; }
  #nz-brandpage .nz-upload > .help-block { grid-column: 1 / -1; margin: 0 0 4px; }
  #nz-brandpage .nz-upload-mark { min-width: 0; }
  #nz-brandpage .nz-upload-mark img { max-width: 100%; }
  #nz-brandpage .nz-upload-fields { min-width: 0; }
  #nz-brandpage .nz-upload-row { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 8px; }
  /* The only place Bootstrap's own columns are overridden, and only inside our
     block: at this width a 3/9 split leaves nothing for the control, so the
     label sits above it. The grid itself is untouched everywhere else. */
  #nz-brandpage .nz-upload-fields .col-sm-3,
  #nz-brandpage .nz-upload-fields .col-sm-6,
  #nz-brandpage .nz-upload-fields .col-sm-9 { width: auto; float: none; padding-left: 0; padding-right: 0; text-align: left; }
  #nz-brandpage .nz-upload-fields .col-sm-9 { width: 100%; }

  /* The hex fields keep their swatch on one line (form-control is a 100%-width
     block in the stock theme and in clarity). */
  #pageContent input.nz-hexfield { display: inline-block; width: 9.5em; vertical-align: middle; }
  #pageContent input.nz-hexpick {
    width: 48px; height: 36px; padding: 2px 3px; margin-left: 8px; vertical-align: middle;
    border: 1px solid var(--pz-edge-input, var(--nz-border-strong, rgba(128,128,128,0.45)));
    border-radius: var(--pz-radius-ctl, var(--nz-radius-ctl, 4px));
    background: none; cursor: pointer;
  }
  /* A field the server rejected. Marked here as well as in the banner, because
     a banner at the top of a two-column page does not say WHICH control. */
  #pageContent .nz-invalid input.form-control,
  #pageContent .nz-invalid select.form-control {
    border-color: var(--pz-danger, var(--nz-danger, #d9534f));
  }
  #pageContent .nz-fielderror { margin-top: 4px; color: var(--pz-danger, var(--nz-danger-text, #d9534f)); }

  #nz-brandpage .nz-actions { margin-top: 18px; text-align: right; }

  @media (max-width: 767px) {
    #nz-brandpage .nz-upload { grid-template-columns: minmax(0, 1fr); }
  }
</style>
```

- [ ] **Step 5: Replace the page body**

Replace everything in `templates/customizer_edit.htm` between the `</style>` line and the opening `<script>` tag with the markup below. Note what is deliberately preserved: every id the JS binds, the `<tmpl_if>` upload-message blocks, the hidden `id` input, and the Save/Cancel buttons with their `data-submit-form` / `data-form-action` attributes.

```html
<!-- Upload feedback only (logo_upload.php's iframe response — the uploader
     driver scrapes #OKMsg/#errorMsg out of it). Deliberately NOT named msg/error:
     the tabbed_form wrapper renders those vars itself, so reusing the names
     would show every save/validation banner twice. -->
<tmpl_if name="upload_msg">
  <div id="OKMsg" class="alert alert-success clear"><tmpl_var name="upload_msg"></div>
</tmpl_if>
<tmpl_if name="upload_error">
  <div id="errorMsg" class="alert alert-danger clear"><tmpl_var name="upload_error"></div>
</tmpl_if>

<div id="nz-brandpage">
<div class="nz-brandgrid">
<div class="nz-brandfields">

<!-- IDENTITY — what the panel is called and what it is marked with. The panel
     name, both logo variants, where each one goes, and the tab icon: everything
     an operator answers once, in the order they answer it. The two variants and
     the placement block are <h4> sub-blocks rather than nested fieldsets so a
     screen-reader user can jump between them by heading while the group stays
     one landmark. -->
<fieldset>
  <legend>{tmpl_var name='identity_head_txt'}</legend>

  <div class="form-group">
    <label for="company_name" class="col-sm-3 control-label">{tmpl_var name='company_name_txt'}</label>
    <div class="col-sm-9">
      <input type="text" name="company_name" id="company_name" value="{tmpl_var name='company_name'}" class="form-control" />
      <p class="help-block">{tmpl_var name='company_name_hint_txt'}</p>
    </div>
  </div>

  <div class="form-group">
    <div class="col-sm-9 col-sm-offset-3"><p class="help-block">{tmpl_var name='logo_variants_hint_txt'}</p></div>
  </div>

  <!-- Named by the BACKGROUND each mark sits on, because that is what the
       operator can look at and check. Which artwork is "light enough" is a
       judgement; which of their designs has a dark header is a fact. -->
  <div class="nz-upload">
    <h4>{tmpl_var name='logo_on_light_head_txt'}</h4>
    <div class="nz-upload-mark" id="used_logo">{tmpl_var name='used_logo'}</div>
    <div class="nz-upload-fields">
      <div class="nz-upload-row">
        <input name="file" id="file" size="30" type="file" class="fileUpload" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" />
        <!-- Not wired to the stock iframe uploader (no data-submit-form /
             data-form-upload, no formbutton-success): the page-render CSRF token
             it depends on can be silently lost to ISPConfig's lock-free session
             store, and the stock Enter-key handler must not trigger uploads.
             The fetch flow in the script below mints its own token at click time. -->
        <button class="btn btn-default" type="button" id="nz-logo-upload">{tmpl_var name='upload_txt'}</button>
        <a href="javascript:void(0);" class="btn btn-default" id="nz-logo-remove">{tmpl_var name='logo_remove_txt'}</a>
      </div>
      <p class="help-block">{tmpl_var name='logo_hint_txt'}</p>
      <div class="form-group">
        <label for="logo_url" class="col-sm-3 control-label">{tmpl_var name='logo_url_txt'}</label>
        <div class="col-sm-9">
          <input type="text" name="logo_url" id="logo_url" value="{tmpl_var name='logo_url'}" class="form-control" placeholder="/themes/custom/logo.svg" />
          <p class="help-block">{tmpl_var name='logo_url_hint_txt'}</p>
        </div>
      </div>
    </div>
  </div>

  <div class="nz-upload">
    <h4>{tmpl_var name='logo_on_dark_head_txt'}</h4>
    <div class="nz-upload-mark" id="used_logo_on_dark">{tmpl_var name='used_logo_on_dark'}</div>
    <div class="nz-upload-fields">
      <div class="nz-upload-row">
        <!-- The name is deliberately NOT "file", even though logo_upload.php
             reads $_FILES['file'] for both slots: the driver below builds its own
             FormData and appends the chosen file under the name 'file' itself, so
             this attribute never reaches the server. Distinct names keep three
             file inputs unambiguous in a form that also carries a native Save. -->
        <input name="file_on_dark" id="file_on_dark" size="30" type="file" class="fileUpload" accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml,.svg" />
        <button class="btn btn-default" type="button" id="nz-logo-upload-on-dark">{tmpl_var name='upload_txt'}</button>
        <a href="javascript:void(0);" class="btn btn-default" id="nz-logo-remove-on-dark">{tmpl_var name='logo_remove_txt'}</a>
      </div>
      <p class="help-block">{tmpl_var name='logo_on_dark_hint_txt'}</p>
      <div class="form-group">
        <label for="logo_url_on_dark" class="col-sm-3 control-label">{tmpl_var name='logo_url_on_dark_txt'}</label>
        <div class="col-sm-9">
          <input type="text" name="logo_url_on_dark" id="logo_url_on_dark" value="{tmpl_var name='logo_url_on_dark'}" class="form-control" placeholder="/themes/custom/logo-on-dark.svg" />
          <p class="help-block">{tmpl_var name='logo_url_on_dark_hint_txt'}</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Placement, not artwork: which of the two marks above each surface uses.
       It stays a third block AFTER both variants rather than moving inside
       either, because it is scoped by SURFACE and is a statement about the
       pair — nesting it under one mark would read as a property of that mark.

       Only the <option> list comes from tform (tform_base.inc.php:510 assigns
       nothing but <option> tags to the field's own tmpl_var); the <select> shell
       is written here, which is core's idiom too. Keep class="form-control":
       clarity styles body.nz select.form-control with its own arrow SVG, and a
       bare select falls through to a different, list-header-sized rule.

       logo_upload.php renders this same template with no tform at all, so there
       both tmpl_vars are unknown and vlibTemplate removes them — that response
       carries two empty <select>s. Harmless, because the uploader driver only
       scrapes #OKMsg/#errorMsg and the three preview blocks out of it; but
       nothing here may assume these options exist. -->
  <div class="nz-upload">
    <h4>{tmpl_var name='placement_head_txt'}</h4>
    <div class="nz-upload-mark"></div>
    <div class="nz-upload-fields">
      <div class="form-group">
        <label for="logo_variant_nav" class="col-sm-3 control-label">{tmpl_var name='logo_variant_nav_txt'}</label>
        <div class="col-sm-9">
          <select name="logo_variant_nav" id="logo_variant_nav" class="form-control">{tmpl_var name='logo_variant_nav'}</select>
          <p class="help-block">{tmpl_var name='logo_variant_nav_hint_txt'}</p>
        </div>
      </div>
      <div class="form-group">
        <label for="logo_variant_login" class="col-sm-3 control-label">{tmpl_var name='logo_variant_login_txt'}</label>
        <div class="col-sm-9">
          <select name="logo_variant_login" id="logo_variant_login" class="form-control">{tmpl_var name='logo_variant_login'}</select>
          <p class="help-block">{tmpl_var name='logo_variant_login_hint_txt'}</p>
        </div>
      </div>
    </div>
  </div>

  <!-- The favicon is the third brand image and rides the same uploader, but it
       has ONE slot, not two: a tab strip is a tab strip whatever the design's
       header looks like. It sits in Identity rather than in a fieldset of its
       own because it is one more thing the panel is marked with. -->
  <div class="nz-upload">
    <h4>{tmpl_var name='favicon_head_txt'}</h4>
    <div class="nz-upload-mark" id="used_favicon">{tmpl_var name='used_favicon'}</div>
    <div class="nz-upload-fields">
      <div class="nz-upload-row">
        <input name="file_favicon" id="file_favicon" size="30" type="file" class="fileUpload" accept="image/svg+xml,.svg,image/png,image/x-icon,image/vnd.microsoft.icon,.ico" />
        <button class="btn btn-default" type="button" id="nz-favicon-upload">{tmpl_var name='favicon_upload_txt'}</button>
        <a href="javascript:void(0);" class="btn btn-default" id="nz-favicon-remove">{tmpl_var name='logo_remove_txt'}</a>
      </div>
      <p class="help-block">{tmpl_var name='favicon_intro_txt'}</p>
      <p class="help-block">{tmpl_var name='favicon_hint_txt'}</p>
      <div class="form-group">
        <label for="favicon_url" class="col-sm-3 control-label">{tmpl_var name='favicon_url_txt'}</label>
        <div class="col-sm-9">
          <input type="text" name="favicon_url" id="favicon_url" value="{tmpl_var name='favicon_url'}" class="form-control" placeholder="/themes/custom/favicon.svg" />
          <p class="help-block">{tmpl_var name='favicon_url_hint_txt'}</p>
        </div>
      </div>
    </div>
  </div>
</fieldset>

<!-- COLOUR — the four values every brand-aware design reads. -->
<fieldset>
  <legend>{tmpl_var name='colour_head_txt'}</legend>

  <div class="form-group">
    <label for="accent_hex" class="col-sm-3 control-label">{tmpl_var name='accent_hex_txt'}</label>
    <div class="col-sm-9">
      <input type="text" name="accent_hex" id="accent_hex" value="{tmpl_var name='accent_hex'}" class="form-control nz-hexfield" placeholder="#0065AB" />
      <input type="color" id="accent_hex_pick" value="#0065AB" class="nz-hexpick" aria-label="{tmpl_var name='accent_hex_txt'}" />
    </div>
  </div>

  <div class="form-group">
    <label for="rail_hex" class="col-sm-3 control-label">{tmpl_var name='rail_hex_txt'}</label>
    <div class="col-sm-9">
      <input type="text" name="rail_hex" id="rail_hex" value="{tmpl_var name='rail_hex'}" class="form-control nz-hexfield" placeholder="#01243D" />
      <input type="color" id="rail_hex_pick" value="#01243D" class="nz-hexpick" aria-label="{tmpl_var name='rail_hex_txt'}" />
      <p class="help-block">{tmpl_var name='rail_contrast_txt'} <span id="nz-rail-ratio" class="nz-ratio">&mdash;</span></p>
    </div>
  </div>

  <div class="form-group">
    <label for="rail_hex_light" class="col-sm-3 control-label">{tmpl_var name='rail_hex_light_txt'}</label>
    <div class="col-sm-9">
      <input type="text" name="rail_hex_light" id="rail_hex_light" value="{tmpl_var name='rail_hex_light'}" class="form-control nz-hexfield" placeholder="#E7EBF0" />
      <input type="color" id="rail_hex_light_pick" value="#E7EBF0" class="nz-hexpick" aria-label="{tmpl_var name='rail_hex_light_txt'}" />
      <p class="help-block">{tmpl_var name='rail_contrast_txt'} <span id="nz-rail-light-ratio" class="nz-ratio">&mdash;</span></p>
      <p class="help-block">{tmpl_var name='rail_hex_light_hint_txt'}</p>
    </div>
  </div>

  <div class="form-group">
    <label for="login_bg" class="col-sm-3 control-label">{tmpl_var name='login_bg_txt'}</label>
    <div class="col-sm-9">
      <input type="text" name="login_bg" id="login_bg" value="{tmpl_var name='login_bg'}" class="form-control nz-hexfield" placeholder="#17252B" />
      <input type="color" id="login_bg_pick" value="#17252B" class="nz-hexpick" aria-label="{tmpl_var name='login_bg_txt'}" />
      <p class="help-block">{tmpl_var name='colour_hint_txt'}</p>
    </div>
  </div>
</fieldset>

<fieldset>
  <legend>{tmpl_var name='login_head_txt'}</legend>

  <div class="form-group">
    <label for="custom_login_text" class="col-sm-3 control-label">{tmpl_var name='custom_login_text_txt'}</label>
    <div class="col-sm-9"><input type="text" name="custom_login_text" id="custom_login_text" value="{tmpl_var name='custom_login_text'}" class="form-control" /></div>
  </div>
  <div class="form-group">
    <label for="custom_login_link" class="col-sm-3 control-label">{tmpl_var name='custom_login_link_txt'}</label>
    <div class="col-sm-9"><input type="text" name="custom_login_link" id="custom_login_link" value="{tmpl_var name='custom_login_link'}" class="form-control" placeholder="https://" /></div>
  </div>
</fieldset>

<!-- PANEL VISIBILITY — six switches over what the panel shows. The two footer
     credits keep their hint, which is about them alone. -->
<fieldset>
  <legend>{tmpl_var name='visibility_head_txt'}</legend>

  <div class="form-group">
    <label class="col-sm-3 control-label">{tmpl_var name='show_design_picker_txt'}</label>
    <div class="col-sm-9">
      {tmpl_var name='show_design_picker'}
      <p class="help-block">{tmpl_var name='show_design_picker_hint_txt'}</p>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">{tmpl_var name='show_version_txt'}</label>
    <div class="col-sm-9">
      {tmpl_var name='show_version'}
      <p class="help-block">{tmpl_var name='show_version_hint_txt'}</p>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">{tmpl_var name='show_news_feed_txt'}</label>
    <div class="col-sm-9">
      {tmpl_var name='show_news_feed'}
      <p class="help-block">{tmpl_var name='show_news_feed_hint_txt'}</p>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">{tmpl_var name='show_donation_dashlet_txt'}</label>
    <div class="col-sm-9">
      {tmpl_var name='show_donation_dashlet'}
      <p class="help-block">{tmpl_var name='show_donation_dashlet_hint_txt'}</p>
    </div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">{tmpl_var name='show_ispconfig_credit_txt'}</label>
    <div class="col-sm-9">{tmpl_var name='show_ispconfig_credit'}</div>
  </div>
  <div class="form-group">
    <label class="col-sm-3 control-label">{tmpl_var name='show_theme_credit_txt'}</label>
    <div class="col-sm-9">
      {tmpl_var name='show_theme_credit'}
      <p class="help-block">{tmpl_var name='credits_hint_txt'}</p>
    </div>
  </div>
</fieldset>

<input type="hidden" name="id" value="{tmpl_var name='id'}" />

<div class="nz-actions">
  <button class="btn btn-default formbutton-success" type="button" data-submit-form="pageForm" data-form-action="customizer/customizer_edit.php">{tmpl_var name='btn_save_txt'}</button>
  <button class="btn btn-default formbutton-default" type="button" data-load-content="dashboard/dashboard.php">{tmpl_var name='btn_cancel_txt'}</button>
</div>

</div><!-- /.nz-brandfields -->

<!-- THE PREVIEW COLUMN.
     Colours, the rail ink and the panel name are painted here in JS as the
     operator types. Anything depending on VARIANT RESOLUTION is not guessed:
     that resolver exists in three PHP copies CI proves agree, and a fourth in
     JavaScript would put what this page promises outside that guarantee — so
     the marks come from customizer/preview.php.

     Deliberately NOT an ARIA live region: it changes on every keystroke, and
     announcing that is worse than silence. It is labelled, and the ratio
     readouts beside the colour fields are plain text, reachable on demand. -->
<aside class="nz-brandpreview" aria-label="{tmpl_var name='preview_head_txt'}">
  <h2>{tmpl_var name='preview_head_txt'}</h2>
  <p class="help-block">{tmpl_var name='preview_hint_txt'}</p>

  <figure class="nz-prevpane">
    <figcaption>{tmpl_var name='preview_nav_txt'}</figcaption>
    <div class="nz-prevframe">
      <div class="nz-prevnav">
        <div class="nz-prevnav-rail nz-prev-rail">
          <div class="nz-prevnav-logo nz-prev-name"></div>
          <div class="nz-prevnav-item is-active nz-prev-accent-rule">&nbsp;</div>
          <div class="nz-prevnav-item">&nbsp;</div>
          <div class="nz-prevnav-item">&nbsp;</div>
        </div>
        <div class="nz-prevnav-body">
          <div class="nz-prevbar w60"></div>
          <div class="nz-prevbar w40"></div>
          <span class="nz-prevbtn nz-prev-accent">{tmpl_var name='btn_save_txt'}</span>
        </div>
      </div>
    </div>
  </figure>

  <figure class="nz-prevpane">
    <figcaption>{tmpl_var name='preview_login_txt'}</figcaption>
    <div class="nz-prevframe">
      <div class="nz-prevlogin nz-prev-login">
        <div class="nz-prevlogin-name nz-prev-name"></div>
        <div class="nz-prevlogin-card">
          <div class="nz-prevfield"></div>
          <div class="nz-prevfield"></div>
          <span class="nz-prevbtn nz-prev-accent">&nbsp;</span>
        </div>
      </div>
    </div>
  </figure>

  <figure class="nz-prevpane">
    <figcaption>{tmpl_var name='preview_tab_txt'}</figcaption>
    <div class="nz-prevframe">
      <div class="nz-prevtab">
        <span class="nz-prevtab-chip">
          <span class="nz-prevtab-icon nz-prev-accent"></span>
          <span class="nz-prev-name"></span>
        </span>
      </div>
    </div>
  </figure>
</aside>

</div><!-- /.nz-brandgrid -->
</div><!-- /#nz-brandpage -->
```

- [ ] **Step 6: Verify the markup**

Run:
```bash
php .github/scripts/lang_check.php
python3 - <<'PY'
import re
src = open('interface/web/customizer/templates/customizer_edit.htm').read()
ids = ['nz-msg-slot','used_logo','used_logo_on_dark','used_favicon','file','file_on_dark',
       'file_favicon','nz-logo-upload','nz-logo-upload-on-dark','nz-favicon-upload',
       'nz-logo-remove','nz-logo-remove-on-dark','nz-favicon-remove','accent_hex',
       'accent_hex_pick','rail_hex','rail_hex_pick','rail_hex_light','rail_hex_light_pick',
       'login_bg','login_bg_pick','nz-rail-ratio','nz-rail-light-ratio','nz-brandpage']
missing = [i for i in ids if ('id="%s"' % i) not in src]
print('MISSING IDS:', missing or 'none')
names = ['company_name','logo_url','logo_url_on_dark','logo_variant_nav','logo_variant_login',
         'favicon_url','accent_hex','rail_hex','rail_hex_light','login_bg','custom_login_text',
         'custom_login_link','id']
print('MISSING NAMES:', [n for n in names if ('name="%s"' % n) not in src] or 'none')
print('OPEN DIVS:', src.count('<div'), 'CLOSE DIVS:', src.count('</div>'))
print('pz- classes (must be 0):', len(re.findall(r'class="[^"]*\bpz-', src)))
print('bare hex colours in style block (must be 0):',
      len(re.findall(r':\s*#[0-9A-Fa-f]{3,8}\s*[;,)]', src.split('</style>')[0])))
PY
grep -c 'tmpl_var' interface/web/customizer/templates/customizer_edit.htm
```
Expected: `language files OK`; `MISSING IDS: none`; `MISSING NAMES: none`; the div counts equal; zero `pz-` classes; zero bare hex colours in the style block (every colour goes through a `var()` chain whose *fallback* hexes appear inside `var(…)` parentheses, which the pattern above does not match — if it reports more than zero, a colour was written directly).

Panel-only (state in the commit body, do not attempt locally): load the Branding page under clarity in both colour modes, under classic, and at 1440, 1280 and 1024 wide; confirm two columns above the collapse and preview-above-fields below it, and that Save, Upload and Remove all still work.

- [ ] **Step 7: Commit**

```bash
git add interface/web/customizer/templates/customizer_edit.htm \
        interface/web/customizer/lib/lang/*_customizer.lng
git commit -m "Rebuild the Branding page as two columns with inline uploaders and a preview pane"
```

---

## Task 8b: The live preview — hex sync, painted panes, the debounced fetch, and inline validation

**Files:**
- Modify: `interface/web/customizer/customizer_edit.php` (publish the field→error-text map)
- Modify: `interface/web/customizer/templates/customizer_edit.htm` (the `<script>` block, and one attribute on `#nz-brandpage`)

**Interfaces:**
- Consumes: the DOM contract from Task 8a; `POST customizer/preview.php` from Task 7 returning `{previews:{used_logo,used_logo_on_dark,used_favicon}, surfaces:[…], colours:{accent,rail,rail_light,login}}`.
- Produces: nothing a later task depends on — this is the last change to the page itself.

**Rules for this task:**

- **`wireUpload()`, `wireRemove()` and the MutationObserver block are carried over byte-identically.** Do not retype them from memory and do not refactor them: the click-time CSRF mint and the self-disconnecting observer are the two things in this file that must never be reimplemented. Copy them from the existing file.
- ES5-compatible syntax with feature detection, matching the existing block: no arrow functions, no `const`/`let`, no template literals. `fetch`, `FormData`, `DOMParser`, `MutationObserver` and `AbortController` are each guarded before use.
- **Progressive enhancement.** With JS unavailable the page is a working form: the fields save, the uploaders are the only thing that stop working (which is already true today), and the preview column simply stays unpainted.
- The JS never decides which logo variant a surface uses. It paints colours and the panel name, and it renders what `preview.php` returns.

- [ ] **Step 1: Publish the field→error map from the controller**

In `interface/web/customizer/customizer_edit.php`, add this method to `page_action` and call it from `onShowEnd()` immediately before `parent::onShowEnd();`:

```php
    /**
     * Which field each validation message belongs to, as JSON for the page.
     *
     * tform reports validation failures as one banner of translated SENTENCES
     * with no field names in it. On a one-column form the offending control was
     * a short scroll away; on a two-column one a banner at the top is a message
     * about a control the operator may not even be able to see. The page marks
     * the field itself as well — and to do that it has to be able to tell which
     * message is whose.
     *
     * The map is built from the form definition, so it cannot drift from the
     * errmsg keys the validators actually name: field => the message that field
     * would produce. Entity-decoded because the banner is read back as
     * textContent, where "&times;" has already become "×"; JSON_HEX_* because
     * the result lands in an HTML attribute and must not be able to close it.
     *
     * A field whose errmsg key is missing from the wordbook is skipped rather
     * than mapped to the raw key: tform prints the key itself in that case, and
     * matching on it would mark a field on the strength of a bug elsewhere.
     */
    private function publish_field_error_map() {
        global $app;
        $map = array();
        foreach($app->tform->formDef['tabs'][$this->active_tab]['fields'] as $key => $field) {
            if(!isset($field['validators']) || !is_array($field['validators'])) continue;
            foreach($field['validators'] as $v) {
                if(!isset($v['errmsg'])) continue;
                $txt = $app->tform->lng($v['errmsg']);
                if(!is_string($txt) || $txt === '' || $txt === $v['errmsg']) continue;
                $map[$key] = html_entity_decode($txt, ENT_QUOTES, 'UTF-8');
            }
        }
        $app->tpl->setVar('field_errors_json',
            json_encode($map, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT));
    }
```

and in `onShowEnd()`:

```php
    function onShowEnd() {
        global $app;
        $this->render_image_previews();
        $this->publish_field_error_map();
        //* the post-save redirect appends msg=saved (see list_default in the form
        //* definition) — without this banner a successful save is indistinguishable
        //* from a silently failed one
        if(isset($_GET['msg']) && $_GET['msg'] === 'saved' && $app->tform->errorMessage == '') {
            $app->tpl->setVar('msg', $app->tform->lng('settings_saved_txt'));
        }
        parent::onShowEnd();
    }
```

- [ ] **Step 2: Carry the map and the failure text into the page**

In `templates/customizer_edit.htm`, replace the opening tag written in Task 8a — `<div id="nz-brandpage">` — with this, comment included. Both attributes are set here, once; nothing later in this task edits this tag again:

```html
<!-- Two values the script below needs, carried as ATTRIBUTES rather than as an
     inline var assignment: logo_upload.php renders this same template with no
     tform, so there both tmpl_vars are unknown, vlibTemplate removes them, and
     an empty attribute is something the readers cope with while an empty
     assignment would be a syntax error.

     data-field-errors is the field => validation-message map built by
     publish_field_error_map() in customizer_edit.php, JSON with the four HEX
     flags so it cannot close the attribute it sits in. -->
<div id="nz-brandpage" data-field-errors="{tmpl_var name='field_errors_json'}" data-preview-failed="{tmpl_var name='preview_failed_txt'}">
```

- [ ] **Step 3: Replace the hex-sync function and add the preview driver**

In the template's `<script>` IIFE:

(a) delete the existing `function sync(txtId, pickId) { … }` and its three `sync(…)` calls;

(b) **leave the MutationObserver block, `wireUpload()`, `wireRemove()` and all six of their calls exactly as they are** — copy them across unchanged;

(c) insert this before the observer block:

```js
  // ---- the live preview -------------------------------------------------
  //
  // Two halves, and the split is the point. Colours, the panel name and the
  // rail INK are painted here as you type, from the same measured-contrast rule
  // the readers use — a white sidebar shows dark text immediately, with no round
  // trip. Anything that depends on which logo VARIANT a surface uses is NOT
  // guessed here: that resolver exists in three PHP copies CI proves agree, and
  // a fourth one in JavaScript would put what this page promises outside that
  // guarantee. Those parts come from customizer/preview.php, debounced.

  var HEX = /^#[0-9A-Fa-f]{6}$/;

  // WCAG 2.x relative luminance and contrast, written from the spec.
  function srgb(c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }
  function lum(hex) {
    return 0.2126 * srgb(parseInt(hex.substr(1, 2), 16)) +
           0.7152 * srgb(parseInt(hex.substr(3, 2), 16)) +
           0.0722 * srgb(parseInt(hex.substr(5, 2), 16));
  }
  function ratio(a, b) {
    var la = lum(a), lb = lum(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  }
  // The DIRECTION half of clarity's brand_rail_vars() and of the module's
  // customizer_rail_ink(): compare the two ratios rather than pivoting on a
  // lightness constant, so a rail near the crossover (#767676) is not handed the
  // worse ink by a rule that rounds. The server sends the same answer back with
  // every preview response; this is what makes it instant.
  function inkFor(bg) { return ratio(bg, '#FFFFFF') >= ratio(bg, '#000000') ? '#FFFFFF' : '#000000'; }

  // A design token as an actual colour, or '' — this is how the panes stay
  // design-agnostic: with no colour set they show the ACTIVE design's own.
  function token(names, fallback) {
    var cs = window.getComputedStyle ? getComputedStyle(document.documentElement) : null;
    for (var i = 0; cs && i < names.length; i++) {
      var v = (cs.getPropertyValue(names[i]) || '').trim();
      if (HEX.test(v)) return v;
    }
    return fallback;
  }

  function fieldValue(id, fallback) {
    var el = document.getElementById(id);
    if (!el) return fallback;
    var v = (el.value || '').trim();
    if (/^[0-9A-Fa-f]{6}$/.test(v)) v = '#' + v;
    return HEX.test(v) ? v.toUpperCase() : fallback;
  }

  function setRatio(id, bg, ink) {
    var el = document.getElementById(id);
    if (!el) return;
    el.textContent = (bg === '') ? '—' : (ratio(bg, ink).toFixed(2) + ':1');
  }

  function paintPreview() {
    var railToken  = token(['--pz-band', '--nz-rail'], '#2E3A44');
    var accentTok  = token(['--pz-accent', '--nz-blue-400'], '#0065AB');
    var loginTok   = token(['--pz-ground', '--nz-page'], '#17252B');

    var railRaw  = fieldValue('rail_hex', '');
    var lightRaw = fieldValue('rail_hex_light', '');
    var rail   = railRaw  !== '' ? railRaw  : railToken;
    var accent = fieldValue('accent_hex', accentTok);
    var login  = fieldValue('login_bg', loginTok);
    // The light rail falls back to the rail, which is what a design with a light
    // scope does with an empty value — the preview must show that, not nothing.
    var light  = lightRaw !== '' ? lightRaw : rail;

    var nameEl = document.getElementById('company_name');
    var name   = nameEl && nameEl.value.trim() ? nameEl.value.trim() : '';

    var railBox = document.querySelector('.nz-prev-rail');
    if (railBox) { railBox.style.background = rail; railBox.style.color = inkFor(rail); }

    var loginBox = document.querySelector('.nz-prev-login');
    if (loginBox) { loginBox.style.background = login; loginBox.style.color = inkFor(login); }

    var i, els;
    els = document.querySelectorAll('.nz-prev-accent');
    for (i = 0; i < els.length; i++) { els[i].style.background = accent; els[i].style.color = inkFor(accent); }
    els = document.querySelectorAll('.nz-prev-accent-rule');
    for (i = 0; i < els.length; i++) { els[i].style.color = accent; }
    // textContent, never innerHTML: the panel name is the operator's own text and
    // has no business being parsed as markup on the page that sets it.
    els = document.querySelectorAll('.nz-prev-name');
    for (i = 0; i < els.length; i++) { els[i].textContent = name; }

    setRatio('nz-rail-ratio', railRaw, inkFor(rail));
    setRatio('nz-rail-light-ratio', lightRaw !== '' ? lightRaw : railRaw, inkFor(light));
  }

  // ---- what only the server can answer -----------------------------------
  //
  // One debounced, same-origin POST carrying the candidate values. No CSRF token
  // is minted for it: minting WRITES the lock-free session, this fires while
  // somebody is typing, and the endpoint writes nothing — see preview.php.
  var PREVIEW_FIELDS = ['accent_hex', 'rail_hex', 'rail_hex_light', 'login_bg',
                        'logo_url', 'logo_url_on_dark', 'favicon_url',
                        'logo_variant_nav', 'logo_variant_login'];
  var previewTimer = null;
  var previewAbort = null;

  function refreshPreview() {
    if (!window.fetch || !window.FormData) return;
    var fd = new FormData();
    for (var i = 0; i < PREVIEW_FIELDS.length; i++) {
      var el = document.getElementById(PREVIEW_FIELDS[i]);
      if (el) fd.append(PREVIEW_FIELDS[i], el.value);
    }

    // Only the newest answer is wanted; an older one landing later would show
    // the operator a state they have already typed past.
    var opts = { method: 'POST', body: fd, credentials: 'same-origin',
                 headers: { 'X-Requested-With': 'XMLHttpRequest' } };
    if (window.AbortController) {
      if (previewAbort) previewAbort.abort();
      previewAbort = new AbortController();
      opts.signal = previewAbort.signal;
    }

    fetch('customizer/preview.php', opts)
      .then(function (r) {
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (data) {
        if (!data || !data.previews) throw new Error('unexpected response');
        // The three preview rows are SERVER-rendered by the same functions the
        // page load and logo_upload.php use, so the three cannot disagree. This
        // is the same innerHTML hand-off the uploader already performs on its
        // own response, and the same trust: the fragment is built by our own
        // renderers out of validated values and wordbook text.
        var rows = ['used_logo', 'used_logo_on_dark', 'used_favicon'];
        for (var i = 0; i < rows.length; i++) {
          var slot = document.getElementById(rows[i]);
          if (slot && typeof data.previews[rows[i]] === 'string') slot.innerHTML = data.previews[rows[i]];
        }
        // The server's measured ink is authoritative; it agrees with the one
        // painted above on every colour, and is applied so that the page shows
        // the answer the panel will use rather than the answer it guessed.
        if (data.colours) {
          if (data.colours.rail && data.colours.rail.hex) setRatio('nz-rail-ratio', data.colours.rail.hex, data.colours.rail.ink);
          if (data.colours.rail_light && data.colours.rail_light.hex) setRatio('nz-rail-light-ratio', data.colours.rail_light.hex, data.colours.rail_light.ink);
        }
        previewNote('');
      })
      .catch(function (e) {
        // An aborted request is this function superseding itself, not a failure.
        if (e && e.name === 'AbortError') return;
        previewNote(pageText('preview-failed'));
      });
  }

  function schedulePreview() {
    if (previewTimer) clearTimeout(previewTimer);
    previewTimer = setTimeout(refreshPreview, 350);
  }

  // A failed refresh is SAID, not swallowed: the panes would otherwise keep
  // showing a state the server never confirmed, with nothing to indicate it.
  function previewNote(text) {
    var box = document.querySelector('.nz-brandpreview');
    if (!box) return;
    var note = document.getElementById('nz-preview-note');
    if (!text) { if (note) note.parentNode.removeChild(note); return; }
    if (!note) {
      note = document.createElement('p');
      note.id = 'nz-preview-note';
      note.className = 'help-block nz-fielderror';
      box.appendChild(note);
    }
    note.textContent = text;
  }

  // ---- inline validation -------------------------------------------------
  //
  // The banner keeps its place under the page header; this additionally marks
  // the control the banner is talking about, which a two-column page needs
  // because the banner and the field can be a screen apart. The mapping is
  // published by the server from the form definition (see
  // publish_field_error_map()), so it cannot drift from the errmsg keys the
  // validators name.
  function pageText(what) {
    var host = document.getElementById('nz-brandpage');
    var raw = host ? host.getAttribute('data-' + what) : null;
    return raw || '';
  }

  function fieldErrorMap() {
    var host = document.getElementById('nz-brandpage');
    if (!host) return {};
    try { return JSON.parse(host.getAttribute('data-field-errors') || '{}'); }
    catch (e) { return {}; }
  }

  function markInvalidFields() {
    var banner = document.getElementById('errorMsg');
    var map = fieldErrorMap();
    var text = banner ? (banner.textContent || '') : '';
    for (var field in map) {
      if (!Object.prototype.hasOwnProperty.call(map, field)) continue;
      var input = document.getElementById(field);
      if (!input) continue;
      var group = input.closest ? input.closest('.form-group') : null;
      var hit = (text !== '' && map[field] !== '' && text.indexOf(map[field]) !== -1);
      if (group) group.className = group.className.replace(/\s*\bnz-invalid\b/, '') + (hit ? ' nz-invalid' : '');
      if (hit) {
        input.setAttribute('aria-invalid', 'true');
        if (!document.getElementById('nz-err-' + field)) {
          var p = document.createElement('p');
          p.id = 'nz-err-' + field;
          p.className = 'help-block nz-fielderror';
          p.textContent = map[field];
          if (input.parentNode) input.parentNode.appendChild(p);
        }
        input.setAttribute('aria-describedby', 'nz-err-' + field);
      } else {
        input.removeAttribute('aria-invalid');
        var old = document.getElementById('nz-err-' + field);
        if (old && old.parentNode) old.parentNode.removeChild(old);
      }
    }
  }

  // ---- wiring ------------------------------------------------------------
  //
  // Advisory only, on blur: a client-side mirror of the hex pattern that repairs
  // what the server would repair anyway and never blocks a submission. The
  // server-side validators stay authoritative.
  function syncHex(txtId, pickId) {
    var t = document.getElementById(txtId), p = document.getElementById(pickId);
    if (!t) return;
    if (p && HEX.test(t.value)) p.value = t.value;
    function fromPick() { t.value = p.value.toUpperCase(); paintPreview(); schedulePreview(); }
    if (p) {
      // 'input' alone is NOT enough: Firefox's native colour dialog only fires
      // 'change' when it closes, so a picker-chosen colour never reached the
      // named text field and every save posted an empty value.
      p.addEventListener('input', fromPick);
      p.addEventListener('change', fromPick);
    }
    t.addEventListener('input', function () {
      if (p && HEX.test(t.value)) p.value = t.value;
      paintPreview();
      schedulePreview();
    });
    t.addEventListener('blur', function () {
      var v = t.value.trim();
      if (/^[0-9A-Fa-f]{6}$/.test(v)) v = '#' + v;
      if (HEX.test(v)) { v = v.toUpperCase(); t.value = v; if (p) p.value = v; }
      paintPreview();
      schedulePreview();
    });
  }

  if (document.getElementById('nz-brandpage')) {
    syncHex('accent_hex', 'accent_hex_pick');
    syncHex('rail_hex', 'rail_hex_pick');
    syncHex('rail_hex_light', 'rail_hex_light_pick');
    syncHex('login_bg', 'login_bg_pick');

    var nameInput = document.getElementById('company_name');
    if (nameInput) nameInput.addEventListener('input', paintPreview);

    // The reference paths and the two placement selects change WHICH mark a
    // surface gets, which only the server can answer.
    ['logo_url', 'logo_url_on_dark', 'favicon_url'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('input', schedulePreview);
    });
    ['logo_variant_nav', 'logo_variant_login'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.addEventListener('change', schedulePreview);
    });

    paintPreview();
    markInvalidFields();
    // One refresh on load so the panes describe the STORED state through the
    // same code path everything else uses, rather than through a second one.
    schedulePreview();
  }
```

(d) inside the existing observer's `relocate()` function, after the `if (moved) slot.scrollIntoView(…)` line, add one call so a banner that arrives later still marks its field:

```js
      if (moved) markInvalidFields();
```

(e) the preview flashes once to the applied state after a save, so the confirmation banner is not the only thing that says the change landed. Add these two rules to the end of the template's `<style>` block:

```css
  /* One flash of the preview panes after a save, so "Changes saved" is not the
     only signal that the panes now describe stored values. It only ADDS a ring
     and takes nothing away, which is why it is safe to gate on reduced motion:
     under `reduce` the rule simply never applies and the panes render normally.
     Nothing here may ever hide an element to animate it back — that is the
     regression this project fixed at v3.1.1, where a reduced-motion visitor got
     `opacity: 0` with nothing left to animate. */
  @media (prefers-reduced-motion: no-preference) {
    @keyframes nzPreviewFlash {
      from { box-shadow: 0 0 0 2px var(--pz-accent, var(--nz-blue-400, #0065AB)); }
      to   { box-shadow: 0 0 0 2px transparent; }
    }
    #nz-brandpage .nz-prevframe.nz-flash { animation: nzPreviewFlash 600ms ease-out 1; }
  }
```

and this to the JS, immediately after the `markInvalidFields();` call in the wiring block:

```js
    // A save redirects back to this page with msg=saved, and the observer moves
    // the confirmation into the slot under the header. Flash the panes once so
    // the preview column is part of the confirmation rather than a bystander to
    // it. Fire-and-forget: the class is removed after the animation's own
    // duration, and under reduced motion the rule does not exist so the class is
    // inert rather than a state something has to undo.
    if (document.getElementById('OKMsg')) {
      var panes = document.querySelectorAll('.nz-prevframe');
      for (var pi = 0; pi < panes.length; pi++) panes[pi].className += ' nz-flash';
      setTimeout(function () {
        var els = document.querySelectorAll('.nz-prevframe.nz-flash');
        for (var i = 0; i < els.length; i++) {
          els[i].className = els[i].className.replace(/\s*\bnz-flash\b/, '');
        }
      }, 700);
    }
```

- [ ] **Step 4: Verify what can be verified without a panel**

Run:
```bash
php -l interface/web/customizer/customizer_edit.php
php tests/brand/run.php
python3 - <<'PY'
import re
src = open('interface/web/customizer/templates/customizer_edit.htm').read()
js = src.split('<script>')[1].split('</script>')[0]
# The three things that must be carried over untouched.
for needle in ['function wireUpload(', 'function wireRemove(', 'new MutationObserver(',
               "fetch('customizer/logo_upload.php'", "fd.append('_csrf_id'", "fd.append('_csrf_key'"]:
    print(('OK  ' if needle in js else 'MISSING '), needle)
# ES5 only, matching the existing block.
for bad in ['=>', 'const ', 'let ', '`']:
    print(('OK  no ' if bad not in js else 'FOUND '), repr(bad))
print('preview.php called:', "fetch('customizer/preview.php'" in js)
print('no CSRF mint on the preview path:', js.count('logo_upload.php') == 2)
PY
node --check <(sed -n '/<script>/,/<\/script>/p' interface/web/customizer/templates/customizer_edit.htm | sed '1d;$d') 2>&1 | tail -1 || echo "node not available — skip"
```
Expected: no PHP syntax errors; `brand suite passed`; every carried-over needle `OK`; no `=>`, `const `, `let ` or backtick found; `preview.php called: True`; `no CSRF mint on the preview path: True`. The `node --check` line is opportunistic — if `node` is not installed, the check is skipped and the JS is verified in the browser instead.

Panel-only: type in each colour field and watch the panes and the ratio readouts change without a round trip; change a reference path and watch the marks refresh after the debounce; submit a bad hex and confirm the banner appears *and* the field is outlined with the message beneath it; upload and remove a logo and confirm all three preview rows still refresh; disable JavaScript and confirm the form still saves.

- [ ] **Step 5: Commit**

```bash
git add interface/web/customizer/customizer_edit.php \
        interface/web/customizer/templates/customizer_edit.htm
git commit -m "Paint the Branding preview as you type, and mark the field a validation error belongs to"
```

---

## Task 9: Documentation

**Files:**
- Modify: `README.md` (the brand-key table and the colour paragraph)
- Modify: `UPGRADING.md` (what a new key means for an existing panel)
- Modify: `CONTRIBUTING.md` (the developing-and-testing checklist)

**Interfaces:** none — this is the last task and nothing consumes it.

- [ ] **Step 1: README — the brand-key table**

In `README.md`, in the "Needs a brand-aware design" table, add a row directly after the line beginning `| `rail_hex` | the main navigation band` (`grep -n '| \`rail_hex\` |' README.md` finds it):

```markdown
| `rail_hex_light` | the same band in **light** colour mode, for designs that have one. Unset, `rail_hex` is used in both modes — which is what every panel does today, so this changes nothing until you set it. A design with a single colour mode (classic, and phosphor) reads the key and does nothing with it |
```

and add a paragraph immediately after the paragraph that ends `…and that surface is pinned to the mark you name, independently of the other.` (`grep -n 'independently of the other' README.md`):

```markdown
**A light-mode sidebar changes which logo the navigation shows, and the page
tells you.** On a design with a light colour mode, setting `rail_hex_light` gives
the navigation bar two backgrounds instead of one — and a mark that reads on a
navy rail is the mark that disappears on a near-white one. Left on **Automatic**,
each colour mode gets the variant that reads on *its* background, exactly as the
login screen already does; the preview draws one swatch per background so you can
see both before you commit. An explicit `logo_variant_nav` is still obeyed in
both modes, because the escape hatch is absolute by design.
```

- [ ] **Step 2: README — the Branding page description**

In `README.md`, under the `### The Branding page` heading, insert this paragraph immediately after the line `and Portuguese.` (`grep -n 'and Portuguese.' README.md`) and before the `**Some of it works with no design installed at all**` paragraph:

```markdown
The page itself is two columns on a wide screen — the settings on the left, a
live preview on the right showing the navigation bar, the login screen and the
tab icon — and one column below about 1000px, with the preview above the fields.
Colours and the panel name update as you type; which logo each surface ends up
with is worked out by the server, using the same code the panel itself uses, so
the preview cannot promise a mark the panel will not render.
```

- [ ] **Step 3: UPGRADING.md**

In `UPGRADING.md`, in the "Upgrading this extension" section, after the paragraph about re-running `install.sh`, add:

```markdown
### The `rail_hex_light` key

This release adds one branding key, `rail_hex_light` — the sidebar colour in
light colour mode. **Nothing changes on an existing panel until you set it:**
unset, every design keeps painting the sidebar from `rail_hex` in both modes,
which is what they have always done. There is no migration and no new column;
like every other branding value it lives in the `[branding]` section of
`sys_ini.config` and is written by the Branding page.

Downgrading is equally uneventful. An older design's `brand.php` does not read
the key, so it is simply ignored; the value stays in the config blob and comes
back if you upgrade again. `bin/purge_branding.php` removes it with the rest of
the section.

If you maintain your own design, add `rail_hex_light` to its `brand.php`: paint
your light scope's rail with it where you have one, or read it and document the
no-op where you do not. CI's **Brand-token contract parity** step walks every
`themes/*/brand.php` and now requires the key in all of them.
```

- [ ] **Step 4: CONTRIBUTING.md — what to check when this page changes**

In `CONTRIBUTING.md`, in "Developing and testing a change", after the paragraph beginning "On clarity, check every visual change in **dark and light mode**", add:

```markdown
The Branding page is the one page in this repository with a layout of its own, so
it needs checking at three widths — 1440, 1280 and 1024 — under **each installed
design**, and in both colour modes on clarity. It has no stylesheet: every colour
in its inline `<style>` is `var(--pz-…, var(--nz-…, <stock fallback>))`, so a rule
that names a colour directly will look correct under the design you wrote it for
and wrong under the other two. Its live preview needs `customizer/preview.php`
reachable; with JavaScript off the page must still save, which is the check that
proves the preview stayed an enhancement.
```

- [ ] **Step 5: Verify**

Run:
```bash
grep -n 'rail_hex_light' README.md UPGRADING.md CONTRIBUTING.md SECURITY.md .github/workflows/ci.yml | wc -l
php tests/brand/run.php
php .github/scripts/lang_check.php
find themes interface bin .github -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
find interface -name '*.lng' -print0 | while IFS= read -r -d '' f; do php -l "$f" >/dev/null || echo "LINT FAIL $f"; done
bash -n install.sh && bash -n uninstall.sh
```
Expected: the grep count is at least 6; `brand suite passed`; `language files OK`; no `LINT FAIL` lines; the shell check is silent.

Still CI-only after this task: `php tests/svg/run.php` (needs `ext/dom`, which the local PHP does not have — it prints `ext/dom is required to run this corpus`), the cache-buster, dashlet-override, favicon-contract, classic-asset and installer-flag steps.

- [ ] **Step 6: Commit**

```bash
git add README.md UPGRADING.md CONTRIBUTING.md
git commit -m "Document rail_hex_light, the redesigned Branding page and what to check when it changes"
```

---

## Verification summary

| Check | Command | Where it runs |
|---|---|---|
| PHP syntax, all sources | `find themes interface bin .github -name '*.php' -print0 \| while IFS= read -r -d '' f; do php -l "$f"; done` | local + CI |
| PHP syntax, language files | same over `-name '*.lng'` | local + CI |
| Wordbook key parity, nav-title budget | `php .github/scripts/lang_check.php` | local + CI |
| Brand readers: rail ink, surfaces, resolver parity, rail-ink parity, tform validators, preview endpoint shape | `php tests/brand/run.php` | local + CI |
| SVG upload screen corpus | `php tests/svg/run.php` | **CI only** — needs `ext/dom` |
| Cache-buster, dashlet overrides, favicon contract, brand-token contract, classic asset ban, installer flags | the `ci.yml` steps | **CI only** |
| The page under each design, at 1440/1280/1024, in both clarity modes; save, upload, remove, the live preview, JS-off | by hand | **test panel only** |

Nothing in this plan can be proven by running the panel locally: the local PHP has no `mysqli`, so every endpoint that reads `sys_ini` is untestable here by design. That is why the endpoint's decisions live in `lib/preview.inc.php` and the endpoint file itself is asserted structurally.
