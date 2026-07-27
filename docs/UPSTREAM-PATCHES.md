# Upstream contributions for ISPConfig core

Three core issues surfaced while building the Clarity theme and Customizer module
against ISPConfig 3.3 (dev/3.3.1p1). None are theme/module bugs — they're in core
— so rather than work around them silently we're offering them back to the
project. Each was located and quoted against the real 3.3 source and then
independently re-verified; the candidate patches below are **starting points**,
not drop-in merge requests: regenerate the final diffs with `git diff` against a
fresh checkout (so hunk headers are real) and test before submitting to
`git.ispconfig.org`.

Framing note for submission: lead with the analysis, offer the patch as a
candidate, and defer design calls (especially #3) to the maintainers. These are
contributions in good faith, not criticism.

Verification status:
- **#1 SVG logo sizing** — verified verbatim; candidate patch ready.
- **#2 dead logo setter** — verified; needs a new endpoint file, so it's an
  approach + artifacts, not a one-hunk diff.
- **#3 session locking / CSRF race** — verified; design-sensitive, presented as
  analysis + a candidate direction with a lower-risk alternative.

---

## 1. Unguarded `getimagesizefromstring()` on the panel logo → E_WARNING + broken sizing (SVG / unparseable logos)

**Affected (identical 4-line pattern, quoted verbatim):**
`interface/web/login/index.php:505-508`, `interface/web/index.php:105-108`,
`interface/web/login/force_password_change.php:145-148`,
`interface/web/login/password_reset.php:181-184`. Wrapper:
`interface/lib/classes/functions.inc.php:461-468` (returns the raw
`getimagesize` result with no guard). `otp.php` is **not** affected (it never
sizes the logo).

```php
$tmp_base64 = explode(',', $base64_logo_txt, 2);
$logo_dimensions = $app->functions->getimagesizefromstring(base64_decode($tmp_base64[1]));
$app->tpl->setVar('base64_logo_width', $logo_dimensions[0].'px');
$app->tpl->setVar('base64_logo_height', $logo_dimensions[1].'px');
```

**Root cause:** `getimagesize`/`getimagesizefromstring` returns `bool(false)` for
anything GD can't measure — an SVG logo, a corrupt/truncated blob, or a value
with no comma (so `$tmp_base64[1]` is unset and `base64_decode(null)` is empty).
Indexing `false[0]`/`false[1]` throws *“Trying to access array offset on value of
type bool”* (E_WARNING, two per render on PHP 7.4+) and sets the template vars to
the literal string `"px"`, i.e. invalid `width:px;height:px` CSS. This fires on
**every** login render (and the authenticated main page) whenever the stored
logo is an SVG.

**Candidate patch** (per call site; guards the result and the missing-`[1]`
case, leaves the raster path byte-identical). Indentation is tabs except
`force_password_change.php` which uses 4 spaces:

```diff
 $tmp_base64 = explode(',', $base64_logo_txt, 2);
-$logo_dimensions = $app->functions->getimagesizefromstring(base64_decode($tmp_base64[1]));
-$app->tpl->setVar('base64_logo_width', $logo_dimensions[0].'px');
-$app->tpl->setVar('base64_logo_height', $logo_dimensions[1].'px');
+$logo_dimensions = isset($tmp_base64[1]) ? $app->functions->getimagesizefromstring(base64_decode($tmp_base64[1])) : false;
+if (is_array($logo_dimensions)) {
+	$app->tpl->setVar('base64_logo_width', $logo_dimensions[0].'px');
+	$app->tpl->setVar('base64_logo_height', $logo_dimensions[1].'px');
+} else {
+	$app->tpl->setVar('base64_logo_width', 'auto');
+	$app->tpl->setVar('base64_logo_height', 'auto');
+}
```

**Honest caveat:** on the authenticated main page the logo is a *background image*
on an empty `<div id='logo'>`, where `height:auto` can collapse the box to 0. So
`auto` doesn't reliably *display* an SVG there — but the previous `width:px` was
already invalid CSS that browsers drop, so this is a **robustness fix** (kills the
warnings + invalid CSS), not a rendering restoration. A maintainer may prefer to
omit the width/height entirely for the background-div case. On the login pages
(a real `<img>`) `auto` sizes correctly.

**Risk:** very low; raster logos are unchanged.

---

## 2. The custom panel-logo **setter** is dead — admins can't set the logo the render path already supports

**Affected:** read/render path is fully live (`interface/web/index.php:98-109`
plus every login-flow page reads `sys_ini.custom_logo`). The **setter** is dead
in four places at once:

1. `interface/web/admin/system_config_edit.php:211-222` — the server-side writer
   is inside a `/* … */` block.
2. `interface/web/admin/templates/system_config_misc_edit.htm:1-9` — the
   file-input + upload button UI is inside an HTML comment.
3. `interface/web/admin/templates/system_config_misc_edit.htm:174-240` — the
   upload/delete JS is inside an HTML comment, and it POSTs to…
4. `interface/web/admin/ajax_get_json.php` — which **doesn't exist** (only
   `sites/`, `mail/`, `dashboard/`, `dns/` have one).

