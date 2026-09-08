# ISPConfig Theme Customizer

An extension for the [ISPConfig](https://www.ispconfig.org/) control panel that
makes the panel brandable. It replaces the panel's front end with one of two
designs and adds an admin-only **Branding** page where you set the logo, panel
name, accent colour and login details, instead of editing files by hand. The
two designs are `clarity`, a dark and light interface built on VMware Clarity's
design tokens, and `classic`, the stock ISPConfig look with the same branding
applied; both read the same settings, so the Branding page drives either one.
Everything it installs is additive — a theme directory under
`interface/web/themes/` and a module directory under
`interface/web/customizer/`. No ISPConfig core file is modified and no database
schema is added. Install is `git clone` and `./install.sh`; re-run the
installer after every ISPConfig release, including patch releases.

![Dashboard](mockup/shots/dark-dashboard-desktop.png)

| | |
|---|---|
| ![Websites list](mockup/shots/dark-sites-desktop.png) | ![Login](mockup/shots/dark-login-desktop.png) |
| ![Light dashboard](mockup/shots/light-dashboard-desktop.png) | ![Branding page](docs/screenshots/branding-page-dark.png) |

Those are the clarity design; `mockup/shots/default-desktop.png` is the stock
panel for comparison, which is the shape classic keeps.

## The two designs

They are alternatives, not layers: pick one with `--design`, or install both
and let each user choose in the Design picker under *Tools → User Settings*.

### clarity — the default

Brand rail, topbar, card surfaces and restyled tables, forms, tabs and modals,
applied by CSS to the stock markup rather than by rewriting pages. Dark and
light, with an in-panel switcher stored in `localStorage`. Clarity icon shapes
replace the legacy `ispconfig` icon font, the Bootstrap glyphicons and the
FontAwesome 4 glyphs, inlined as CSS masks, and **Inter** is self-hosted: no
external font, script or CDN request is added. Vendor CSS and JS still load
from `themes/default`, so that directory must remain present.

It overrides **seven templates** and nothing else: three shell templates
(`main.tpl.htm`, `main_login.tpl.htm`, `topnav.tpl.htm`) and four dashboard
dashlets (`dashboard.htm`, `modules.htm`, `metrics.htm`, `donate.htm`). Every
other page renders from the stock `default` theme, styled by CSS alone. All
seven are pinned, with the contracts each preserves, in
[`themes/clarity/BUILT-AGAINST.txt`](themes/clarity/BUILT-AGAINST.txt) — the
file to re-check after a panel upgrade.

### classic — the stock look, made brandable

Stock layout and stock stylesheets, with the logo, panel name, accent colour,
navigation band and login background applied on top. It has no CSS, fonts or
images of its own; every asset is served from `themes/default/assets/`.

Its two shell templates (`main.tpl.htm`, `main_login.tpl.htm`) are **generated
at install time** from the target panel's own `themes/default/templates/`,
which is why there is no `templates/` directory under `themes/classic/` in the
repository. `install.sh` applies four mechanical changes — pin asset paths to
`themes/default/assets/`, link `brand.php`, `title.php` and `favicon.php`
before `</head>`, replace stock's icon links with the `favicon.php` one, and
split the stock footer credit into two addressable spans — and aborts rather
than deploy a template it cannot account for. The installer run already
required after a panel upgrade regenerates the shell from the new stock markup,
so there is nothing to diff by hand. Details in
[`themes/classic/README.md`](themes/classic/README.md).

## The Branding page

One admin-only page, labelled **Branding** in the top navigation (`customizer`
is only the directory name). It writes core's own settings — the existing
`sys_ini` row, plus the `sys_config` row ISPConfig's own Hide button uses for
the donation dashlet — and creates no tables and no columns. It ships in seven
locales: English, German, French, Spanish, Italian, Dutch and Portuguese.

A full-width preview at the top shows the panel and the login screen side by
side, with the supplied marks, the panel name and the three colours as measured
values in a legend under them; the settings follow in five cards with a sticky
save bar. Colours, the panel name and the contrast readouts update as you type,
and which logo a surface ends up with is worked out by the server using the
same code the panel uses, so the preview cannot promise a mark the panel will
not render.

**Some of it works with no design installed**, because ISPConfig core reads
these values itself — which is why `install.sh --module` is a real option on a
panel staying on the stock `default` theme:

| Setting | Where core reads it |
|---|---|
| `custom_logo` (the light-background logo uploader) | login page and panel header |
| `company_name` | browser title prefix |
| `custom_login_text` / `custom_login_link` | the extra line on the login screen |
| `dashboard_atom_url_admin` / `_reseller` / `_client` | the per-role dashboard news feed |
| `hide_donation_dashlet` (`sys_config`) | the donation appeal on the admin dashboard; core's own Hide button writes the same row |

The uploader exists because the stock panel's own logo upload is currently
non-functional; the field it writes is ISPConfig's, not a new one. For the
stock look with every option live, install `--design=classic` rather than
`--module`.

**The rest needs a brand-aware design** — clarity or classic, or anything else
adopting the [contract below](#the-brand-token-contract):

| Setting | Effect |
|---|---|
| `accent_hex` | re-hues the blue ramp and accents |
| `rail_hex` | the main navigation band: clarity's brand rail, classic's navbar |
| `rail_hex_light` | the same band in light colour mode. Unset, `rail_hex` is used in both modes |
| `login_bg` | login-screen background base |
| `logo_url` | light-background logo by reference; wins over the uploaded `custom_logo` |
| `logo_on_dark` | dark-background logo, uploaded as a data URI |
| `logo_url_on_dark` | dark-background logo by reference; wins over `logo_on_dark` |
| `logo_variant_nav`, `logo_variant_login` | which mark each surface uses; unset means automatic |
| `favicon` | the tab icon, uploaded as a data URI (SVG, PNG or ICO, under 15 KB) |
| `favicon_url` | the tab icon by reference; wins over `favicon` |
| `show_version` | hides the version surfaces on the Help page — read [Version disclosure](#version-disclosure) first |
| `show_design_picker` | hides the Design drop-down under *Tools → User Settings*. Cosmetic, like `show_version`: it applies to every role, and the setting stays writable by a crafted request |
| `show_ispconfig_credit`, `show_theme_credit` | the two footer courtesy lines |

**The two logos are named after the background they sit on.** Clarity's rail is
dark and wants a light mark, classic's header is stock's `#f2f5f7` and wants a
dark one, so a single logo cannot serve both. Each surface asks for the variant
matching its own background and falls back to the other when that variant is
unset, so a panel with only the historical `custom_logo` renders as it always
did; within a variant, a reference beats an upload. Left on **Automatic**, the
navigation bar picks by `rail_hex` (and `rail_hex_light` in light mode) and the
login screen by `login_bg`. Because those two settings can falsify a design's
assumption, `logo_variant_nav` and `logo_variant_login` pin either surface to a
named mark. On classic neither colour reaches a logo, so Automatic stays on the
light-background mark there.

`logo_on_dark` and `favicon` are stored in `sys_ini.config` rather than in
columns, so each is re-read whenever the panel loads a global setting and is
journalled into `sys_datalog` by the next save. The column is `longtext`, so
nothing truncates, and the upload caps bound it: 45 KB for the logo, 15 KB for
the favicon. `logo_url_on_dark` and `favicon_url` store a path instead. The tab
icon is served by `themes/<design>/favicon.php`; with nothing stored it falls
back to the design's shipped icon, so an unbranded panel looks as it did.

Both footer toggles work on both designs and default to **on**, and neither
touches a licence notice. The donation dashlet switch also defaults to on; it
is admin-only in core, so it concerns your own dashboard rather than what
customers see.

## Install

ISPConfig's *System → Extension Installer* lists only what is in
[repo.ispconfig.com](https://repo.ispconfig.com/api/v1/list/), so this
extension is not available there. Clone somewhere the web server can read —
**not `/root`**, which is mode 700:

```bash
cd /opt
git clone https://github.com/wadejbeckett/ispconfig-theme-customizer.git
cd ispconfig-theme-customizer
sudo ./install.sh
```

```
./install.sh [--theme|--module|--all] [--design=<name>] [--copy]
             [--no-assign] [ISPCONFIG_ROOT]
```

- With no component flag, both halves are installed. `--theme` installs only
  the design; `--module` installs only the Branding page.
- `--design=<name>` takes `clarity`, `classic` or `all`, and is repeatable. It
  is a separate axis from `--theme`/`--module`/`--all`: those pick which
  halves, this picks which design the theme half means. Default is `clarity`.
- `--copy` copies real files instead of symlinking. With symlinks the panel
  reads from the clone, so the clone and every parent directory must stay
  traversable by the web server; the installer warns if they are not.
- `--no-assign` skips assigning the module to admin users; do it by hand in
  *System → CP Users → edit the admin user → Modules*.
- `ISPCONFIG_ROOT` defaults to `/usr/local/ispconfig`.

On a multiserver setup, install only on the server that serves the web
interface. Then pick the design per user in *Tools → User Settings → Design*.
To set the system-wide default and the login screen, add
`$conf['theme'] = 'clarity';` to **both** `interface/lib/config.inc.php` and
`server/lib/config.inc.php`; `install.sh` never edits ISPConfig configuration,
and the server file is what makes the value persist across panel updates.

## After any ISPConfig upgrade

Re-run the installer after every panel upgrade, patch releases included
(`3.3.1p1` → `3.3.1p2`), passing the same `--design` and `--copy` flags as
before. Core compares the stamped `ispconfig_version` against
`ISPC_APP_VERSION` as an exact string, and resets affected users to the default
theme on any mismatch. After a **major** upgrade, also diff clarity's seven
overridden templates against the new stock ones; classic needs no such diff.
Full procedure in [UPGRADING.md](UPGRADING.md).

## Compatibility

**ISPConfig 3.3**, developed and verified against 3.3.1p1: clarity's template
overrides are pinned to that version's stock markup, and classic's are
generated from whatever stock markup the panel has. **ISPConfig 3.2 is not
verified** — it has not been tested, so treat it as unknown. Also needed: root
shell access to the panel server, the stock `default` theme still present, and
PHP CLI for the module-assignment and cleanup helpers (without it the installer
prints the manual equivalent).

## Version disclosure

Read this before deploying on a public panel. ISPConfig's theme gate requires
the file `themes/<theme>/ispconfig_version` inside the panel's web root, under
that exact name, so the web server serves it as an ordinary static file: anyone
who can reach the login page learns the exact version and patch level, with no
session and no credentials. That is not stock behaviour, it arrives with any
third-party theme including this one, and it undercuts the `show_version`
toggle. The fix belongs at the web-server layer; nginx and Apache snippets and
the full explanation are in
[`contrib/webserver/`](contrib/webserver/README.md).

## Uninstall

```
./uninstall.sh [--theme|--module|--all] [--design=<name>] [--reset-users]
               [--purge-branding] [--keep-assignment] [ISPCONFIG_ROOT]
```

Same component flags as the installer, but `--design` defaults to **all
designs** rather than to clarity, because removal has to clear whatever might
be on the panel. The other defaults are conservative: `--reset-users` clears
`sys_user.app_theme` rows still pointing at a removed design (without it,
affected users get a "theme not compatible" banner at every login), and
`--purge-branding` wipes the stored branding values, which are otherwise left
intact and editable under *System → Interface Config*. Nothing here edits
ISPConfig configuration, so `$conf['theme']` is yours to revert; `uninstall.sh`
warns before removing anything if it is still set to a design.

## The brand-token contract

The Branding page writes, and a design reads, the `[branding]` and `[misc]`
keys in the tables above, in the global `sys_ini` row (`sysini_id = 1`). That
is the entire coupling: no shared code, no API. Anything reading the same keys
inherits the whole Branding page, and CI fails the build if a key the page
writes is not read back. The two implementations,
[`themes/clarity/brand.php`](themes/clarity/brand.php) and
[`themes/classic/brand.php`](themes/classic/brand.php), are read-only pre-auth
stylesheet endpoints that query one row, emit CSS, and do nothing when nothing
is set. Which ISPConfig self-identification surfaces can be overridden from
inside that envelope is listed in
[docs/OVERRIDE-SURFACES.md](docs/OVERRIDE-SURFACES.md).

On clarity you can also brand by file swap and store nothing: replace
`themes/clarity/assets/images/wordmark-white.svg` and drop your own icons into
`themes/clarity/assets/favicon/`. Stored values override both.

## Contributing

Bug reports, fixes and ideas are welcome. [CONTRIBUTING.md](CONTRIBUTING.md)
covers how it is put together, the ground rules that keep it upgrade-safe, and
how to test a change. Security reports: [SECURITY.md](SECURITY.md).

## Support this project

Free and MIT-licensed. If it saves you time, donations are taken in Monero:

```text
44BtMn9izxH8mK2yFbSdY6Di7TNobkLbnHdZ6gZQjukCME5vsNhtPRtH4TcVkDHKHLhSpAJbsjv8gCdYuSZVMpXgMkUC1hV
```

Code is as welcome as coin.

### Support ISPConfig itself

This project sits on top of ISPConfig rather than replacing any part of it.
ISPConfig takes no direct donations; the ways its developers ask to be
supported are the
[manual](https://www.ispconfig.org/documentation/user-manual/) (€5) or a
[HowtoForge subscription](https://www.howtoforge.com/download-the-ispconfig-3-manual),
[Business Support](https://www.ispconfig.org/get-support/) for paid help, the
commercial tools that fund the free panel
([ISPProtect](https://www.ispprotect.com/) and the
[Migration Tool](https://www.ispconfig.org/add-ons/ispconfig-migration-tool/)),
and contributing upstream at
[git.ispconfig.org](https://git.ispconfig.org/ispconfig/ispconfig3) or the
[HowtoForge forum](https://forum.howtoforge.com/).

## Licence and attribution

- This project: [MIT](LICENSE).
- **VMware Clarity (`@cds/core`, MIT).** Surface and status values are derived
  from Clarity's dark theme tokens, and 29 Clarity icon shapes are bundled
  verbatim as data-URI SVG masks in
  `themes/clarity/assets/stylesheets/clarity/icons.css`. That is redistribution
  of a substantial portion of the upstream work, so the MIT copyright and
  permission notice ships in that file's header. Keep the header with the
  shapes if you vendor the theme.
- **Inter** — [SIL OFL 1.1](themes/clarity/assets/fonts/inter/LICENSE.txt),
  self-hosted.
- ISPConfig is BSD-licensed. This project ships no ISPConfig code and modifies
  none.
- Not affiliated with, or endorsed by, **VMware** or the **ISPConfig
  project**. ISPConfig is a trademark of its respective owner; this is an
  independent, third-party front end that builds on it.

---

Maintained by [Wade Beckett](https://github.com/wadejbeckett) as an
independent, open-source project. Contributions welcome from anyone.
