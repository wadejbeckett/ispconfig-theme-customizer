# Security Policy

The extension installs into the panel in two places, and both are in scope:

- `themes/clarity/` and `themes/classic/` — the two designs, each with three endpoints reachable **without a session** (`brand.php`, `title.php`, `favicon.php`), so six in total;
- `interface/web/customizer/` — the **Branding** page, an ISPConfig module in core's sense of the word, which writes the values those endpoints read.

One policy covers both halves, because every `brand.php` reads exactly the `sys_ini` keys `customizer_edit.php` writes.

## Reporting a vulnerability

Report security issues **privately**, using GitHub's private vulnerability reporting on this repository (**Security → Report a vulnerability**), not a public issue or pull request. You will get an acknowledgement and either a fix or an assessment; coordinated disclosure is welcome.

If a report turns out to be an ISPConfig core issue rather than one here, it is redirected upstream to the ISPConfig project with credit.

## Supported versions

The latest tagged release receives fixes. Developed and verified against **ISPConfig 3.3.1p1**; ISPConfig **3.2 is untested** and neither claimed nor supported.

## Trust boundary: the Branding page is admin-only by construction

Every endpoint under `interface/web/customizer/` (`customizer_edit.php`, `logo_upload.php`, `logo_delete.php`, `preview.php`) opens with the same three checks, in this order:

```php
$app->auth->check_module_permissions('customizer');
$app->auth->check_security_permissions('admin_allow_system_config');
if(!$app->auth->is_admin()) die('Allowed for administrators only.');
```

1. `check_module_permissions()` requires `customizer` in the user's `sys_user.modules` CSV.
2. `check_security_permissions('admin_allow_system_config')` reads the same security setting ISPConfig uses to gate **System → Interface Config**. It ships as `superadmin`, which core resolves as `typ = 'admin'` **and** `userid = 1`.
3. `is_admin()` requires `typ = 'admin'` regardless of what that security setting is relaxed to.

**No automated path grants `customizer` to a non-admin.** Core builds a new user's module list from `$conf['interface_modules_enabled']`, which ships as
`dashboard,mail,sites,dns,tools,help`:

- `client_edit.php` and `reseller_edit.php` set `sys_user.modules` from that constant (plus `client` for resellers), so a client or reseller never receives `customizer`;
- the **remote API** intersects any caller-supplied `modules` parameter with the same constant and silently drops anything absent from it (`remoting_lib.inc.php`, on both the sysuser add and the update path), so `client_add` and `client_update` cannot inject it either;
- this repository's own `bin/assign_module.php` selects `WHERE typ = 'admin'` and touches nothing else.

The one remaining way `customizer` can land on a non-admin account is an administrator ticking it by hand in **System → CP Users**: core's CP-user form lists every directory under `interface/web/` that has a `lib/module.conf.php`, and cannot know this one is admin-only. Even then check 3 refuses the request, so the consequence is a visible-but-dead nav entry, not access.

Clients and resellers only ever see the result of branding, never the page.

## What the Branding page validates, and where

Validation happens on write (the form's filters and validators) **and again on read** (each design's `brand.php` re-checks every value before it reaches CSS). The reader-side pass is defence in depth, not the boundary: it exists so that a value written by some other means — the remote `system_config_set` call, a direct `UPDATE`, a restored backup — is still checked at render time.

**Colours** (`accent_hex`, `rail_hex`, `rail_hex_light`, `login_bg`) — anchored hex regex `/^(#[0-9A-Fa-f]{6})?$/` on write, `/^#[0-9A-Fa-f]{6}$/` on read. Anything else is treated as unset. Values are normalised (leading `#` added, upper-cased) before validation, so a pasted `0065ab` is accepted rather than rejected opaquely.

**`logo_url`, `logo_url_on_dark` and `favicon_url`** — the brand images by reference, consumed inside a CSS `url("…")` for the two logos and as a `Location:` header by each design's `favicon.php` for the icon. All three carry the same filter and the same validator, character for character; only the error message differs. Only a root-relative path or an `https://` URL, and no character that could break out of any of those contexts:

```
/^(https:\/\/[^\s"'<>()\\]+|\/(?!\/)[^\s"'<>()\\]+)?$/D
```

