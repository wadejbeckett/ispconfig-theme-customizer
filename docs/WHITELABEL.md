# White-Label Roadmap

The goal: ISPConfig as a full Plesk/cPanel alternative that an operator can
brand as their own — **without ever modifying ISPConfig itself**. This is one
product: a modern, brandable front-end for the panel. It replaces the interface with one of two
designs — `clarity` (`themes/clarity/`), a ground-up dark and light interface, or
`classic` (`themes/classic/`), the stock look made brandable by an install-time
generated shell — and adds an admin-only **Branding** page
(`interface/web/customizer/`) where the logo, panel name and colours are
set. The design reads its colours, logo and panel name from exactly the
`sys_ini` keys that page writes — one contract, one version number. (Before
v3.0.0 the halves shipped as separate projects on independent versions with
nothing enforcing that they matched, which is why some version numbers below are
pre-merge.) This document is the verified audit of every place ISPConfig
identifies itself in 3.3.1p1, who sees it, and how (or whether) it can be
overridden inside the allowed envelope.

**The envelope (hard constraint):** theme directories (`themes/<name>/`),
module directories (`interface/web/<module>/`), and writes to existing DB
rows/columns (`sys_ini` row 1, `sys_user.modules`, `sys_message`,
`sys_config`). The single documented exception is `$conf['theme']` in the two
`config.inc.php` files — a manual, user-performed edit in both directions,
never touched by `install.sh` or `uninstall.sh`.

Verified override mechanisms (each confirmed against 3.3.1p1 source):

- **Frame templates** (`main.tpl.htm`, `main_login.tpl.htm`, `topnav.tpl.htm`,
  `error.tpl.htm`): theme-flat override. **clarity** owns the first three as
  committed files. **classic** owns the first two as install-time generated
  copies of the panel's own stock markup — deliberately not committed, so they
  cannot drift from the ISPConfig version actually installed — and inherits
  `topnav.tpl.htm` from stock unchanged. `error.tpl.htm` is still stock in both
  (item 8 below).
- **Module content templates** (dashlets, login pages, help pages, tools):
  override at `themes/clarity/templates/<module>/<basename>.htm` — the
  module-subdir rule. Flat placement never wins for these. Clarity uses it for
  three dashboard templates (`dashboard/dashboard.htm`, `dashboard/modules.htm`,
  `dashboard/metrics.htm`). With the three frame templates that is **six
  overridden templates in total**, each pinned in
  `themes/clarity/BUILT-AGAINST.txt` — re-diff and re-stamp them, and re-run
  `install.sh`, after *any* ISPConfig upgrade, patch releases included.
- **Language strings**: **cannot be shadowed** without core edits — no theme
  fallback exists in the lang loader. Lang-fed surfaces are CSS/JS-hide or
  upstream-patch only.
- **`sys_ini` config keys**: the Branding page's native channel (`[branding]`
  is ours — core's only `[branding]` reference is dead commented code;
  `[misc]`/`[mail]` keys are stock, core-consumed). CI checks that every key
  the page writes is read on the render side, so a future design that reads the
  same keys inherits the branding unchanged.
- **`tmpl_phpinclude`**: enabled in core; the theme dir already runs PHP
  (brand.php) — usable for server-side branding inside theme templates.

## Status: already covered

Version numbers cited from here down are the **pre-merge release lines** — theme
v2.x and module v1.x, both folded into v3.0.0. They are kept as the record of
when each surface was closed, not as versions you can install today.

| Surface | Audience | How |
|---|---|---|
| Browser tab `Name :: ISPConfig` | all | theme v2.1.5 — a set panel name replaces the product title (main + login + reset pages) |
| Logo everywhere (frame, login, OTP, reset) | all | two variants named after the background they sit on: light-background = native `custom_logo` (uploader) or `[branding] logo_url`; dark-background = `[branding] logo_on_dark` or `logo_url_on_dark`. Each surface takes the one matching the background it sits on and falls back to the other. That choice is automatic — it reads the operator's own `rail_hex` / `login_bg`, which can falsify a design's assumption about its own brightness — and `[branding] logo_variant_nav` / `logo_variant_login` pin a surface to one variant when it gets it wrong |
| Accent / rail / login colours | all | `[branding]` + brand.php |
| Footer credits ("powered by ISPConfig", theme credit) | all | the two attribution toggles (default ON — courtesy lines only) |
| Login footnote text/link, panel name | all | stock `[misc]` keys the Branding page writes |
| Browser-tab favicon | all | `[branding] favicon` (upload) or `favicon_url` (by reference), served per design by `themes/<design>/favicon.php`; falls back to the design's shipped icon |
| Home-screen / pinned-tab / tile icons | all | theme-owned assets (already neutral) — platform install artefacts, not the tab |
| Outbound mail sender identity | all | stock `[mail] admin_name` / `admin_mail` |

