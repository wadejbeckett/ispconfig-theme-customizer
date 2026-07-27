# Contributing to ispconfig-theme-customizer

Bug reports, fixes, and ideas are all welcome. This page is the practical
guide; the design language of the `clarity` design (tokens, surfaces, component
rules) lives in [DESIGN.md](DESIGN.md).

This is one extension — a brandable front-end for ISPConfig, with an admin page
where you set your logo, panel name and colours. ISPConfig calls third-party
add-ons *extensions*, and that is what this is. It installs an ISPConfig
**module** called `customizer`, which appears in the top navigation as
**Branding**; "module" in this document always means that ISPConfig mechanism,
never the project itself.

The codebase has two areas, and working out which one your change belongs in is
usually the first thing to do:

- `themes/` — the design layer (`clarity` and `classic`)
- `interface/web/customizer/` — the **Branding** page that stores the
  white-label settings, plus the `bin/` helpers that install, remove and purge
  them

They are one contract: the Branding page writes a set of `sys_ini` keys and a
brand-aware design reads them. Two designs read them today; anything else
reading the same keys inherits the Branding page for free. CI enforces that
contract, so a change on one side that drops a key fails the build rather than
silently disabling branding. If you are adding a key, add it on both sides in
the same change.

## Reporting a bug

Open a [bug report](../../issues/new/choose). The template asks for the things
that make a bug diagnosable: ISPConfig version, the release or commit you
installed, browser, dark or light mode, a screenshot, and any browser-console
errors. It does not yet ask which design you are on — say so in the
description, because clarity and classic fail in different ways.