Two details are load-bearing. The `(?!\/)` lookahead rejects protocol-relative `//host/…`, which browsers treat as remote. The **`/D` modifier** makes `$` mean true end-of-subject; without it PCRE also matches before a final newline, so `/img/logo.png\n` would validate and the raw LF would be emitted inside `content: url("…")`, terminating the CSS string and breaking the stylesheet for every visitor, pre-auth login screen included — and for `favicon_url` the same LF in a `Location:` header is how one header becomes two. The pattern admits no whitespace at all, so with `/D` the value cannot carry a CR or LF. Both designs' `brand.php` and `favicon.php` carry that pattern character for character. The fields are filtered with `TRIM` only and deliberately not `STRIPNL`: a filter that spliced a newline out of the middle of a value would hand the validator a string the administrator never typed.

**The favicon endpoint never fetches what it is pointed at.** A reference is answered with a `302` to the browser, not by reading the target server-side. That is deliberate twice over: an `https://` reference must not turn a pre-auth endpoint into a fetcher of arbitrary URLs on the panel's behalf, and a root-relative reference is a *web* path, not a filesystem path — resolving `/img/../../etc/passwd` against a directory would be a file-disclosure primitive. The browser resolves it, exactly as it would for a hardcoded `<link href>`.

**The uploaded favicon** (`[branding] favicon`) is a data URI, re-validated before serving: an anchored `data:image/…;base64,…` pattern whose media type must be one of the three the uploader accepts (`image/svg+xml`, `image/png`, `image/x-icon` / `image/vnd.microsoft.icon`), then a **strict** `base64_decode()`. Anything else is treated as "not set" and the design's own shipped icon is served instead. The response carries `X-Content-Type-Options: nosniff` and `Content-Security-Policy: default-src 'none'; img-src data:; style-src 'unsafe-inline'; sandbox`, because SVG is an active-content format and a direct navigation to that URL renders it as a same-origin *document*.

**`custom_login_link`** — core renders this **unescaped** inside `<a href="…">` on the pre-auth login page (`login/index.php`), so the validator is anchored and forbids quotes, whitespace and angle brackets: `/^(https?:\/\/[^\s"'<>]+)?$/`.

**Free text** (`company_name`, `custom_login_text`) — `STRIPTAGS` + `STRIPNL` on save. The active design normalises again on read, by the same byte-wise filter in the four endpoints that emit free text (`brand.php` and `title.php` in both designs), so the CSS wordmark and the tab title can never derive different strings from the same row. Escaping is per output context: a CSS-string escape in `brand.php`, `json_encode` with the HEX flags in `title.php`.

**Toggles** — strict `0|1`; anything else reads as the default, which is always the attribution-preserving value.

**Uploaded rasters (logo)** — the `finfo` MIME type must be one of `image/png`, `image/jpeg`, `image/gif`, `image/webp`, and the raw file must be ≤ 45,000 bytes so its base64 form fits the `sys_ini.custom_logo` column.

**Uploaded rasters (favicon)** — a narrower list and a much smaller cap: `image/png` or `image/x-icon` / `image/vnd.microsoft.icon` (normalised to the former on the way in), ≤ 15,000 bytes. JPEG, GIF and WebP buy nothing in a 16px box and would only widen what `favicon.php` must be willing to re-serve. `.ico` is accepted because it is the compatibility floor for browsers that will not take an SVG icon; `finfo`'s label for it varies by libmagic build, so an otherwise-unclassified upload gets one chance to prove itself **structurally** (`customizer_ico_ok()`: header, image count, and every directory entry pointing at a byte range inside the file). That is identification, not a weaker security check — ICO carries no scripting affordance, unlike SVG.

**The upload slot** — one endpoint serves all three brand images, so a POST names the slot it targets (`on_light`, `on_dark`, `favicon`) and `logo_delete.php` takes the same value as a GET parameter. That value selects a storage location, so it is checked against a shared allowlist (`customizer_logo_slots()` in `lib/preview.inc.php`) and never used raw. An absent slot means `on_light`, which is what both endpoints did before a second slot existed, so a replayed request from an older page still means what it meant. A slot that is present but unknown is refused outright rather than defaulted, because quietly writing or deleting a different image would be a destructive surprise reported as success. CSRF, MIME sniffing, the SVG screen and demo mode are shared by every slot; only the format list and the size cap differ.

## The SVG screen

An SVG is XML with executable affordances — `<script>`, event-handler attributes, SMIL animation, `<foreignObject>` embedding arbitrary HTML — so unlike a PNG it cannot simply be believed. `finfo` is also unreliable here: it mislabels prolog-less SVGs as `text/xml`, `text/plain`, even `text/html`. SVG therefore never enters through the MIME allowlist; a texty verdict gets exactly one chance to prove itself against `customizer_svg_ok()`.

