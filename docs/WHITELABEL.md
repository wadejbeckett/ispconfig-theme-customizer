# White-Label Roadmap

The goal: ISPConfig as a full Plesk/cPanel alternative that an operator can
brand as their own — **without ever modifying ISPConfig itself**. The toolkit
splits into the base theme (Clarity, evolving) and the branding module
(ispconfig-customizer). This document is the verified audit of every place
ISPConfig identifies itself in 3.3.1p1, who sees it, and how (or whether) the
kit can override it inside its allowed envelope.

**The envelope (hard constraint):** theme directories (`themes/<name>/`),
module directories (`interface/web/<module>/`), and writes to existing DB
rows/columns (`sys_ini` row 1, `sys_user.modules`, `sys_message`,
`sys_config`). The single documented exception is `$conf['theme']` in the two
`config.inc.php` files — a manual, user-performed edit in both directions,
never touched by installers.

Verified override mechanisms (each confirmed against 3.3.1p1 source):

- **Frame templates** (`main.tpl.htm`, `main_login.tpl.htm`, `topnav.tpl.htm`,
  `error.tpl.htm`): theme-flat override — Clarity already owns the first three.
- **Module content templates** (dashlets, login pages, help pages, tools):
  override at `themes/clarity/templates/<module>/<basename>.htm` — the
  module-subdir rule. Flat placement never wins for these.
- **Language strings**: **cannot be shadowed** without core edits — no theme
  fallback exists in the lang loader. Lang-fed surfaces are CSS/JS-hide or
  upstream-patch only.
- **`sys_ini` config keys**: the customizer's native channel (`[branding]` is
  module-owned — core's only `[branding]` reference is dead commented code;
  `[misc]`/`[mail]` keys are stock, core-consumed).
- **`tmpl_phpinclude`**: enabled in core; the theme dir already runs PHP
  (brand.php) — usable for server-side branding inside theme templates.

## Status: already covered

| Surface | Audience | How |
|---|---|---|
| Browser tab `Name :: ISPConfig` | all | theme v2.1.5 — a set panel name replaces the product title (main + login + reset pages) |
| Logo everywhere (frame, login, OTP, reset) | all | native `custom_logo` via the uploader, or `[branding] logo_url` |
| Accent / rail / login colours | all | `[branding]` + brand.php |
| Footer credits ("powered by ISPConfig", theme credit) | all | the two attribution toggles (default ON — courtesy lines only) |
| Login footnote text/link, panel name | all | stock `[misc]` keys the customizer writes |
| Favicons / tiles / mask icons | all | theme-owned assets (already neutral) |
| Outbound mail sender identity | all | stock `[mail] admin_name` / `admin_mail` |

## P0 — bugs & quick wins (all inside the envelope)

> **Status: SHIPPED 2026-07-21** — customizer v1.0.6 + theme v2.1.6/v2.1.7.
> Items 1-4 below are done and live-verified (title.php serves the branded
> title on all auth pages incl. OTP; uninstall round-trips tested against the
> production DB with exact state restoration).

1. **OTP page leaks a bare "ISPConfig" tab title** — core never sets
   `company_name` there, so the v2.1.5 trim never fires. Proper fix: render the
   title server-side in the theme's login template via `tmpl_phpinclude`
   reading `sys_ini` (also removes the JS-trim flash on all auth pages).
2. **Clarity's own `site.webmanifest` still says `"name": "ISPConfig"`** — the
   last ISPConfig string in our own assets. Ship a neutral name.
3. **Uninstall tooling (currently missing, README overclaims):**
   `uninstall.sh` in both repos + `bin/unassign_module.php` (strip
   `customizer` from **all** users' module CSVs — install assigns it to every
   admin, not just one — and reset `startmodule='customizer'` →
   `'dashboard'`), theme-side `sys_user.app_theme` reset SQL (core does *not*
   self-heal the column; users get a recurring "theme not compatible" banner),
   and an explicit `--purge-branding` flag (drop `[branding]`, blank the three
   `[misc]` keys, clear `custom_logo`). Default preserves branding
   (reinstall-friendly); purge only on request. Installers never touch
   `config.inc.php` in either direction.
4. Housekeeping: stray `.omc/` state dir inside the theme clone blocks the
   symlink install path.

## P1 — white-label completeness (customizer features)

