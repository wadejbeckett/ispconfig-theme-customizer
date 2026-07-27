# Security Policy

This is one extension: a brandable front-end for ISPConfig, with an admin-only
page where you set your logo, panel name and colours. It installs into the panel
in two places, and both are in scope here:

- `themes/clarity/` and `themes/classic/` — the two designs it ships, each with
  two endpoints that are reachable **without a session** (`brand.php`,
  `title.php`), so four in total;
- `interface/web/customizer/` — the **Branding** page. That page is an ISPConfig
  *module* in core's sense of the word (`lib/module.conf.php`,
  `sys_user.modules`), and it writes the values those endpoints read.

One policy covers both halves, because they are one contract: every `brand.php`
reads exactly the `sys_ini` keys `customizer_edit.php` writes.

## Reporting a vulnerability

Report security issues **privately** — use GitHub's private vulnerability
reporting on this repository (**Security → Report a vulnerability**), not a
public issue or pull request. You will get an acknowledgement and either a fix
or an assessment; coordinated disclosure is welcome.

If a report turns out to be an ISPConfig **core** issue rather than one in this
repository, it is redirected upstream to the ISPConfig project with credit.

## Supported versions

The latest tagged release receives fixes.

Developed and verified against **ISPConfig 3.3.1p1**. ISPConfig **3.2 is
untested** — it is neither claimed nor supported here.

From **v3.0.0** there is a single version number and a single tag. Before that,
the design and the Branding page were separate repositories with independent
versions, and nothing enforced that the halves matched: a mismatched pair
(branding v1.0.12 against design v2.1.0, say) could leave a written branding key
with no reader — the accent colour simply did not apply, with no error anywhere.
A matched pair is now the only thing you can install.

## Trust boundary: the Branding page is admin-only by construction

Every endpoint under `interface/web/customizer/` (`customizer_edit.php`,
`logo_upload.php`, `logo_delete.php`) opens with the same three checks, in this
order:

```php
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');
```

1. `check_module_permissions()` requires `customizer` to be present in the
   user's `sys_user.modules` CSV.
2. `check_security_permissions('admin_allow_system_config')` reads the same
   security setting ISPConfig uses to gate **System → Interface Config**. It
   ships as `superadmin`, which core resolves as `typ = 'admin'` **and**
   `userid = 1`.
3. `is_admin()` requires `typ = 'admin'` regardless of what that security
   setting is relaxed to.

**No automated path grants `customizer` to a non-admin.** Core builds a new
user's module list from `$conf['interface_modules_enabled']`, which ships as
`dashboard,mail,sites,dns,tools,help`:

- `client_edit.php` and `reseller_edit.php` set `sys_user.modules` from that
  constant (plus `client` for resellers), so a client or reseller never receives
  `customizer`;
- the **remote API** intersects any caller-supplied `modules` parameter with the
  same constant and silently drops anything absent from it
  (`remoting_lib.inc.php`, on both the sysuser add and the update path), so
  `client_add` / `client_update` cannot inject it either;
- this repository's own `bin/assign_module.php` selects `WHERE typ = 'admin'`
  and touches nothing else.

The one remaining way `customizer` can land on a non-admin account is an
administrator ticking it by hand in **System → CP Users** — core's CP-user form
lists every directory under `interface/web/` that has a `lib/module.conf.php`,
and cannot know this one is admin-only. That is worth stating plainly rather
than claiming an absolute. Even then check 3 refuses the request, so the
consequence is a visible-but-dead nav entry, not access.

Clients and resellers only ever see the *result* of branding, never the Branding
page.

## What the Branding page validates, and where