**The screen parses the document; it does not scan bytes.** A regex blocklist over the upload bytes does not hold, because the raw bytes are not the document: XML identity is (namespace, local name) rather than spelling, so with `s` bound to the SVG namespace `<s:script>` *is* the SVG script element and `/<script/` never sees it; character references are resolved by the parser, so `&#106;avascript:` is a `javascript:` URL no byte scan matches; CDATA sections and comments let text masquerade as markup and back; and an event handler can hide in an attribute value rather than a name, where SMIL `<set attributeName="onload" to="…">` defeats any "whitespace then `on…=`" pattern.

What runs, against the parsed tree:

- `<!ENTITY` is rejected by a byte scan **before** parsing (billion-laughs expansion, external-entity references). It is a single XML token that whitespace cannot split, so a byte scan is sufficient for this one case. A bare `<!DOCTYPE>` stays allowed: Inkscape and Illustrator both emit one.
- Parsing is `DOMDocument::loadXML()` with `LIBXML_NONET`, so no network retrieval happens during the parse. `LIBXML_NOENT` is deliberately **not** set: entity substitution must never run.
- If `ext/dom` is missing, SVG is **refused** rather than screened weakly. Raster formats are unaffected. CI installs `dom` and `xml` for this reason.
- The root element's local name must be `svg`, in the SVG namespace or in none (which is how a hand-written file with no `xmlns` parses).
- **No processing instruction may appear anywhere.** An `xml-stylesheet` PI makes the renderer fetch a remote stylesheet, and it can sit outside the root element where an element walk would never reach it.
- Elements are screened on local name, lower-cased, over a namespace-agnostic XPath `//*`, so a namespace prefix cannot smuggle anything. In the SVG namespace (or none) the rule is an **allowlist** of the vocabulary a logo needs — shapes, gradients, text, filters, SVG fonts, Inkscape flowed text — so anything outside it is refused by omission. Foreign-namespace elements are tolerated, because real editor output is full of them, but they still go through the attribute screen, and a second **denylist** rejects executable or embedding local names (`script`, `foreignobject`, `iframe`, `object`, `a`, `set`, `animate`, and the rest) in **any** namespace.
- Attributes: any local name beginning `on` is rejected outright. `href` and `src` must be a same-document reference (`#…`) or a `data:image/(png|jpeg|gif|webp);base64,` payload. **Every** attribute value additionally goes through the CSS screen, which covers `style=""` and every funcIRI attribute in one pass; `<style>` element text goes through the same screen, CDATA-wrapped or not.
- The CSS screen unwinds CSS comments, HTML entities and backslash hex escapes before matching, then rejects `@import`, `expression(`, `javascript:`, `vbscript:`, `-moz-binding` and `behavior:`, and screens every `url()` target. It **fails closed**: a PCRE backtrack or recursion limit, input that is not valid UTF-8, and a `url(` occurrence count that disagrees with the number of matches all return "not OK" rather than falling through.

This is verified against **41 cases — 29 bypass attempts, all blocked, and 12 real-world logos** (Inkscape/RDF namespaces, filter chains, embedded data URIs), all still accepted. That corpus ships as `tests/svg/run.php`, and CI runs it on every push, exiting non-zero if any bypass attempt passes or any real logo is refused, so the numbers above cannot go stale without the build going red. Run it with `php tests/svg/run.php`.

The endpoint is admin-only, and every place the logo renders on either design is an image context (an `<img>` tag, or CSS `content: url()` / `background-image`), where browsers apply SVG secure-static mode. The screen is defence in depth rather than the last line of defence.

## Other controls