5. **Per-role dashboard curation** (the "hide announcements/news" ask): the
   customizer should manage the six `*_dashlets_left/right` keys and the three
   `dashboard_atom_url_*` feeds. Recommended defaults: blank the
   reseller/client news feeds (or point them at the operator's own Atom feed —
   a genuine rebrand feature), leave the admin feed on ispconfig.org (update
   awareness), and **omit `[metrics]` from non-admin layouts** — stock ISPConfig
   shows whole-server load/memory/network charts to every client (the
   admin-only guard is commented out in core; real infrastructure disclosure,
   not just branding). Gotchas to encode: setting only one column key
   activates both (empty ≠ default), `[none]` is the hide-all idiom, feeds are
   session-cached until re-login.
5b. **Staff-view version hiding (requested 2026-07-21):** operators who brand
   for their own staff don't want "ISPConfig Version: x.y.z" visible in Help.
   Mechanism: a `[branding]` toggle read by brand.php emitting
   `#help_version { display:none }` (the nav item carries that html_id) —
   note it hides for ALL admin users including the superadmin (CSS cannot
   see roles; the session exposes admin/user type only), and the page stays
   reachable by direct URL. Role-aware hiding would need a core patch.
6. **"Neutralize admin chrome" toggle (default OFF):** CSS/JS relabeling of
   admin-only lang surfaces — Monitor's "ISPConfig Log"/"ISPConfig Cron -
   Log", Help's "About ISPConfig", Tools headings, the fail2ban HowtoForge
   hint. Admin-only eyes, so cosmetic and low priority.
7. **Branded client announcements** — a white-label *asset*: the customizer
   can post per-client/per-group notices through core's own `sys_message`
   mechanism (auth-scoped, dismissible, translated banner slot on every
   dashboard). Clean uninstall = delete own rows.
8. Theme `error.tpl.htm` override (stock fragment is neutral but unstyled) —
   static markup only; no template vars are substituted on that path.

## P2 — needs core patches → the upstream channel

These cannot be done inside the envelope. Each is small, benefits every
ISPConfig user (not just white-labelers), and is exactly the kind of patch to
offer upstream:

9. **Email branding**: password-reset and OTP mails hardcode "ISPConfig 3
   Control panel" in subjects/bodies (lang strings), support-ticket mails
   append a literal `ISPConfig: https://<host>`, and every interface mail
   sends `User-Agent: ISPConfig/3 (Mailer Class)`. Upstream proposal: derive
   from `company_name` when set — core already half-supports this via
   `[mail] admin_name`.
10. **Maintenance-mode text** hardcodes ISPConfig in a lang string injected as
    pre-built HTML (template override can't reword it). Stopgap: theme JS
    substitution on the login page; upstream: parameterize the string. Same
    for the remoting SoapFault variant.
11. **Bugs we have already root-caused on this panel — patches ready to
    contribute:** the lock-free DB session store (REPLACE-based last-writer-
    wins silently eats single-use CSRF tokens → the notorious random "CSRF
    attempt blocked"), the dead stock logo uploader (`custom_logo` setter is
    commented out and its ajax endpoint missing), and the unguarded
    `getimagesizefromstring` on SVG logos at login.

## Deliberately out of scope (symbiosis by design)

- **Admin update notice + version phone-home**: security-relevant, admin-only,
  clients never see it. Stays untouched.
- **Donate dashlet**: admin-only, funds upstream. Stays visible by default; an
  optional hide would write the *exact* `sys_config` row core's own Hide
  button writes — same mechanism, same 1-year TTL.
- **Attribution toggles default ON**; turning them off hides courtesy lines
  only — LICENSE files and source headers are never touched, structurally.
- **Help/support module**: keep assigned; the support-message inbox is
  branding-free client↔operator messaging — an asset, not a leak.
- **Hard boundary (do not promise):** custom dashlets are impossible without a
  core touch — dashlet code loads exclusively from core's
  `web/dashboard/dashlets/` directory. Layout keys can only arrange what core
  ships.

## Role visibility (verified)

Only admins can ever see the customizer. Module access = the `sys_user.modules`
CSV checked at login and on every request; the installer grants `customizer`
to `typ='admin'` users only. Client/reseller creation builds module lists from
`$conf['interface_modules_enabled']` (never contains customizer); the remote
API filters module grants against that same list; the self-service settings
form cannot add modules. The only UI that could hand it to a non-admin is the
admin-gated CP Users editor. Every customizer endpoint is additionally
triple-guarded (`check_module_permissions` → `admin_allow_system_config`,
shipped default *superadmin* → `is_admin`). Resellers and clients only ever
see the result. One core gotcha worth knowing: editing a client/reseller whose
`limit_client` changed silently rewrites their module CSV wholesale from the
core default list.