**Root cause:** the feature was never fully shipped. Re-enabling the inline
writer alone would **not** work: the main config form submits via ISPConfig's
`submitForm()` → jQuery `.serialize()` (`ispconfig.js:164`), which drops file
inputs, so `$_FILES` is always empty on the normal Save. That's exactly why the
original authors routed the upload through a separate `FormData` POST to a
dedicated `ajax_get_json.php` — the piece that's missing. (Corrected from an
earlier draft: a stock admin sees **nothing** in the Misc tab — no upload field
*and* no delete button — because the `{tmpl_var name='used_logo'}` slot is inside
the commented block too.)

**Fix approach** (larger than one hunk — needs a new endpoint + un-commenting):
- **Create `interface/web/admin/ajax_get_json.php`** following the existing
  `sites/ajax_get_json.php` conventions: admin-typ + module-permission +
  `demo_mode` guards, validate the upload with the same `getimagesizefromstring`
  the render path uses (allow PNG/JPEG/GIF, bound dimensions and byte size),
  store as a `data:` URI in `sys_ini.custom_logo`, and a `delcustomlogo` branch
  that blanks it. (This also fixes a latent MIME bug in the old commented writer,
  which did `pathinfo($tmp_name)` on an extension-less temp file →
  `data:image/;base64,`.)
- **Un-comment** the UI (htm:1-9) and JS (htm:174-240); **delete** the now-dead
  inline writer block (`system_config_edit.php:211-222`).
- **Add the missing lang keys** (`logo_txt`, `used_logo_txt`, `upload_txt`) to
  `en_system_config.lng` — they're genuinely absent.

A full candidate endpoint (~45 lines) and the exact un-comment hunks are prepared
and can accompany the MR. (Interested parties can also just use the Customizer
module, which ships a complete, hardened working uploader for this same field —
but core deserves the fix too.)

**Risk:** medium; adds a new admin-only endpoint. Scope it to admins + demo-mode
guard + strict image validation (as above).

---

## 3. DB session handler has no per-session locking → intermittent “CSRF attempt blocked”

**This one is design-sensitive — presented as analysis + a candidate direction,
deferring the call to the maintainers.**

**Affected:** `interface/lib/classes/session.inc.php` — `open()` (57-59) and
`close()` (61-68) do no locking; `read()` (70-84) is a bare `SELECT`; `write()`
(86-110) is an unconditional `UPDATE`/`REPLACE INTO sys_session` of the whole
serialized `session_data`. Handler registered at `interface/lib/app.inc.php:126`.
CSRF tokens live in `$_SESSION` (`auth.inc.php` `csrf_token_get` 305-316 /
`csrf_token_check` err at 343) and every tform render mints one
(`tform_base.inc.php:452`). Concurrent session-writing proof:
`interface/web/capp.php:62-63` (`$_SESSION["s"]["module"] = $module;
session_write_close();`).

**Root cause:** the custom `SessionHandlerInterface` provides none of the
per-session mutual exclusion PHP's native *files* handler gives for free (which
serializes concurrent requests on the same session). So overlapping requests are
last-writer-wins on the whole `session_data` column. Because a fresh single-use
CSRF token is written into `$_SESSION` on render, a concurrent session-mutating
request that read *before* the render and commits *after* it silently erases the
token → the next POST fails `csrf_token_check` → forced re-login. This is the
well-known intermittent “CSRF attempt blocked.”

Important nuance (makes the report precise): the `write()` guard at lines 92-96
means strictly **read-only** requests take a harmless “update `last_updated`
only” branch and do **not** clobber. So a pure `datalogstatus` poll isn't the
culprit — the clobberers are requests that *mutate* the session concurrently
with a render (a `capp.php` module switch, or two overlapping form renders, since
each render itself mutates `$_SESSION['_csrf']`).