- **Upload CSRF.** ISPConfig's DB session store does not lock — read is a `SELECT`, write is a whole-row `REPLACE`, last writer wins — so a single-use CSRF token minted during a page render can be silently erased by any concurrent session-writing request in the page-load burst. This is upstream core behaviour, and the cause of the intermittent "CSRF attempt blocked" that stock ISPConfig forms also exhibit. The uploader therefore mints a **fresh token at click time**, from a lone same-origin request gated on `X-Requested-With: XMLHttpRequest` (a header that cannot be attached cross-origin without a CORS preflight), shrinking the race window to milliseconds. The upload POST itself is checked with `csrf_token_check('POST')`.
- **`logo_delete.php`** requires the same `X-Requested-With` header *and* `csrf_token_check('GET')`, matching core's own delete flow. The header check alone would be defence in depth; the token check is the control.
- **`preview.php` mints no token, deliberately.** It is admin-only under the same three checks and **read-only**: one `SELECT` against `sys_ini` row 1, and no write to `sys_ini`, `sys_config` or disk. It echoes back only values that cleared the same anchored allowlists the save path uses — the four hex colours, and the three reference paths inside an `<img src>` that `htmlspecialchars($src, ENT_QUOTES)` escaped, in a payload `json_encode`d with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`. Anything failing its pattern is dropped, not echoed; no free text is reflected. It answers `application/json` with `Cache-Control: no-store`, gated on `POST` (`405` otherwise) and on `X-Requested-With: XMLHttpRequest` (`400` otherwise). A CSRF token would be actively harmful here: minting one *writes* the session, the session store does not lock, and this endpoint fires on a debounce while an admin types, so minting per keystroke would manufacture the race the click-time mint exists to shrink. Leaving the session untouched puts core's handler on its unchanged-data path, where `session.inc.php::write()` only stamps `sys_session.last_updated` rather than rewriting the row — and it is the whole-row rewrite that erases a token minted concurrently. `tests/brand/probe_preview.php` asserts all of that against the file's token stream on every push.
- **Demo mode** (`$conf['demo_mode']`) refuses before any write, and refuses *visibly* — in `onBeforeUpdate` rather than `onUpdateSave`, because the framework tests `errorMessage` before calling the save hook and its redirect is unconditional, so a refusal raised any later would print "Settings saved." over values that were never written.
- **Symlink installs refuse to serve the working tree.** A symlinked install is served *through* the link, so `install.sh` aborts if the source directory contains `.git`, `.omc` or `node_modules` rather than exposing them over HTTP; `--copy` excludes them instead.

## The pre-auth surface

There are **six** of these: `brand.php` (CSS), `title.php` (JS) and `favicon.php` (an image) under `themes/clarity/`, and the same three under `themes/classic/`. Each design links its own three from both of its shell templates, login shell included, and only one design is active per request — but once both are installed all six exist on disk in the web root and all six are reachable by URL. They run with no session and must be safe for anonymous requests. The read half — query, unescape, normalise, cache — is deliberately the same code in both designs, so the two cannot drift apart.

- **No application bootstrap.** None of them starts a session, loads `app.inc.php`, or triggers maintenance-mode redirects. Each opens a direct `mysqli` connection with the credentials already in `interface/lib/config.inc.php` and issues a **single read-only** `SELECT` against `sys_ini` row 1.
- **Always valid output.** `brand.php` always returns HTTP 200 with `Content-Type: text/css` (or 304 on an ETag match); `title.php` always returns HTTP 200 with valid JavaScript; `favicon.php` always returns an image — 200, 304 on an ETag match, or a 302 to a reference the operator set — and **never** a 404, down to a 1×1 transparent PNG if even the design's own shipped icon files cannot be read, because a broken icon shows on every tab. All six re-assert their MIME type after including `config.inc.php`, which emits `text/html` on a web request.
- **Degrade to a no-op on DB failure.** Any connection or query fault is caught and produces an empty stylesheet, a no-op script or the shipped icon, with `Cache-Control: no-store` — never an error message, never a stack trace, and never a cached failure that would blank a host's branding for a whole max-age window after the database has recovered. `title.php` treats `json_encode` returning `false` (invalid UTF-8 in the stored name) the same way, because a classic script that fails to parse runs *nothing*, losing even the statements before it.
- **Every value is validated or escaped before output.** Colours via the hex regex; the reference paths via the anchored allowlist above; the uploaded logo via `#^data:image/[a-z0-9.+-]+;base64,[A-Za-z0-9+/=]+$#i`; toggles as strict `0|1`. The text wordmark has its control characters stripped on read, then is **escaped, not deleted**, for the CSS string context: backslashes doubled first, then quotes. Printable characters including `<` and `>` survive intact, because `brand.php` is only ever fetched through `<link rel="stylesheet">` and never inlined into a `<style>` block. A multibyte-safe 40-character cap applies to the visible wordmark only. In `title.php` the name reaches the page only through `json_encode` with `JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT`.
- **One request parameter exists, on one endpoint.** `themes/classic/brand.php` accepts `?scene=login`, because a few rules apply to the login screen only and classic cannot scope them with a body class: its templates are generated from stock, the transform may not touch the markup, and stock's login `<body>` carries no class or id. The value is **only ever compared, never emitted**: `$_GET['scene'] === 'login'` selects the login scene and anything else selects the app scene, so an array (`?scene[]=login`) is simply not equal and falls through rather than raising a type error. It does reach the `ETag`, deliberately: the two scenes are different URLs, and a validator that ignored the scene would let a stale revalidation cross them. The other five endpoints read no request input.
- **No code execution surface.** Nothing user-controlled is `eval`'d or `include`'d. All six endpoints read and emit validated scalars.
- **Caching is `private`**, max-age 30 seconds, so branding never lands in a shared or reverse-proxy cache.