## P0 — bugs & quick wins (all inside the envelope) — *closed*

> **History, not a to-do list.** All four shipped 2026-07-21 in customizer
> v1.0.6 + theme v2.1.6/v2.1.7, and were live-verified then: title.php serves
> the branded title on all auth pages incl. OTP, and uninstall round-trips were
> tested against the production DB with exact state restoration. The items are
> written below as they were when open; each is fixed in the current tree.

1. **OTP page leaks a bare "ISPConfig" tab title** — core never sets
   `company_name` there, so the v2.1.5 trim never fires. Proper fix: render the
   title server-side in the theme's login template via `tmpl_phpinclude`
   reading `sys_ini` (also removes the JS-trim flash on all auth pages).
2. **Clarity's own `site.webmanifest` still says `"name": "ISPConfig"`** — the
   last ISPConfig string in our own assets. Ship a neutral name.
3. **Uninstall tooling (missing at the time; the README overclaimed):** an
   `uninstall.sh` mirroring `install.sh`'s `--theme` / `--module` / `--all`
   flags (with no flag, both are removed) + `bin/unassign_module.php` (strip
   `customizer` from **all** users' module CSVs — install assigns it to every
   admin, not just one — and reset `startmodule='customizer'` →
   `'dashboard'`), `--reset-users` for the `sys_user.app_theme` reset SQL (core
   does *not* self-heal the column; users get a recurring "theme not
   compatible" banner), and an explicit `--purge-branding` flag (drop
   `[branding]`, blank the three `[misc]` keys, clear `custom_logo`). Neither
   reset happens by default: uninstall leaves branding stored
   (reinstall-friendly) and leaves `app_theme` alone unless asked. Neither
   script touches `config.inc.php` in either direction — reverting
   `$conf['theme']` is always a manual edit.
4. Housekeeping: stray `.omc/` state dir inside the theme clone blocks the
   symlink install path.

## P1 — white-label completeness (Branding-page features)

> **Partly delivered.** Item 5b and the news-feed half of item 5 shipped and are
> in the current tree; 5's dashlet-layout half and items 6-8 are still open.
> Status is marked per item.

5. *(news-feed half shipped — the Branding page's news-feed toggle owns the
   three `dashboard_atom_url_*` keys and `bin/purge_branding.php` restores
   them; the `*_dashlets_left/right` layout keys remain unmanaged.)*
   **Per-role dashboard curation** (the "hide announcements/news" ask): the
   Branding page should manage the six `*_dashlets_left/right` keys and the
   three `dashboard_atom_url_*` feeds. Recommended defaults: blank the
   reseller/client news feeds (or point them at the operator's own Atom feed —
   a genuine rebrand feature), leave the admin feed on ispconfig.org (update
   awareness), and **omit `[metrics]` from non-admin layouts** — stock ISPConfig
   shows whole-server load/memory/network charts to every client (the
   admin-only guard is commented out in core; real infrastructure disclosure,
   not just branding). Gotchas to encode: setting only one column key
   activates both (empty ≠ default), `[none]` is the hide-all idiom, feeds are
   session-cached until re-login.
5b. *(shipped — the show_version toggle, honoured by each design's brand.php)*
   **Staff-view version hiding (requested 2026-07-21):** operators who brand
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
7. **Branded client announcements** — a white-label *asset*: the Branding page
   could post per-client/per-group notices through core's own `sys_message`
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

Only admins can ever see the Branding page. Module access = the
`sys_user.modules` CSV checked at login and on every request; `install.sh`
grants `customizer` to `typ='admin'` users only. Client/reseller creation builds
module lists from `$conf['interface_modules_enabled']` (never contains
`customizer`); the remote API filters module grants against that same list; the
self-service settings form cannot add modules. The only UI that could hand it to
a non-admin is the admin-gated CP Users editor. Every one of its endpoints is
additionally triple-guarded (`check_module_permissions` → `admin_allow_system_config`,
shipped default *superadmin* → `is_admin`). Resellers and clients only ever
see the result. One core gotcha worth knowing: editing a client/reseller whose
`limit_client` changed silently rewrites their module CSV wholesale from the
core default list.