**Candidate direction (needs maintainer review + load testing):** mirror the
native handler by taking a MySQL advisory lock per session — `GET_LOCK` in
`read()`, `RELEASE_LOCK` in `close()`, idempotent, **fail-soft** on lock-wait
timeout (degrade to today's behaviour rather than a hard 500). A ~30-line
candidate exists.

**Why this is a candidate, not a patch** — the open questions the maintainers
own: the AJAX pollers (`datalogstatus`, `keepalive`, `capp`, `nav`) interact with
any session-wide lock (lock-wait latency, stuck locks on dead workers, `GET_LOCK`
being per-connection so a mid-request reconnect voids it, the server-wide
`GET_LOCK` namespace). **A lower-risk alternative worth leading with:** make just
the CSRF store concurrency-safe — merge rather than overwrite `$_SESSION['_csrf']`
on write, or move tokens to an INSERT-only table — sidestepping session-wide
locking entirely. We'd rather surface the analysis and let the maintainers pick
the shape.

**Risk:** high (session locking is high-blast-radius); hence the fail-soft
candidate + the lower-risk alternative + explicit deferral.

---

## 4. Saving any config tab silently strips one backslash level from every value it does **not** edit

**Affected — two readers and seven writers, all of one pattern:**

Readers (both apply `stripslashes()` to the stored blob):
`interface/lib/classes/getconf.inc.php:43` (`get_server_config`) and
`:54` (`get_global_config`).

Writers (all read via `getconf`, replace one section, and write the whole blob
back through `ini_parser::get_ini_string()`):
`interface/web/admin/system_config_edit.php:143,186`,
`interface/web/admin/server_config_edit.php:171`,
`interface/web/client/client_edit.php:393`,
`interface/web/client/reseller_edit.php:318`,
`interface/web/mail/spamfilter_config_edit.php:84`,
`interface/lib/classes/remote.d/admin.inc.php:123`,
`interface/lib/classes/remote.d/server.inc.php:165`.

**Root cause — an asymmetry, not a missing escape.** The stored blob's invariant
is *escaped*, and three pieces have to agree on that:

- `tform_base::_encode()` line 912 applies `$app->db->quote()` to every field
  when `$dbencode` is true, so the section being **edited** is written escaped.
  Correct.
- `getconf` unescapes the **whole** blob on read (`parse_ini_string(stripslashes(...))`).
- `ini_parser::get_ini_string()` writes values verbatim, and
  `db::datalogUpdate()` binds the blob as a query parameter, so **nothing
  re-applies the escaping** on the way out.

So the edited section round-trips correctly, while every *other* section is read
unescaped and written back unescaped — losing one backslash level per save. The
next read strips again. Each further save of any tab eats another level.

**Reproduction** (models the code path exactly; no database required):

```
1. Save System > Interface Config > Mail with smtp_pass = pa\ss
     column holds: smtp_pass=pa\\ss        reads back as pa\ss    correct

2. Now save the Misc tab three times (company name, nothing else)
     save #1 -> smtp_pass reads back as: pass
     save #2 -> smtp_pass reads back as: pass
     save #3 -> smtp_pass reads back as: pass
```

The password is destroyed on the **first** unrelated save. Outbound mail
authentication then fails with nothing in the UI to explain it, because the Mail
tab still displays the value it thinks it stored.

**Impact.** Any config value containing a backslash, in any section, corrupted
by editing an unrelated tab. `smtp_pass` is the most damaging instance because
the failure is silent and remote. The two `remote.d` call sites make it worse in
practice: provisioning automation saves these repeatedly, so a value can lose
several levels in one run.

**Candidate patch** — parse the raw column in the write path rather than the
unescaped one. Pass-through sections then stay in their stored (escaped) form,
the edited section is already escaped by `tform`, and the whole array is
consistently escaped, so the existing `stripslashes()` on read stays correct.
Shown for `system_config_edit.php`; the other six sites take the same shape:

```diff
-		$server_config_array = $app->getconf->get_global_config();
+		// Parse the RAW column, not the stripslashes'd one: get_ini_string()
+		// does not re-escape and datalogUpdate() binds the blob, so anything
+		// read through getconf here would be written back one escaping level
+		// short. Sections we are not editing must pass through untouched.
+		$tmp_ini = $app->db->queryOneRecord('SELECT config FROM sys_ini WHERE sysini_id = 1');
+		$server_config_array = $app->ini_parser->parse_ini_string($tmp_ini['config']);
```

Verified: with this change the same three saves leave `smtp_pass` as `pa\ss`,
and the edited value still round-trips correctly.

**Alternative worth considering instead** — make the asymmetry impossible rather
than fixing seven call sites: have `get_ini_string()` escape, or add an explicit
`getconf::get_global_config_raw()` that the write paths use. That is a larger
design call and belongs to the maintainers; the per-site patch above is offered
because it is minimal and provable.

**Risk:** low per site, but it should land on all seven together — a partial fix
would leave the blob half-escaped depending on which screen was last saved. Worth
a migration thought too: existing installs may already hold under-escaped values
from previous saves, and this patch preserves whatever is there rather than
repairing it.

**Honest caveat:** we could not date the `stripslashes()` calls — our reference
checkout is squashed to a single commit, so `git blame` attributes everything to
one merge. They look like a magic-quotes-era vestige, but that is a guess and we
have not verified it.

---

## Suggested submission order

1. **#1 SVG guard** — smallest, safest, obviously correct; a good first MR.
2. **#4 config escaping** — small diff, reproducible in two steps, and it
   silently corrupts mail credentials today. Arguably the most valuable of the
   four despite being found last.
3. **#2 logo setter** — completes a half-shipped feature; medium size.
4. **#3 session locking** — open as a discussion issue first (with the analysis
   and both fix options) rather than a patch; let the maintainers steer.