What these endpoints do disclose, by design, is the panel's configured branding — accent colour, logo, panel name — to an unauthenticated caller. That is the same information the login page renders to the same caller, so it is not an escalation. One smaller observable: a healthy panel with no branding set and a panel whose database is unreachable both emit an empty body, but their response headers differ (`private, max-age=30` plus an ETag, versus `no-store`). That distinction is deliberate, because a failure must not be cached.

## No core file is modified, and this is the whole write surface

Nothing here patches, replaces or edits any ISPConfig core file. The designs live entirely under `themes/clarity/` and `themes/classic/` and override templates and assets through ISPConfig's own theme loader; clarity borrows the stock theme's vendor CSS and JS by reference and never edits it, and classic ships no assets at all. The Branding page lives entirely under `interface/web/customizer/`. **Nothing under either design directory writes at runtime** — all six endpoints only read.

`classic` is the one that comes close to core. Its two shell templates are generated at install time from the target panel's own `themes/default/templates/main.tpl.htm` and `main_login.tpl.htm`. `install.sh` **reads** those two files and writes nothing back, to them or to anything else under `themes/default/`; the transformed copies are written into `themes/classic/templates/`. The transform is mechanical and bounded: asset paths pinned to `themes/default/assets/`, the design's three brand endpoints linked immediately before `</head>`, stock's tab-icon `<link>`s replaced by the one pointing at `favicon.php`, and the stock footer credit split into two addressable spans. The installer then checks its own output against the source — line count (derived from how many icon links were actually replaced), no surviving `current_theme` reference, all three endpoints present, exactly one tab-icon link and it is the right one — and aborts rather than deploy a shell it cannot account for.

There are **no schema changes**: no new tables, no new columns, no `CREATE` or `ALTER` anywhere in the repository. Every write targets a row and column ISPConfig already has. `preview.php` appears nowhere in this table, which is the point of it.

| What | When | Written by |
|---|---|---|
| `sys_ini.config` (row 1) — the `[branding]` section plus existing `[misc]` keys | saving the Branding form | `customizer_edit.php` |
| `sys_ini.config` (row 1) — the single key `[branding] logo_on_dark` | uploading or removing the **dark-background** logo | `logo_upload.php`, `logo_delete.php` |
| `sys_ini.config` (row 1) — the single key `[branding] favicon` | uploading or removing the **favicon** | `logo_upload.php`, `logo_delete.php` |
| `sys_ini.custom_logo` (row 1) — the **light-background** logo | logo upload, remove or purge | `logo_upload.php`, `logo_delete.php`, `bin/purge_branding.php` |
| `sys_user.modules` | install / uninstall | `bin/assign_module.php` (only `typ='admin'` rows), `bin/unassign_module.php` |
| `sys_user.startmodule` | uninstall | `bin/unassign_module.php`, and only where it pointed at `customizer` |
| `sys_user.app_theme` | uninstall with `--reset-users` | `bin/reset_app_theme.php`, and only rows equal to a design being removed; the name is validated as a name, and `default` is refused outright |
| `sys_config` (`group`='interface', `name`='hide_donation_dashlet') | saving the Branding form; cleared on purge | `customizer_edit.php` via core's own `$app->conf()`, `bin/purge_branding.php` |

Three qualifications, because a flat "no new rows" would not be true:

- The donation-dashlet switch goes through core's `$app->conf()`, which issues `REPLACE INTO sys_config`. On a panel whose admin has never clicked ISPConfig's own **Hide** button that row does not exist yet, so the first save **creates** it — the same row, by the same key, that core writes itself. `bin/purge_branding.php` deletes it again, but only when the stored value is one this module can have written.
- The **form** config write goes through core's own `datalogUpdate()`, which appends one row to the `sys_datalog` journal per changed save, exactly as saving **System → Interface Config** does. Every logo write is a direct `UPDATE` instead, deliberately: a 60 KB blob has no business in the journal. That holds for the dark-background logo too, even though it lives inside `sys_ini.config`. The caveat: once a dark-background logo is stored there, the **next** save of the Branding form journals the blob with the image in it. Core has one logo column and this project adds no schema, so there is nowhere else to put it; `logo_url_on_dark` stores a path instead, for panels where that matters.
- On disk, `install.sh` creates `themes/<design>/ispconfig_version` and `themes/<design>/ISPC_VERSION` inside the panel's web root for each design it installs, and for classic also that design's two generated shell templates.

