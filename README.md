# ispconfig-theme-customizer

Two things that belong together for the [ISPConfig](https://www.ispconfig.org/)
control panel: **Clarity**, a complete dark/light interface built on VMware
Clarity design tokens (navy navigation rail, card-based content, Clarity icons,
a theme switcher, a redesigned login screen), and **Branding**, an admin-only
white-label module that sets your logo, panel name, accent colour and login
details from a page inside the panel instead of from a text editor. Both install
as ordinary ISPConfig extensions — a theme directory and a module directory — so
**no core file is ever modified and no database schema is added**. Either can be
installed on its own: the theme is useful with stock branding, and the module
does real work on the stock ISPConfig theme.

![Dashboard](mockup/shots/dark-dashboard-desktop.png)

| | |
|---|---|
| ![Websites list](mockup/shots/dark-sites-desktop.png) | ![Login](mockup/shots/dark-login-desktop.png) |
| ![Light dashboard](mockup/shots/light-dashboard-desktop.png) | ![Branding page](docs/screenshots/branding-page-dark.png) |

More in [`mockup/shots/`](mockup/shots/) (mobile, forms, components, and
`default-desktop.png` for a stock-panel comparison) and
[`docs/screenshots/`](docs/screenshots/).

## Why one repository

These shipped as two repos until v3.0.0, and that was a mistake. The theme's
`brand.php` reads exactly the `sys_ini` keys the module's `customizer_edit.php`
writes — one contract, split across two projects with independent version
numbers and nothing enforcing that they matched. Module v1.0.12 against theme
v2.1.0 meant the accent colour silently did not apply, with no error anywhere.
One repo, one version number, one tag.

**v3.0.0** is the first combined release. The number sits above both old lines
(theme v2.2.4, module v1.0.13) and is a major bump because the repository layout
changed. There are no submodules; a plain `git clone` is all you need.

## What the theme gives you

- A full interface: vertical brand rail, topbar, card surfaces, restyled tables,
  forms, tabs and modals — applied by CSS to the stock markup, not by rewriting
  pages.
- **Dark and light**, with an in-panel switcher; the choice is stored in
  `localStorage`, so it is per browser.
- Clarity icon shapes replacing the legacy `ispconfig` icon font, the Bootstrap
  glyphicons and the FontAwesome 4 glyphs the panel still emits — inlined as CSS
  masks, so the icons themselves add no font of their own and no extra request.
  The stock icon fonts still load as vendor dependencies from `themes/default`;
  no covered glyph renders from them.
- Self-hosted **Inter**. No external font, script or CDN request is added.
- Six overridden templates and nothing else: three shell templates
  (`main.tpl.htm`, `main_login.tpl.htm`, `topnav.tpl.htm`) and three dashboard
  dashlet templates (`dashboard.htm`, `modules.htm`, `metrics.htm`). Every other
  page renders from the stock `default` theme and is styled by CSS alone. All
  six are pinned, with the JS and template-variable contracts each one preserves,
  in [`themes/clarity/BUILT-AGAINST.txt`](themes/clarity/BUILT-AGAINST.txt) —
  that file is what you re-check after a panel upgrade.
- Vendor CSS/JS still loads from `themes/default`, so that directory must remain
  present. It always is.

## What the module gives you

One admin-only page, labelled **Branding** in the top navigation (not
"Customizer" — that is only the directory name). It writes to the existing
`sys_ini` row and to `sys_user.modules`; it creates no tables and no columns.
The UI ships in seven locales: English, German, French, Spanish, Italian, Dutch
and Portuguese.

**Works on the stock ISPConfig theme** — these values are read by ISPConfig core
itself, so the module is useful with no theme installed:

| Setting | Where core reads it |
|---|---|
| `custom_logo` (uploader) | login page and panel header |
| `company_name` | browser title prefix |
| `custom_login_text` / `custom_login_link` | the extra line on the login screen |
| `dashboard_atom_url_admin` / `_reseller` / `_client` | the per-role dashboard news feed, which the page's news toggle drives |

The uploader exists because the stock panel's own logo upload is currently
non-functional. The field it writes is ISPConfig's, not a new one.

**Needs a brand-aware theme** — Clarity, or any theme that adopts the contract
below. Nothing in core reads these:

| Setting | Effect |
|---|---|
| `accent_hex` | re-hues the blue ramp and accents |
| `rail_hex` | the navy brand rail |
| `login_bg` | login-screen background base |
| `logo_url` | logo by reference (root-relative path or `https` URL); wins over the uploaded `custom_logo` |
| `show_version` | hides the version surfaces on the Help page — read [Version disclosure](#version-disclosure) before relying on it |
| `show_ispconfig_credit`, `show_theme_credit` | the two footer courtesy lines |

Attribution defaults to **on**. The donate dashlet and the admin update notice
are left exactly as ISPConfig ships them.

## Install

Clone somewhere the web server can read — **not `/root`**, which is mode 700 and
serves nothing through a symlink:

```bash
cd /opt
git clone https://github.com/wadejbeckett/ispconfig-theme-customizer.git
cd ispconfig-theme-customizer
sudo ./install.sh
```

```
./install.sh [--theme|--module|--all] [--copy] [--no-assign] [ISPCONFIG_ROOT]
```

- With no component flag, **both** are installed. Use `--theme` or `--module`
  for one.
- `--copy` copies real files instead of symlinking. Symlinks mean the panel
  reads from your clone, so the clone and every parent directory must stay
  traversable by the web server; with `--copy` the clone can live anywhere and
  need not stay on the server at all. The installer warns if the path is not
  traversable.
- `--no-assign` skips assigning the module to admin users; do it by hand in
  *System → CP Users → edit the admin user → Modules*.
- `ISPCONFIG_ROOT` defaults to `/usr/local/ispconfig`.

The installer also stamps the two version-gate files ISPConfig requires
(`ispconfig_version` and `ISPC_VERSION`) and prints the next steps.

Multiserver: install only on the server that serves the ISPConfig web interface.
Slaves need nothing.

**Then pick the theme.** Per user: *Tools → User Settings → Design → `clarity` →
Save*. Core updates the session theme on save and force-reloads the page, so the
change applies immediately — the code does not require a login round trip. In
practice, if the frame still looks stock, log out and back in, then hard-refresh
(`Ctrl+Shift+R`) so the browser drops the old CSS.

**Manual step — login screen and system-wide default.** The installers never
edit ISPConfig configuration, by design. To set the default yourself, add

```php
$conf['theme'] = 'clarity';
```

to **both** `interface/lib/config.inc.php` **and** `server/lib/config.inc.php`
(each already has the line, set to `'default'`). The interface one controls the
login page and the default for new users. The server one is what makes it
persist: ISPConfig updates regenerate both files and carry the theme value
forward from the **server** config, so setting only the interface one means the
login screen quietly reverts at the next panel update.

**The Branding page** appears in the top navigation after the next page load or
re-login.

## After any ISPConfig upgrade

Re-run the installer after **every** panel upgrade, including patch releases
(`3.3.1p1` → `3.3.1p2`):

```bash
cd /opt/ispconfig-theme-customizer
git fetch --tags && git checkout <tag>
sudo ./install.sh
```

This is not just for major versions. Core compares the stamped
`ispconfig_version` against `ISPC_APP_VERSION` as an exact string; on any
mismatch it resets affected users to the default theme at login and the Design
picker stops offering the theme. That value changes on every release.

If you installed with `--copy`, re-run **with `--copy` again** — re-running
without it converts the install into a symlink pointing at your clone.

Also diff the six overridden templates against the stock ones for your new
version before trusting the upgrade. Compare against your own panel's
`interface/web/themes/default/templates/` and
`interface/web/dashboard/**/templates/`, or against the ISPConfig source for
that version; `BUILT-AGAINST.txt` lists what each override must preserve. Full
procedure in [UPGRADING.md](UPGRADING.md).

## Compatibility

- **ISPConfig 3.3** — developed and verified against **3.3.1p1**. The template
  overrides are pinned to that version's stock markup.
- **ISPConfig 3.2 is not verified.** It has not been tested, and nothing here
  claims it works. Treat it as unknown.
- Root shell access to the panel server, and the stock `default` theme still
  present (the theme loads its vendor CSS/JS from there).
- PHP CLI, for the module's assignment and cleanup helpers. Without it the
  installer says so and prints the manual equivalent.

## Version disclosure

Read this before deploying on a public panel.

ISPConfig's theme gate requires the file `themes/<theme>/ispconfig_version`
inside the panel's **web root**, under that exact name — so the web server
serves it as an ordinary static file:

```bash
curl -k https://panel.example.com:8080/themes/clarity/ispconfig_version
# 3.3.1p1
```

No session, no credentials. Anyone who can reach your login page learns the
exact version **and patch level**. This is not stock behaviour: ISPConfig's own
`default` theme ships no version file and that URL returns 404 — tested. The
exposure arrives with any third-party theme, this one included. It also
undercuts the module's `show_version` toggle, which hides the version on the
Help page while the same string stays readable one URL away.

The fix belongs at the web-server layer, and ISPConfig already denies files this
way in its own panel vhost. Ready-made nginx and Apache snippets, with the full
explanation, are in [`contrib/webserver/`](contrib/webserver/README.md).

## Uninstall

```
./uninstall.sh [--theme|--module|--all] [--reset-users] [--purge-branding]
               [--keep-assignment] [ISPCONFIG_ROOT]
```

With no component flag it uninstalls **both**. What it actually does, stated
plainly, because the defaults are deliberately conservative:

- **It does not restore stock on its own.** The theme uninstaller removes
  `themes/clarity` but resets `sys_user.app_theme` only when you pass
  `--reset-users`. That reset matters: core falls back to the default theme at
  session level but never heals the stored column, so without it affected users
  get a "theme not compatible" banner at every login. Skip it only if you are
  reinstalling.
- **Your branding survives by default.** The module uninstaller removes the
  module directory and strips `customizer` from users' module lists (resetting
  any startmodule that pointed at it), but leaves the stored values alone — the
  stock fields keep working and stay editable under *System → Interface Config*,
  and the `[branding]` values sit inert for any brand-aware theme. Pass
  `--purge-branding` to wipe them, `--keep-assignment` to leave the module in
  users' lists.
- **`$conf['theme']` is always yours to revert.** Neither script edits ISPConfig
  configuration. If you set it to `'clarity'`, set it back to `'default'` in
  both files. The uninstaller checks and warns you *before* it removes anything,
  because a panel still configured for a theme directory that no longer exists
  serves a login page with every stylesheet and script 404ing, silently.

## The brand-token contract

The module writes, and the theme reads, a small set of keys in the global
`sys_ini` row (`sysini_id = 1`) — the `[branding]` and `[misc]` values listed in
the two tables above. That is the entire interface between them: no shared code,
no API.

That is deliberate, and it is the reason to think of this as one contract rather
than one product. Another base design can adopt the same keys and inherit the
whole Branding page for free. The reference implementation is
[`themes/clarity/brand.php`](themes/clarity/brand.php) — a read-only, pre-auth
stylesheet endpoint that queries one row, emits CSS custom properties, and is a
no-op when nothing is set. The audit of every place ISPConfig identifies itself,
and which of those can be overridden inside the theme/module envelope, is in
[docs/WHITELABEL.md](docs/WHITELABEL.md).

You can also brand the theme with two file swaps and no module at all: replace
`themes/clarity/assets/images/wordmark-white.svg` (light artwork — it sits on
the navy rail, the mobile header and the login card; any aspect ratio works) and
drop your own icons into `themes/clarity/assets/favicon/`. A logo set through
the module or the native field overrides the wordmark.

## Repo layout

| Path | What |
|---|---|
| `themes/clarity/` | The theme: 6 templates, stylesheets, self-hosted fonts, brand assets. |
| `themes/clarity/brand.php` | Brand-token reader — read-only pre-auth stylesheet endpoint; a no-op when nothing is set. |
| `themes/clarity/BUILT-AGAINST.txt` | What is overridden, against which stock version, and the contracts each override preserves. |
| `interface/web/customizer/` | The Branding module (directory name `customizer`; nav label "Branding"). |
| `bin/` | PHP helpers the scripts call: module assign/unassign, theme reset, branding purge. |
| `install.sh`, `uninstall.sh` | Unified installers for both components. |
| `contrib/webserver/` | nginx and Apache snippets for the version-disclosure mitigation. |
| `DESIGN.md` | The design language — tokens, surfaces, component rules. |
| `UPGRADING.md` | Version stamp and override contracts across panel upgrades. |
| `docs/WHITELABEL.md` | Verified audit of ISPConfig's self-identification surfaces. |
| `mockup/` | Offline dev harness: renders the real templates with sample content and screenshots them (`python3 build.py --shoot`; needs Playwright and a local ISPConfig source checkout for the stock vendor assets). Not needed to install. |

## Contributing

Bug reports, fixes and ideas are welcome — see [CONTRIBUTING.md](CONTRIBUTING.md)
for how the theme and module are put together, the ground rules that keep them
upgrade-safe, and how to test a change. The issue templates ask for exactly what
makes a theme or branding bug diagnosable. Security reports:
[SECURITY.md](SECURITY.md).

## Support this project

Free and MIT-licensed. If it saves you time, donations are taken in Monero:

```text
44BtMn9izxH8mK2yFbSdY6Di7TNobkLbnHdZ6gZQjukCME5vsNhtPRtH4TcVkDHKHLhSpAJbsjv8gCdYuSZVMpXgMkUC1hV
```

Code is just as welcome as coin.

### Support ISPConfig itself

This project only exists because ISPConfig does, and it is built to sit on top
of ISPConfig rather than replace any part of it. The project takes no direct
donations; the ways its developers ask to be supported are:

- Buy the [ISPConfig manual](https://www.ispconfig.org/documentation/user-manual/)
  (€5) or a [HowtoForge subscription](https://www.howtoforge.com/download-the-ispconfig-3-manual)
  that includes it — the project's own README names this as the way to fund
  development.
- Need paid help? [ISPConfig Business Support](https://www.ispconfig.org/get-support/)
  is run by the core team's official partner.
- Their commercial tools fund the free panel:
  [ISPProtect](https://www.ispprotect.com/) and the
  [Migration Tool](https://www.ispconfig.org/add-ons/ispconfig-migration-tool/).
- Contribute upstream: code and bug reports at
  [git.ispconfig.org](https://git.ispconfig.org/ispconfig/ispconfig3), help
  other users at the [HowtoForge forum](https://forum.howtoforge.com/).

## Licence and attribution

- This project: [MIT](LICENSE).
- **VMware Clarity (`@cds/core`, MIT).** Surface and status values are derived
  from Clarity's dark theme tokens, and 29 Clarity **icon shapes are bundled
  verbatim** as data-URI SVG masks in
  `themes/clarity/assets/stylesheets/clarity/icons.css`. That is redistribution
  of a substantial portion of the upstream work, so the MIT copyright and
  permission notice ships in that file's header, attached to the artwork itself.
  Keep the header with the shapes if you vendor the theme.
- **Inter** — [SIL OFL 1.1](themes/clarity/assets/fonts/inter/LICENSE.txt),
  self-hosted.
- Frame anatomy informed by DirectAdmin Evolution (reference only; nothing
  copied).
- ISPConfig is BSD-licensed. This project ships no ISPConfig code and modifies
  none.

### Notices

- Not affiliated with, or endorsed by, **VMware**. Built using the open-source
  Clarity design system (`@cds/core`, MIT).
- Not affiliated with, or endorsed by, the **ISPConfig project**. ISPConfig is a
  trademark of its respective owner; this is an independent, third-party theme
  and module that builds on ISPConfig and competes with nothing in it.

---

Maintained by [Wade Beckett](https://github.com/wadejbeckett) as an independent,
open-source project — contributions welcome from anyone.