Validation happens on write (the form's filters and validators) **and again on
read** (each design's `brand.php` re-checks every value before it reaches CSS).
The
reader-side pass is defence in depth, not the boundary: it exists so that a
value written by some other means — the remote `system_config_set` call, a
direct `UPDATE`, a restored backup — is still checked at render time.

**Colours** (`accent_hex`, `rail_hex`, `login_bg`) — anchored hex regex
`/^(#[0-9A-Fa-f]{6})?$/` on write, `/^#[0-9A-Fa-f]{6}$/` on read. Anything else
is treated as unset. Values are normalised (leading `#` added, upper-cased)
before validation, so a pasted `0065ab` is accepted rather than rejected
opaquely.

**`logo_url`** — the logo by reference, consumed inside a CSS `url("…")`. Only a
root-relative path or an `https://` URL, and no character that could break out
of that context:

```
/^(https:\/\/[^\s"'<>()\\]+|\/(?!\/)[^\s"'<>()\\]+)?$/D
```

Two details are load-bearing. The `(?!\/)` lookahead rejects protocol-relative
`//host/…`, which browsers treat as remote. The **`/D` modifier** makes `$` mean
true end-of-subject; without it PCRE also matches *before* a final newline, so
`/img/logo.png\n` would validate and the raw LF would be emitted inside
`content: url("…")` — a literal newline terminates a double-quoted CSS string,
which would break the stylesheet for every visitor, including pre-auth on the
login screen. Both designs' `brand.php` carry that pattern character for
character, `/D` included, for the same reason. The field is filtered with `TRIM` only, deliberately not `STRIPNL`: a
filter that spliced an embedded newline out of the *middle* of the value would
hand the validator a string the administrator never typed.

**`custom_login_link`** — core renders this **unescaped** inside `<a href="…">`
on the pre-auth login page (`login/index.php`), so the validator is anchored and
forbids quotes, whitespace and angle brackets:
`/^(https?:\/\/[^\s"'<>]+)?$/`.

**Free text** (`company_name`, `custom_login_text`) — `STRIPTAGS` + `STRIPNL` on
save. The active design normalises again on read (control characters stripped,
by the same byte-wise filter in all four endpoints, so the CSS wordmark and the
tab title can never derive different strings from the same row) and escapes per
output context: a CSS-string escape in `brand.php`, `json_encode` with the HEX
flags in `title.php`.

**Toggles** — strict `0|1`; anything else reads as the default, which is always
the attribution-preserving value.

**Uploaded rasters** — the `finfo` MIME type must be one of `image/png`,
`image/jpeg`, `image/gif`, `image/webp`, and the raw file must be ≤ 45,000 bytes
so its base64 form fits the `sys_ini.custom_logo` column.

## The SVG screen

An SVG is XML with executable affordances — `<script>`, event-handler
attributes, SMIL animation, `<foreignObject>` embedding arbitrary HTML — so
unlike a PNG it cannot simply be believed. `finfo` is also unreliable here: it
mislabels prolog-less SVGs as `text/xml`, `text/plain`, even `text/html`. SVG
therefore never enters through the MIME allowlist; a texty verdict gets exactly
one chance to prove itself against `customizer_svg_ok()`.

**The screen parses the document; it does not scan bytes.** A regex blocklist
over the upload bytes does not hold, because the raw bytes are not the document:

- XML identity is (namespace, local name), not spelling. With `s` bound to the
  SVG namespace, `<s:script>` *is* the SVG script element, and `/<script/` never
  sees it.
- Character references are resolved by the parser, so `&#106;avascript:` is a
  `javascript:` URL that no byte scan for `javascript:` matches.
- CDATA sections and comments let text masquerade as markup and back.
- An event handler can hide in an attribute *value* rather than a name — SMIL
  `<set attributeName="onload" to="…">` defeats any "whitespace then `on…=`"
  pattern.

What actually runs, against the parsed tree:

- `<!ENTITY` is rejected by a byte scan **before** parsing (billion-laughs
  expansion, external-entity references). It is a single XML token that
  whitespace cannot split, so a byte scan is sufficient for this one case. A
  bare `<!DOCTYPE>` stays allowed — Inkscape and Illustrator both emit one.
- Parsing is `DOMDocument::loadXML()` with `LIBXML_NONET`, so no network
  retrieval happens during the parse. `LIBXML_NOENT` is deliberately **not**
  set: entity substitution must never run.
- If `ext/dom` is missing, SVG is **refused** rather than screened weakly.
  Raster formats are unaffected. CI installs `dom` and `xml` explicitly for this
  reason.
- The root element's local name must be `svg`, in the SVG namespace or in none
  (which is how a hand-written file with no `xmlns` parses).
- **No processing instruction may appear anywhere.** An `xml-stylesheet` PI
  makes the renderer fetch a remote stylesheet, and it can sit outside the root
  element where an element walk would never reach it.
- Elements are screened **on local name**, lower-cased, over a
  namespace-agnostic XPath `//*` — so a namespace prefix cannot smuggle
  anything. In the SVG namespace (or none) the rule is an **allowlist** of the
  vocabulary a logo needs: shapes, gradients, text, filters, SVG fonts,
  Inkscape flowed text. Anything outside it is refused by omission rather than
  by having to be enumerated. Foreign-namespace elements are tolerated, because
  real editor output is full of them (`sodipodi:namedview`, `inkscape:*`,
  `rdf:RDF`, Adobe XMP), but they still go through the attribute screen — and a
  second **denylist** rejects executable or embedding local names (`script`,
  `foreignobject`, `iframe`, `object`, `a`, `set`, `animate`, …) in **any**
  namespace.
- Attributes: any local name beginning `on` is rejected outright. `href` and
  `src` must be a same-document reference (`#…`) or a
  `data:image/(png|jpeg|gif|webp);base64,` payload. **Every** attribute value
  additionally goes through the CSS screen, which covers `style=""` and every
  funcIRI attribute (`fill="url(…)"`, `filter="url(…)"`) in one pass. `<style>`
  element text goes through the same screen, CDATA-wrapped or not.
- The CSS screen unwinds CSS comments, HTML entities and backslash hex escapes
  before matching, then rejects `@import`, `expression(`, `javascript:`,
  `vbscript:`, `-moz-binding` and `behavior:`, and screens every `url()` target.
  It **fails closed**: a PCRE backtrack or recursion limit, input that is not
  valid UTF-8, and a `url(` occurrence count that disagrees with the number of
  matches all return "not OK" rather than falling through.

This is verified against **41 cases — 29 bypass attempts, all blocked, and 12
real-world logos** (Inkscape/RDF namespaces, filter chains, embedded data URIs),
all still accepted. That corpus is shipped, as `tests/svg/run.php`, and CI runs
it on **every push** (`.github/workflows/ci.yml`, "SVG upload screen —
adversarial corpus"); the run exits non-zero if any bypass attempt passes *or*
any real logo is refused, so the numbers above cannot go stale without the build
going red. You can run it yourself with `php tests/svg/run.php`.

The honest framing: this endpoint is admin-only, and every place the logo
renders — on either design — is an image context (an `<img>` tag, or CSS
`content: url()` / `background-image`), where browsers apply SVG secure-static
mode. The screen is **defence in depth**, not
the last line of defence. It is written to be exactly as strict as this document
says it is.

## Other controls

- **Upload CSRF.** ISPConfig's DB session store does not lock — read is a
  `SELECT`, write is a whole-row `REPLACE`, last writer wins — so a single-use
  CSRF token minted during a page render can be silently erased by any
  concurrent session-writing request in the page-load burst. This is upstream
  core behaviour, and the cause of the intermittent "CSRF attempt blocked" that
  stock ISPConfig forms also exhibit. The uploader therefore mints a **fresh
  token at click time**, from a lone same-origin request gated on
  `X-Requested-With: XMLHttpRequest` (a header that cannot be attached
  cross-origin without a CORS preflight), shrinking the race window from minutes
  to milliseconds. The upload POST itself is checked with
  `csrf_token_check('POST')`.
- **`logo_delete.php`** requires the same `X-Requested-With` header *and*
  `csrf_token_check('GET')`, matching core's own delete flow. The header check
  alone would be defence in depth; the token check is the control.
- **Demo mode** (`$conf['demo_mode']`) refuses before any write, and refuses
  *visibly* — in `onBeforeUpdate` rather than `onUpdateSave`, because the
  framework tests `errorMessage` before calling the save hook and its redirect
  is unconditional, so a refusal raised any later would print "Settings saved."
  over values that were never written.
- **Symlink installs refuse to serve your working tree.** A symlinked install is
  served *through* the link, so `install.sh` aborts if the source directory
  contains `.git`, `.omc` or `node_modules` rather than exposing them over HTTP;
  `--copy` excludes them instead.

## The pre-auth surface

There are **four** of these, not two: `brand.php` (CSS) and `title.php` (JS)
under `themes/clarity/`, and the same pair under `themes/classic/`. Each design
links its own two from **both** of its shell templates, including the login
shell, and only one design is active per request — but once both are installed
all four exist on disk in the web root and all four are reachable by URL. They
run with no session and must be safe for anonymous requests. All four are
written for that, and the read half — query, unescape, normalise, cache — is
deliberately the same code in both designs, so the two cannot drift apart:

- **No application bootstrap.** None of them starts a session, loads
  `app.inc.php`, or triggers maintenance-mode redirects. Each opens a direct `mysqli`
  connection with the credentials already in `interface/lib/config.inc.php` and
  issues a **single read-only** `SELECT` against `sys_ini` row 1 — `config` and
  `custom_logo` for `brand.php`, `config` alone for `title.php`.
- **Always valid output.** `brand.php` always returns HTTP 200 with
  `Content-Type: text/css` (or 304 on an ETag match); `title.php` always returns
  HTTP 200 with valid JavaScript. All four re-assert their MIME type after
  including `config.inc.php`, which emits `text/html` on a web request.
- **Degrade to a no-op on DB failure.** Any connection or query fault is caught
  and produces an empty stylesheet / a no-op script with `Cache-Control:
  no-store` — never an error message, never a stack trace, and never a cached
  failure that would blank a host's branding for a whole max-age window after
  the database has recovered. `title.php` treats `json_encode` returning `false`
  (invalid UTF-8 in the stored name) the same way, because a classic script that
  fails to parse runs *nothing*, losing even the statements before it.
- **Every value is validated or escaped before output.** Colours via the hex
  regex; `logo_url` via the anchored allowlist above; the uploaded logo via
  `#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i`; toggles as strict
  `0|1`. The text wordmark has its control characters stripped on read — those
  cannot be represented, since a raw CR/LF terminates a CSS string — and is then
  **escaped, not deleted**, for the CSS string context: backslashes doubled
  first, then quotes. Printable characters including `<` and `>` survive
  intact, because `brand.php` is only ever fetched through
  `<link rel="stylesheet">` and never inlined into a `<style>` block, so there
  is no HTML context to break out of. A multibyte-safe 40-character cap applies
  to the visible wordmark only. In `title.php` the name reaches the page only
  through `json_encode` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS |
  JSON_HEX_QUOT`; `document.title` and the `<img alt>` text stay uncapped on
  purpose.
- **One request parameter exists, on one endpoint.**
  `themes/classic/brand.php` accepts `?scene=login`. A few rules apply to the
  login screen only, and classic cannot scope them with a body class the way
  Clarity does — its templates are generated from the panel's stock ones and the
  transform may not touch the markup, and stock's login `<body>` carries no
  class or id to hang a selector on — so `install.sh` adds that query string to
  the link in the generated login shell and the scope travels in the URL. The
  value is **only ever compared, never emitted**: `$_GET['scene'] === 'login'`
  selects the login scene and *anything else at all* selects the app scene.
  Because the comparison is `===` against a string, an array — `?scene[]=login`
  — is simply not equal and falls through to the app scene rather than raising a
  type error, and no form of the parameter reaches the response body. It does
  reach the `ETag`, deliberately: the two scenes are different URLs, and a
  validator that ignored the scene would let a stale revalidation cross them.
  The other three endpoints read no request input whatsoever.
- **No code execution surface.** Nothing user-controlled is `eval`'d or
  `include`'d. All four endpoints read and emit validated scalars.
- **Caching is `private`**, max-age 30 seconds, so branding never lands in a
  shared or reverse-proxy cache.

What these endpoints *do* disclose, by design, is the panel's configured
branding — accent colour, logo, panel name — to an unauthenticated caller. That
is the same information the login page renders to the same caller, so it is not
an escalation. One smaller observable: a healthy panel with no branding set and
a panel whose database is unreachable both emit an empty body, but their
response headers differ (`private, max-age=30` plus an ETag, versus `no-store`).
That distinction is deliberate — a failure must not be cached — and it is stated
here rather than left to be discovered.

## No core file is modified, and this is the whole write surface

Nothing here patches, replaces or edits any ISPConfig core file. The designs
live entirely under `themes/clarity/` and `themes/classic/` and override
templates and assets through ISPConfig's own theme loader; Clarity borrows the
stock theme's vendor CSS/JS by reference and never edits it, and classic ships
no assets at all — every stylesheet, script and icon on the page is served from
`themes/default/assets/` exactly as core left it. The Branding page lives
entirely under `interface/web/customizer/`. **Nothing under either design
directory writes at runtime** — all four endpoints only read.

`classic` is the one that comes close to core, so it is worth stating exactly.
Its two shell templates are **generated at install time from the target panel's
own** `themes/default/templates/main.tpl.htm` and `main_login.tpl.htm`.
`install.sh` **reads** those two files and writes nothing back — not to them,
not to anything else under `themes/default/`; the transformed copies are written
into `themes/classic/templates/`, which is why the repository contains no
`templates/` directory for classic. The transform is mechanical and bounded:
asset paths pinned to `themes/default/assets/`, the design's two brand endpoints
linked immediately before `</head>`, and the stock footer credit split into two
addressable spans so each credit toggle has a target. The installer then checks
its own output against the source — line count, no surviving `current_theme`
reference, both endpoints present — and aborts rather than deploy a shell it
cannot account for.

There are **no schema changes**: no new tables, no new columns, no `CREATE` or
`ALTER` anywhere in the repository. Every write is an `UPDATE` of a row and
column ISPConfig already has.

| What | When | Written by |
|---|---|---|
| `sys_ini.config` (row 1) — the `[branding]` section plus existing `[misc]` keys | saving the Branding form | `customizer_edit.php` |
| `sys_ini.custom_logo` (row 1) | logo upload / remove / purge | `logo_upload.php`, `logo_delete.php`, `bin/purge_branding.php` |
| `sys_user.modules` | install / uninstall | `bin/assign_module.php` (only `typ='admin'` rows), `bin/unassign_module.php` |
| `sys_user.startmodule` | uninstall | `bin/unassign_module.php`, and only where it pointed at `customizer` |
| `sys_user.app_theme` | uninstall with `--reset-users` | `bin/reset_app_theme.php`, and only rows equal to a design being removed (`clarity`, `classic`); the name is validated as a name, and `default` is refused outright |

Two qualifications, because a flat "no new rows" would not be true:

- The config write goes through core's own `datalogUpdate()`, which appends one
  row to the **`sys_datalog`** journal per changed save — exactly what happens
  when you save **System → Interface Config** yourself. The logo write is a
  direct `UPDATE` instead, deliberately: a 48 KB blob has no business in the
  journal.
- On disk, `install.sh` creates `themes/<design>/ispconfig_version` and
  `themes/<design>/ISPC_VERSION` inside the panel's web root, for each design it
  installs — and for classic it also writes that design's two generated shell
  templates, described above. See the next section: the version files are the one
  real exposure this project introduces.

The Branding page also *reads* far more of `sys_ini.config` than it writes, and
is careful with it: it parses the **raw** column rather than going through
`getconf::get_global_config()`, because that method applies `stripslashes()` on
read while nothing re-applies the escaping on write. A read-modify-write through
it would silently eat one backslash level from **every** value in the file on
**every** save, including sections it has no business touching — `[mail]
smtp_pass` among them, where `pa\ss` degrades to `pass` and outbound mail
authentication starts failing with nothing to explain it. Parsing raw means
every value it does not own is carried through byte-identical.

## Language files are PHP, and CI never executes them

The `.lng` wordbooks are PHP files, and they arrive through community
translation pull requests. `include()`ing one in CI would be arbitrary code
execution in the runner. CI therefore does two things, and neither runs the
file:

- `php -l` on every `.lng` — the linter parses, it does not execute;
- `.github/scripts/lang_check.php`, which checks key parity and the nav-title
  length budget using regex over `file_get_contents()`.

Both the Apache and the nginx panel vhosts ISPConfig ships already deny `\.lng$`
over HTTP, so the shipped wordbooks are not served either.

## Known exposure: each design directory's version file is readable without a session

**This one is real, it arrives with any third-party theme, and the `show_version`
toggle on the Branding page does not cover it.**

ISPConfig refuses to load a third-party theme unless the theme directory
contains a version file matching the panel exactly — `ispconfig_version` for the
login gate, `ISPC_VERSION` for the admin default-settings form. Core reads those
exact filenames from that exact location, so `install.sh` has to create them in
**every** design directory it installs. But a design directory lives inside the
panel's **web root**, so the web server serves them as ordinary static files:

```
$ curl -k https://panel.example.com:8080/themes/clarity/ispconfig_version
3.3.1p1
$ curl -k https://panel.example.com:8080/themes/classic/ispconfig_version
3.3.1p1
```

`classic` is no exception, despite being the stock look: it is still a
third-party theme directory as far as core's gate is concerned, so it carries
the same two files.

No session, no credentials, nothing unusual in the log. Anyone who can reach
your login page learns your exact ISPConfig version **and patch level** — which
is precisely the information needed to look up which known vulnerabilities apply
to your install.

**This is not stock behaviour.** ISPConfig's own `default` theme ships no
version file — the gate exists to validate *third-party* themes — so a stock
panel discloses nothing here. This was tested: on a stock panel
`/themes/default/ispconfig_version` returns 404 while
`/themes/clarity/ispconfig_version` returns 200. Installing any third-party
theme, either design here included, introduces the exposure.

It also **undercuts this project's own `show_version` toggle**, which hides the
version on the Help page while the same string stays readable one URL away.
Applying the mitigation is what makes that toggle honest.

The fix belongs at the web-server layer, because the filenames cannot be
changed. Ready-made Apache and nginx snippets, with the full explanation and a
verification command, are in **`contrib/webserver/`**. They match any theme
directory rather than a named one, so a single rule covers both designs — and
any other third-party theme you install. They also deny `BUILT-AGAINST.txt`,
which names the version (Clarity ships one; classic does not), and each design's
`README.md`, which is internal documentation with no reason to be public.
The snippets follow ISPConfig's existing idiom for this — its own vhosts already
deny dotfiles and `.lng` files the same way.

Re-check after upgrades: the ISPConfig updater can regenerate the panel vhost
when you let it reconfigure services, which drops the rule.

## Known upstream interactions (not defects in this project)

- The intermittent core **"CSRF attempt blocked"** caused by the lock-free
  session store. The uploader works around it; the real fix belongs in core and
  is being prepared for upstream.
- Under the **stock** ISPConfig theme an *uploaded* SVG logo renders at
  intrinsic size, because core measures logos with `getimagesizefromstring()`,
  which cannot read SVG. `classic` inherits that, and deliberately: it leaves the
  uploaded logo to core's own markup rather than racing it with a second code
  path. Clarity sizes via CSS and is unaffected, as is a logo set by `logo_url`
  on **either** design, which `brand.php` sizes itself. So prefer PNG or WebP for
  an uploaded logo on the stock theme or on classic. A guard for this belongs in
  core and is being prepared for upstream.

## Scope

**In scope:** anything in this repository that could let a non-admin reach the
Branding page; injection into the rendered panel or the login page (CSS, JS or
HTML) via any branding value that is read back; information disclosure on the
pre-auth endpoints; privilege escalation; corruption of `sys_ini`; and any way
anything shipped here could execute attacker-controlled input.

**Out of scope:** pre-existing ISPConfig core behaviour, which is reported
upstream instead; and the deliberate, documented ability of an administrator to
opt into hiding the optional courtesy credits and the Help version line (the
`show_version` toggle described above — and note that on its own it does not
cover the version file, which is why `contrib/webserver/` exists). Both credit
toggles work on **both** designs, since `install.sh` splits stock's footer while
generating classic's shell so each credit is individually hideable. Licence
notices are never removed, the update notice and the donate dashlet are left
exactly as core ships them, and every attribution toggle defaults to **on**. If
the markup a toggle targets is ever absent, the rule matches nothing and the
credit simply stays visible — the failure mode is "attribution shown".