The Branding page also *reads* far more of `sys_ini.config` than it writes, and is careful with it: it parses the **raw** column rather than going through `getconf::get_global_config()`, because that method applies `stripslashes()` on read while nothing re-applies the escaping on write. A read-modify-write through it would silently eat one backslash level from every value in the file on every save, including sections it has no business touching — `[mail] smtp_pass` among them, where `pa\ss` degrades to `pass` and outbound mail authentication starts failing with nothing to explain it. Parsing raw means every value it does not own is carried through byte-identical. The two logo endpoints perform the same read-modify-write for the dark-background logo, under the same rule; the stored value is itself immune to that asymmetry, because the base64 alphabet and the `data:image/…;base64,` prefix contain no backslash.

## Language files are PHP, and CI never executes them

The `.lng` wordbooks are PHP files, and they arrive through community translation pull requests. `include()`ing one in CI would be arbitrary code execution in the runner. CI therefore does two things, neither of which runs the file: `php -l` on every `.lng` (the linter parses, it does not execute), and `.github/scripts/lang_check.php`, which checks key parity and the nav-title length budget using regex over `file_get_contents()`. Both the Apache and the nginx panel vhosts ISPConfig ships already deny `\.lng$` over HTTP, so the shipped wordbooks are not served either.

## Known exposure: each design directory's version file is readable without a session

ISPConfig refuses to load a third-party theme unless the theme directory contains a version file matching the panel exactly, under the exact names `ispconfig_version` and `ISPC_VERSION`. A design directory lives inside the panel's web root, so the web server serves those as ordinary static files: anyone who can reach the login page learns the exact ISPConfig version and patch level, with no session and no credentials. `classic` is no exception. It is not stock behaviour — ISPConfig's own `default` theme ships no version file, and that URL returns 404 on a stock panel, tested — and it arrives with any third-party theme. It also undercuts the `show_version` toggle, which hides the version on the Help page while the same string stays readable one URL away. The filenames cannot be changed, so the fix belongs at the web-server layer: Apache and nginx snippets, the full explanation and a verification command are in [`contrib/webserver/`](contrib/webserver/README.md). Re-check after upgrades, because the ISPConfig updater can regenerate the panel vhost when you let it reconfigure services, which drops the rule.

## Known upstream interactions (not defects in this project)

- The intermittent core **"CSRF attempt blocked"** caused by the lock-free session store. The uploader works around it; the real fix belongs in core and is offered in [docs/UPSTREAM-PATCHES.md](docs/UPSTREAM-PATCHES.md).
- Under the **stock** ISPConfig theme an uploaded SVG logo renders at intrinsic size, because core measures logos with `getimagesizefromstring()`, which cannot read SVG. `classic` inherits that deliberately: it leaves the uploaded logo to core's own markup rather than racing it with a second code path. clarity sizes via CSS and is unaffected, as is a logo set by `logo_url` on either design, which `brand.php` sizes itself. Prefer PNG or WebP for an uploaded logo on the stock theme or on classic.

## Scope

**In scope:** anything in this repository that could let a non-admin reach the Branding page; injection into the rendered panel or the login page (CSS, JS or HTML) via any branding value that is read back; information disclosure on the pre-auth endpoints; privilege escalation; corruption of `sys_ini`; and any way anything shipped here could execute attacker-controlled input.

**Out of scope:** pre-existing ISPConfig core behaviour, which is reported upstream instead; and the deliberate, documented ability of an administrator to opt into hiding the optional courtesy credits and the Help version line. Both credit toggles work on both designs, licence notices are never removed, the admin update notice is left exactly as core ships it, and every attribution toggle defaults to **on**. The donation dashlet toggle is admin-only in core, so no reseller or client ever saw the dashlet, and switching it off writes the same `sys_config` row ISPConfig's own **Hide** button writes rather than hiding anything with CSS. If the markup a toggle targets is ever absent, the rule matches nothing and the credit stays visible: the failure mode is "attribution shown".
