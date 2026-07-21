# ISPConfig Customizer

A standalone white-label / branding module for the
[ISPConfig](https://www.ispconfig.org/) hosting control panel. Set your logo,
panel name, accent colours and login screen from an admin page in the panel —
no theme edits, no core patches, no per-account fees.

It runs on **stock ISPConfig**, with or without a custom theme. A brand-aware
theme (such as the [Clarity theme](https://github.com/wadejbeckett/clarity-theme-ispconfig))
applies the colours; the logo, panel name and login text work on the stock theme
too. Think of it as *the way you brand ISPConfig*.

Sponsored by Earlier and released free and open under the MIT licence. Use it,
change it, ship it, brand it — for yourself or your clients — no strings.

## What it does

- **Logo** — upload once (SVG, PNG, JPEG, GIF or WebP, under 45 KB); it replaces
  the panel logo everywhere (sidebar, mobile header, login). Stored in
  ISPConfig's native `custom_logo` field. SVGs are validated before storage
  (well-formed XML, `<svg>` root, no scripts / event handlers / foreignObject /
  entity declarations). One caveat inherited from core: the *stock* theme sizes
  the logo with `getimagesizefromstring()`, which can't measure SVG, so under
  the stock theme an SVG renders at its intrinsic size (and logs a PHP notice
  per login render). Brand-aware themes size the logo with CSS and are
  unaffected — if you run the stock theme, prefer an optimised PNG or WebP.
- **Panel name** — the name shown in the browser title and footer.
- **Accent colour / sidebar colour / login background** — pick a hex; a
  brand-aware theme re-skins itself to match, in both dark and light modes.
- **Login screen** — a custom footnote line and link.
- **Attribution & visibility toggles** — per-checkbox control over the
  "powered by ISPConfig" and theme courtesy lines in the footer, the dashboard
  news feed, and the Help version display. The licence notices in the source
  and docs are always kept (see *Attribution & licensing*).

Everything is stored in ISPConfig's own database (`sys_ini`), so it **survives
both ISPConfig upgrades and theme updates**, and can be pushed to many panels via
ISPConfig's remote API.

## Requirements

- ISPConfig 3.2 / 3.3 (developed and verified against 3.3.1p1).
- Admin access to the panel and shell access to the server for install.

## Install

```bash
git clone https://github.com/wadejbeckett/ispconfig-customizer.git
cd ispconfig-customizer
sudo ./install.sh            # symlink into /usr/local/ispconfig, assign to admins
# or, for a packaged copy instead of a symlink:
sudo ./install.sh --copy
```

Pass your ISPConfig root if it isn't the default:
`sudo ./install.sh /usr/local/ispconfig`. Then re-log in and open the
**Customizer** module in the top navigation.

> Symlink installs are read through the link by the web server, so the clone must
> live somewhere world-traversable (e.g. `/opt`, not `/root`). The installer warns
> you if it doesn't. Use `--copy` to avoid this entirely.

To remove it, run the uninstaller:

```bash
sudo ./uninstall.sh                    # removes the module + unassigns it from ALL users
sudo ./uninstall.sh --purge-branding   # ...and also wipes every stored branding value
```

It removes `interface/web/customizer`, strips `customizer` from every user's
module list (the installer assigns it to every admin account, and admins may
have assigned it further), and resets any start-module that pointed at it. By
default your branding values **survive** in `sys_ini` — reinstall-friendly, and
the panel name / login text stay editable under *System > Interface Config*.
`--purge-branding` drops the `[branding]` section, blanks the panel name /
login text / login link, and clears the logo. ISPConfig core is never touched.

## Brand-token contract

This is the stable interface between the customizer (which **writes** brand
intent) and any theme (which **reads** it and realises it). The two never call
each other's code — they communicate only through these keys in ISPConfig's
`sys_ini` table (global row `sysini_id = 1`). A theme that reads these keys is
"brand-aware".

| Brand intent | Where | Key | Format |
|---|---|---|---|
| Logo | `sys_ini.custom_logo` column | *(the column)* | `data:image/…;base64,…` (≤ ~45 KB raw) |
| Logo by reference | `sys_ini.config` → `[branding]` | `logo_url` | root-relative path (`/…`) or `https://` URL; no spaces/quotes/brackets/parens; wins over `custom_logo` in brand-aware themes; ignored by the stock theme and login |
| Version visibility | `sys_ini.config` → `[branding]` | `show_version` | `0`/`1` (default `1`); `0` asks brand-aware themes to hide the Help version surfaces for every user (visual hide; direct URL still answers) |
| News feed | `sys_ini.config` → `[misc]` | `dashboard_atom_url_admin` / `_reseller` / `_client` | stock core keys driven by the news-feed toggle: off blanks all three (core hides the sidebar feed on an empty URL); on restores the default feed only when all three are empty, so per-role custom URLs survive |
| Panel / product name | `sys_ini.config` → `[misc]` | `company_name` | text |
| Login footnote text | `sys_ini.config` → `[misc]` | `custom_login_text` | text |
| Login footnote link | `sys_ini.config` → `[misc]` | `custom_login_link` | `http(s)://…` |
| Accent colour | `sys_ini.config` → `[branding]` | `accent_hex` | `#RRGGBB` |
| Sidebar / rail colour | `sys_ini.config` → `[branding]` | `rail_hex` | `#RRGGBB` |
| Login background | `sys_ini.config` → `[branding]` | `login_bg` | `#RRGGBB` |
| Show "powered by ISPConfig" | `sys_ini.config` → `[branding]` | `show_ispconfig_credit` | `0` / `1` (default `1`) |
| Show theme credit | `sys_ini.config` → `[branding]` | `show_theme_credit` | `0` / `1` (default `1`) |

Notes for theme authors:

- **The keys are semantic, not theme-specific.** `accent_hex` is "the brand
  colour" — your theme decides how to apply it (Clarity, for example, re-hues its
  colour ramp onto that hue in both light and dark). This keeps the contract
  portable across themes.
- **`company_name`, `custom_login_text/link` are existing ISPConfig core keys** —
  the stock panel already reads them, so they work with no theme changes.
- **Read the values yourself, side-effect-free.** The Clarity theme ships a small
  `brand.php` that does a single read-only query of the `sys_ini` row and emits a
  stylesheet; it needs no session, so it works on the login screen too. See that
  file for a reference reader.
- **Degrade to nothing.** When a key is empty, emit nothing for it and fall back
  to your theme's own defaults — so the panel looks right before anything is set.

The `[branding]` keys are also settable over ISPConfig's remote API via
`system_config_set($session, 'branding', '<key>', '<value>')`, which is how you'd
brand a fleet of panels programmatically. (The logo column is not remote-writable
by the stock API.)

## Attribution & licensing

You can turn off the visible courtesy lines ("powered by ISPConfig", the theme
credit) from the Customizer page — brand the panel right down to no visible
third-party credit.

What you **cannot** do from the UI — by design — is remove the open-source licence
notices. ISPConfig is [BSD-3-Clause](https://www.ispconfig.org/) and its notice
stays in the source tree and documentation; this module is MIT and its notice
stays too. The toggles only hide optional courtesy text in the interface; they
never touch a licence file. So every build you can produce from the UI is
licence-compliant.

Using it yourself, leaving the "powered by ISPConfig" line on is a nice
good-citizen signal — but it's your call.

## About Earlier

Earlier is the official sponsor of the Clarity theme for ISPConfig and offers
commercial support for ISPConfig as an open-source control panel — branded
deployment, SLAs, and upstream fixes. Earlier is a supporter and contributor, never
a gatekeeper: everything here is open, and you're free to use it with or without
us. If you'd like a supported, branded ISPConfig for your hosting business,
that's what we do.

## Support this project

This module is free and open source. If it saves you time and you'd like to say
thanks, donations are taken in Monero:

```text
44BtMn9izxH8mK2yFbSdY6Di7TNobkLbnHdZ6gZQjukCME5vsNhtPRtH4TcVkDHKHLhSpAJbsjv8gCdYuSZVMpXgMkUC1hV
```

### Support ISPConfig itself

None of this exists without ISPConfig. The project takes no direct donations; the
way its developers ask to be supported is to buy the
[ISPConfig manual](https://www.ispconfig.org/documentation/user-manual/) or a
[HowtoForge subscription](https://www.howtoforge.com/download-the-ispconfig-3-manual),
use their commercial tools ([ISPProtect](https://www.ispprotect.com/), the
[Migration Tool](https://www.ispconfig.org/add-ons/ispconfig-migration-tool/)), or
contribute upstream at [git.ispconfig.org](https://git.ispconfig.org/ispconfig/ispconfig3).

## License

[MIT](LICENSE) © Wade Beckett. Built for ISPConfig; not affiliated with or
endorsed by the ISPConfig project. ISPConfig is a trademark of its owners.
