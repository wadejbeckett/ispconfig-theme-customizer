# Contributing to ispconfig-theme-customizer

Bug reports, fixes, and ideas are all welcome. This page is the practical
guide; the design language itself (tokens, surfaces, component rules) lives in
[DESIGN.md](DESIGN.md).

This is one product — a modern, brandable front-end for ISPConfig, with an admin
page where you set your logo, panel name and colours. The codebase has two
areas, and working out which one your change belongs in is usually the first
thing to do:

- `themes/clarity/` — the design layer
- `interface/web/customizer/` — the **Branding** page that stores the
  white-label settings, plus the `bin/` helpers that install, remove and purge
  them

They are one contract: the Branding page writes a set of `sys_ini` keys and a
brand-aware design reads them. Clarity is the design that ships; anything else
reading the same keys inherits the Branding page for free. CI enforces that
contract, so a change on one side that drops a key fails the build rather than
silently disabling branding. If you are adding a key, add it on both sides in
the same change.

## Reporting a bug

Open a [bug report](../../issues/new/choose). The template asks for the things
that make a bug diagnosable: ISPConfig version, the release or commit you
installed, browser, dark or light mode, a screenshot, and any browser-console
errors.

One check before filing: **switch your user back to the stock `default` theme
and try again.** If the problem is still there, it's an ISPConfig issue, not a
theme issue — take it to the
[ISPConfig bugtracker](https://git.ispconfig.org/ispconfig/ispconfig3/-/issues)
or the [HowtoForge forum](https://forum.howtoforge.com/) instead.

## How it is put together

There is no build step anywhere in this repository.

### The design layer — `themes/clarity/`

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

`themes/clarity/BUILT-AGAINST.txt` records exactly which stock contracts the
theme relies on — read it before touching a template.

### The Branding page — `interface/web/customizer/`

A stock ISPConfig tform module:

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

A separate CI step, **Brand-token contract parity**, greps
`themes/clarity/brand.php` for each of the six keys the Branding page writes
(`accent_hex`, `rail_hex`, `login_bg`, `logo_url`, `show_version`,
`company_name`) and fails if one is missing. This is the check that keeps the
two sides one product, and it is what a future design would have to satisfy to
drop in. Adding a key means adding it on both sides in the same PR — and adding
it to that list, since the check reads from a hard-coded list rather than
discovering keys. It catches a key being dropped or renamed on the design side;
it does not prove the value is used correctly.

## Ground rules (what keeps it update-proof)

1. **Never modify an ISPConfig core file.** Everything ships inside
   `themes/clarity/` and `interface/web/customizer/`; the one sanctioned
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

With no component flag, `install.sh` installs both sides. Pass `--theme` to
install just the design, or `--module` to install just the Branding page, if you
are only working on one of them. `uninstall.sh` takes the same flags.

Re-run `install.sh` after **any** ISPConfig upgrade, patch releases included —
the version files the panel checks are pinned to the exact release.

The default symlink install means edits to your clone appear on the panel
immediately — just hard-refresh (`Ctrl+Shift+R`). If browsers cling to stale
CSS/JS, bump the `?ver=` query string on the asset links in
`templates/main.tpl.htm`.

Check every visual change in **dark and light mode** (the topbar toggle), and
check the **mobile drawer** if you touched the frame.

### The mockup harness (optional)

`mockup/build.py` renders the real templates with sample content, offline —
useful for screenshots and pixel-diff regression testing:

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
- Include before/after screenshots for anything visual, in both modes.
- Fill in the PR checklist (it enforces the ground rules above).
- By contributing you agree your work is licensed under the repo's
  [MIT license](LICENSE).