One check before filing: **switch your user back to the stock `default` theme
and try again.** If the problem is still there, it's an ISPConfig issue, not an
issue with this extension — take it to the
[ISPConfig bugtracker](https://git.ispconfig.org/ispconfig/ispconfig3/-/issues)
or the [HowtoForge forum](https://forum.howtoforge.com/) instead.

## How it is put together

There is no build step anywhere in this repository.

### The design layer — `themes/`

Two designs ship, and which one you are looking at changes the rules:

| Design | What it is |
|---|---|
| `themes/clarity/` | A ground-up dark and light interface: six overridden templates, its own CSS, fonts and icons, every stock contract pinned in `BUILT-AGAINST.txt`. |
| `themes/classic/` | The stock ISPConfig look, made brandable. `brand.php`, `title.php` and a README — no stylesheets, no images, no fonts, and its two shell templates are **generated at install time**, not committed. |

Both read the same brand keys from the same Branding page, and every option on
that page works on both.

The design is a separate axis from the two halves: `--theme`/`--module`/`--all`
choose which halves get installed, `--design=` chooses which design the theme
half means. It takes `clarity`, `classic` or `all`, and is repeatable.
`install.sh` defaults to `clarity` — a second design never appears in
ISPConfig's theme picker unless you ask for it — while `uninstall.sh` defaults
to *all* designs, because uninstalling has to clear whatever might be there.

#### `themes/clarity/`

Six template overrides plus plain CSS and one JS file, loaded in this order:

| File | Role |
|---|---|
| `themes/clarity/templates/` | The three shell templates: `main.tpl.htm` (app frame), `topnav.tpl.htm` (module rail), `main_login.tpl.htm` (login scene). |
| `themes/clarity/templates/dashboard/` | Three dashlet overrides: `dashboard.htm`, `modules.htm`, `metrics.htm`, replacing `interface/web/dashboard/templates/dashboard.htm` and `interface/web/dashboard/dashlets/templates/{modules,metrics}.htm`. |

Those six are the complete set — everything else falls back to
`themes/default`. Each one's contract with the stock template it replaces (the
`tmpl_var`s it must provide, the click handlers it must preserve) is pinned in
`themes/clarity/BUILT-AGAINST.txt`, and CI fails if an override is added
without a matching entry there.

| File | Role |
|---|---|
| `assets/stylesheets/clarity/tokens.css` | **The design DNA.** Every color, radius, and shadow as a semantic `--nz-*` token. Dark values at `:root`, light mode as a pure remap block on `:root[data-nz-theme='light']`. |
| `assets/stylesheets/clarity/icons.css` | Clarity icon shapes as CSS `mask` data-URIs, tinted by `currentColor`. Generated file — edit with care. |
| `assets/stylesheets/clarity/base.css` | Functional port of stock `ispconfig.css` — layout/behavior rules the panel's JS depends on, none of its looks. |
| `assets/stylesheets/clarity/app.css` | The frame: rail, topbar, sidebar, drawer, login-adjacent chrome. |
| `assets/stylesheets/clarity/components.css` | Everything inside the content pane: tables, forms, buttons, alerts, tabs, select2, datetimepicker, charts. |
| `assets/stylesheets/clarity/login.css` | The login scene — loaded only by `main_login.tpl.htm` (with `tokens.css`), not by the app frame. |
| `assets/javascripts/nz-theme.js` | Progressive enhancement only: theme switcher, Chart.js theming, drawer/search/a11y behavior. The panel works with it absent. |

`themes/clarity/BUILT-AGAINST.txt` records exactly which stock contracts this
design relies on — read it before touching a template.

#### `themes/classic/`

Three files in the repository, and nothing else:

| File | Role |
|---|---|
| `themes/classic/brand.php` | The brand reader. Emits a stylesheet that overrides **stock's own** selectors (`theme.min.css`, `ispconfig.css`, Bootstrap 3.3.0), keeping stock's lightness for each role so its contrast relationships survive a re-brand. Also accepts `?scene=login` for the handful of rules that must apply to the login screen alone. |
| `themes/classic/title.php` | The tab title, plus the alt text core never sets on the login logo. |
| `themes/classic/README.md` | Why it exists and how the shell is built. |

**Do not hand-edit `themes/classic/templates/`, and do not commit it.**
`install.sh` generates those two files — `main.tpl.htm` and
`main_login.tpl.htm` — from the *target panel's own*
`themes/default/templates/`, applying exactly three mechanical changes:

1. every `themes/<tmpl_var name='current_theme'>/assets/` path is pinned to
   `themes/default/assets/` (template fallback covers templates, not assets, so
   without this every stylesheet, script and icon on the page 404s);
2. `brand.php` and `title.php` are linked immediately before `</head>`;
3. the app frame's footer credit line is split into a `.nzc-credit-ispconfig`
   span around core's own text and an appended `.nzc-credit-theme` span, so the
   two footer-credit toggles on the Branding page have a target each. Both ship
   ON, and neither touches a licence notice.

Everything else is byte-for-byte stock, and the generator verifies that —
line counts, no surviving `current_theme` reference, both endpoints present —
aborting rather than deploying a shell it cannot account for.

Generating beats committing because ISPConfig looks in
`themes/<active>/templates` first and falls back to `themes/default/templates`
(`interface/lib/classes/tpl_ini.inc.php`): a design that overrides only the
shell inherits every other template from whatever version is installed, whereas
a committed copy would freeze one release's markup and diverge from the panel
silently. Re-running `install.sh` after an ISPConfig upgrade — already mandatory
for the version stamp — regenerates the shell in the same pass. This is why
browsing the repository shows no `templates/` under `themes/classic/`.

The ignore rules for the generated files live in the **repository-root**
`.gitignore`, not in a `themes/classic/.gitignore`: that directory is deployed
verbatim into the panel's web root, and a dotfile naming `install.sh` and
`templates/` would fingerprint the extension on a panel whose whole point is to
carry someone else's brand. A CI step enforces the same thing from the other
end — `themes/classic/` may contain only `brand.php`, `title.php`, `README.md`
and `.gitignore`, so a stray stylesheet or a committed `templates/` fails the
build.

Changing classic means changing `brand.php`, or the generator in `install.sh` —
never a file under `templates/`, which the next install run overwrites.

### The Branding page — `interface/web/customizer/`

A stock ISPConfig tform module — this is the `customizer` module the extension
installs, listed in `sys_user.modules` and declared by `lib/module.conf.php`:

| File | Role |
|---|---|
| `interface/web/customizer/customizer_edit.php` | The Branding settings page: reads and writes the `[branding]` keys in `sys_ini.config`. |
| `interface/web/customizer/logo_upload.php`, `logo_delete.php` | The logo endpoints, writing `sys_ini.custom_logo`. |
| `interface/web/customizer/lib/svg_guard.inc.php` | The SVG upload screen. Its adversarial corpus is `tests/svg/run.php` — run it before and after any change here. |
| `interface/web/customizer/form/`, `templates/` | tform definition and page markup. |
| `bin/` | Install/uninstall helpers: `assign_module.php`, `unassign_module.php`, `purge_branding.php`, `reset_app_theme.php`. |

**Any new endpoint opens with the same three admin checks**, in this order —
`check_module_permissions('customizer')`, then
`check_security_permissions('admin_allow_system_config')`, then an
`is_admin()` guard that dies. See [SECURITY.md](SECURITY.md) for why all three
are needed rather than any one of them.

#### Translations

Three wordbooks ship, and three different core code paths load them:

| Wordbook | Loaded by |
|---|---|
| `interface/web/customizer/lib/<lang>.lng` | `nav.php` merges it on every page for the top-menu title, and `lib/module.conf.php` reads it for the dashboard launcher tile. |
| `interface/web/customizer/lib/lang/<lang>.lng` | auto-loaded by `app.inc.php` inside the module. |
| `interface/web/customizer/lib/lang/<lang>_customizer.lng` | the tform wordbook for the settings form. |

`.github/scripts/lang_check.php` runs in CI and enforces two rules that bite
translators in particular:

- **Key parity** against each wordbook's English source. ISPConfig
  *substitutes* wordbooks rather than merging them — it only falls back to
  `en.lng` when the per-language file is **absent** — so a file that exists but
  omits a key makes the raw key name render in the UI.
- **An 8-character budget on `top_menu_customizer`.** That string is the nav
  label — `Branding` in English — and it drives both the top nav and the
  dashboard launcher tile, where the core dashlet truncates anything longer to
  7 characters plus `..`.

The script parses `.lng` files as text and never `include()`s them — they are
PHP, and they arrive through pull requests. Keep it that way.

A separate CI step, **Brand-token contract parity**, greps *every*
`themes/*/brand.php` for each of the six keys on CI's hard-coded contract list
(`accent_hex`, `rail_hex`, `login_bg`, `logo_url`, `show_version`,
`company_name` — the Branding page writes more than these; the list is the
subset a design must read) and fails if one is missing. The loop walks the directory rather
than naming a design on purpose: both `clarity` and `classic` have to satisfy
it, and a third design must not quietly opt out. This is the check that keeps
the two sides one product. Adding a key means adding it to every design in the
same PR — and adding it to that list, since the check reads from a hard-coded
list rather than discovering keys. It catches a key being dropped or renamed on
the design side; it does not prove the value is used correctly.

## Ground rules (what keeps it update-proof)

Rule 1 applies everywhere. Rules 2, 3 and 7 are about clarity's stylesheets;
classic ships none, and its equivalent rule is that `brand.php` may only
override stock's own selectors — it must never require a change to stock
markup, because the shell it runs against is regenerated from stock on every
install.

1. **Never modify an ISPConfig core file.** Everything ships inside
   `themes/<design>/` and `interface/web/customizer/`; the one sanctioned
   exception is the documented `$conf['theme']` line users set themselves.
2. **Stylesheets read only semantic tokens.** No hard-coded colors outside
   `tokens.css`. Need a new color? Add a token.
3. **Every new token needs a light-mode value** in the remap block — except
   `--nz-rail-accent`, which is deliberately never remapped (the navy rail is
   constant in both modes).
4. **Vendor assets are referenced explicitly from `themes/default/…`.**
   ISPConfig templates fall back to the default theme, but static assets do
   not — never assume an asset fallback exists.
5. **Preserve the JS shell contract** (`#pageContent` inside
   `form#pageForm`, `#topnav-container`/`#sidebar` AJAX sinks, the pushy
   drawer elements, `data-capp`/`data-icon-class` on module links). Breaking
   these breaks navigation in ways CSS can't show you.
6. **Keep WCAG AA contrast.** If you change a foreground/background pair,
   state the computed ratio in your PR.
7. **Icons are Clarity shapes as CSS masks**, inlined as data-URIs in
   `icons.css` and tinted by `currentColor` — never a new icon font, never an
   emoji. The stock icon fonts (FontAwesome 4, Bootstrap Icons) are still
   loaded as vendor CSS by the shell templates, because core markup emits their
   class names; `icons.css` overrides the glyphs those classes render. Adding a
   Clarity icon costs no extra request — adding a font would.

## Developing and testing a change

```bash
git clone https://github.com/wadejbeckett/ispconfig-theme-customizer.git
cd ispconfig-theme-customizer
./install.sh /usr/local/ispconfig     # on a TEST panel, not production
```

Installing is a git clone and `./install.sh` — that is the only supported route
today. ISPConfig's own **System > Extension Installer** screen lists what
`https://repo.ispconfig.com/api/v1/list/` serves, which is ISPConfig UG's
curated repository, so this extension is not installable from that screen.

```
./install.sh   [--theme|--module|--all] [--design=<name>] [--copy]
               [--no-assign] [ISPCONFIG_ROOT]
./uninstall.sh [--theme|--module|--all] [--design=<name>] [--reset-users]
               [--purge-branding] [--keep-assignment] [ISPCONFIG_ROOT]
```

With no component flag, `install.sh` installs both halves. Pass `--theme` to
install just the design, or `--module` to install just the Branding page, if you
are only working on one of them. Add `--design=classic` (or `--design=all`) when
your change touches the stock-look design; bare `./install.sh` still means
clarity, exactly as it did before classic existed.

Re-run `install.sh` after **any** ISPConfig upgrade, patch releases included —
the version files the panel checks are pinned to the exact release, and for
classic the same run regenerates the shell from the new stock templates.

The default symlink install means edits to your clone appear on the panel
immediately — just hard-refresh (`Ctrl+Shift+R`). If browsers cling to stale
CSS/JS, bump the `?ver=` query string on the asset links in
`themes/clarity/templates/main.tpl.htm`. Classic's `brand.php` and `title.php`
are live through the symlink in the same way; its `templates/` are not — they
are written into your clone by the installer, so changes to the generator only
take effect on the next `install.sh` run.

On clarity, check every visual change in **dark and light mode** (the topbar
toggle), and check the **mobile drawer** if you touched the frame. On classic
there is no mode toggle — check it instead with a non-default accent and rail
colour set on the Branding page, and check the login screen separately, since
`brand.php?scene=login` emits rules the app scene never sees.

### The mockup harness (optional)

`mockup/build.py` renders clarity's real templates with sample content, offline
— useful for screenshots and pixel-diff regression testing. It does not cover
classic, whose shell only exists after an install against a real panel.

```bash
git clone https://git.ispconfig.org/ispconfig/ispconfig3.git .refs/ispconfig3
pip install playwright && playwright install chromium
python3 mockup/build.py --shoot      # writes mockup/shots/*.png
```

Renders are deterministic, so before/after runs can be compared with
ImageMagick (`compare -metric AE old.png new.png diff.png`) — zero differing
pixels means a refactor changed nothing visually.

## Submitting a pull request

- Keep PRs small and focused — one fix or one feature.
- Include before/after screenshots for anything visual — both modes for
  clarity, and both designs if the change touches the shared brand contract.
- Fill in the PR checklist (it enforces the ground rules above).
- By contributing you agree your work is licensed under the repo's
  [MIT license](LICENSE).
