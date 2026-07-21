# Security Policy

## Reporting a vulnerability

Please report security issues **privately**, not as a public issue or pull
request. Use GitHub's private vulnerability reporting on this repository
(**Security → Report a vulnerability**). You'll get an acknowledgement and a
fix or assessment; coordinated disclosure is welcome.

If a report turns out to be an ISPConfig **core** issue rather than one in this
module, it will be redirected upstream to the ISPConfig project with credit.

## Supported versions

The latest tagged release receives fixes. This module is developed and verified
against **ISPConfig 3.3.1p1** (3.2 / 3.3 supported).

## Design posture

The module is built so that the safe thing is also the default thing.

- **No ISPConfig core file is modified.** The module lives entirely in its own
  directory (`interface/web/customizer/`) and writes only to ISPConfig's
  existing `sys_ini` row and the `sys_user.modules` column. There is no schema
  change and no core patch, so there is nothing in core for it to weaken.
- **Admin-only, by construction.** Every endpoint is guarded three ways in
  order: `check_module_permissions('customizer')` → `check_security_permissions('admin_allow_system_config')` (ships as **superadmin**) → `is_admin()`. The
  module is granted only to `typ='admin'` users; no client/reseller provisioning
  path, remote-API call, or self-service settings form can add it to a
  non-admin. Resellers and clients only ever see the *result* of branding, never
  the module.
- **Input is validated on write and again on read.** Colours are anchored hex
  regexes; the logo-URL override is an anchored allowlist (root-relative path or
  `https://`, no characters that could break a CSS `url()` context, and no
  protocol-relative `//host`); uploaded SVGs must be well-formed XML with an
  `<svg>` root and are rejected if they contain scripts, event handlers,
  `foreignObject`, or entity declarations; raster uploads are MIME- and
  size-capped (≤ 45 KB). The companion theme reader (`brand.php`) re-validates
  every value before it reaches CSS output, so a value written by any means
  (including the remote API) is still checked at render time.
- **Upload CSRF.** ISPConfig's DB session store does not lock, so a page-render
  CSRF token can be silently clobbered by a concurrent request (this is an
  upstream core behaviour — see `docs/` in the toolkit). The uploader therefore
  mints a fresh token at click time via a same-origin, `X-Requested-With`-gated
  request rather than relying on the render-time token.
- **No code execution surface.** The module never `eval`s or `include`s
  user-controlled input; the logo is stored and served as a validated data URI
  or a validated reference, never executed. CI parses the PHP language files as
  text rather than including them, so a translation pull request cannot run code
  in the build.

## Known upstream interactions (not module bugs)

- The intermittent core **“CSRF attempt blocked”** behaviour caused by the
  lock-free session store — the uploader works around it; the core fix is
  offered upstream.
- Under the **stock** ISPConfig theme, an SVG logo renders at intrinsic size
  because core sizes logos with `getimagesizefromstring()`, which cannot measure
  SVG. Brand-aware themes size via CSS and are unaffected; prefer PNG/WebP on the
  stock theme. A core guard for this is offered upstream.

## Scope

In scope: anything in this repository that could let a non-admin reach the
module, inject into the rendered panel (CSS/JS/HTML), execute code, escalate
privileges, or corrupt `sys_ini`. Out of scope: pre-existing ISPConfig core
behaviour (reported upstream instead) and the deliberate, documented ability of
an admin to hide optional courtesy credits (licence notices are never removed).
