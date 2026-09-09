# Contributing to ispconfig-theme-customizer

Bug reports, fixes and ideas are welcome. This is the practical guide; the design language of the `clarity` design lives in [DESIGN.md](DESIGN.md), and what the extension is and how to install it is in [README.md](README.md).

## Where a change belongs

The codebase has two areas, and working out which one a change belongs in is the first thing to do:

- `themes/` — the design layer, `clarity` and `classic`.
- `interface/web/customizer/` — the **Branding** page that stores the white-label settings, plus the `bin/` helpers that install, remove and purge them.

They are one contract: the Branding page writes a set of `sys_ini` keys and a brand-aware design reads them. Two designs read them today; anything else reading the same keys inherits the Branding page. CI enforces that contract, so a change on one side that drops a key fails the build rather than silently disabling branding. **Adding a key means adding it on both sides, and to CI's contract list, in the same change.**

"Module" in this document always means the ISPConfig mechanism — the `customizer` module, listed in `sys_user.modules` and declared by `lib/module.conf.php`, which appears in the top navigation as **Branding**. There is no build step anywhere in this repository.

### `themes/clarity/`

Seven template overrides, plain CSS and one JS file. The three shell templates are `main.tpl.htm` (app frame), `topnav.tpl.htm` (module rail) and `main_login.tpl.htm` (login scene); the four dashboard overrides live under `templates/dashboard/`. Everything else falls back to `themes/default`. Each override's contract with the stock template it replaces — the `tmpl_var`s it must provide, the click handlers it must preserve — is pinned in `themes/clarity/BUILT-AGAINST.txt`, and CI fails if an override is added without a matching entry there. Read that file before touching a template.

The stylesheets load in this order:

| File | Role |
|---|---|
| `tokens.css` | Every colour, radius and shadow as a semantic `--nz-*` token. Dark values at `:root`, light mode as a remap block on `:root[data-nz-theme='light']`. |
| `icons.css` | Clarity icon shapes as CSS `mask` data-URIs, tinted by `currentColor`. Generated — edit with care. |
| `base.css` | Functional port of stock `ispconfig.css`: the layout and behaviour rules the panel's JS depends on, none of its looks. |
| `app.css` | The frame: rail, topbar, sidebar, drawer. |
| `components.css` | Everything inside the content pane: tables, forms, buttons, alerts, tabs, select2, datetimepicker, charts. |
| `login.css` | The login scene, loaded only by `main_login.tpl.htm`. |
| `assets/javascripts/nz-theme.js` | Progressive enhancement only: theme switcher, Chart.js theming, drawer, search, accessibility behaviour. The panel works with it absent. |

### `themes/classic/`

Four files in the repository and nothing else: `brand.php` (the brand reader, which overrides stock's own selectors and accepts `?scene=login`), `title.php`, `favicon.php` and a README. CI enforces that list, so a stray stylesheet or a committed `templates/` fails the build.

**Do not hand-edit `themes/classic/templates/`, and do not commit it.** `install.sh` generates those two shells from the target panel's own `themes/default/templates/`, applying four mechanical changes; the reasoning and the list are in [`themes/classic/README.md`](themes/classic/README.md). Changing classic means changing `brand.php`, or the generator in `install.sh` — never a file under `templates/`, which the next install run overwrites.

### `interface/web/customizer/`

A stock ISPConfig tform module.

| File | Role |
|---|---|
| `customizer_edit.php` | The Branding settings page: reads and writes the `[branding]` keys in `sys_ini.config`. |
| `logo_upload.php`, `logo_delete.php` | The brand-image endpoints. Three slots share them: `on_light` writes `sys_ini.custom_logo`, `on_dark` writes `[branding] logo_on_dark`, `favicon` writes `[branding] favicon`. The slot is allowlisted, never taken raw. Only the accepted formats and the size cap vary by slot; CSRF, MIME sniffing, the SVG screen and demo mode are shared. |
| `preview.php` | The live preview: admin-only and read-only. Takes the form's unsaved values as POST and returns JSON built by `lib/preview.inc.php`. It exists so the logo-variant resolver stays in PHP; a JavaScript copy would sit outside the three-copies-agree guarantee CI enforces. `tests/brand/probe_preview.php` proves it writes nothing. |
| `lib/preview.inc.php` | The brand-image model in one place: slot vocabulary, source resolution (the two logo variants with their cross-variant fallback, and the favicon), the ICO structural check, and the preview renderers. `brand.php` and `favicon.php` mirror its resolution rules and must change with it. |
| `lib/svg_guard.inc.php` | The SVG upload screen. Its adversarial corpus is `tests/svg/run.php` — run it before and after any change here. |
| `form/`, `templates/` | tform definition and page markup. |

**Any new endpoint opens with the same three admin checks**, in this order: `check_module_permissions('customizer')`, then `check_security_permissions('admin_allow_system_config')`, then an `is_admin()` guard that dies. [SECURITY.md](SECURITY.md) explains why all three are needed rather than any one.

### Translations

Three wordbooks ship, loaded by three different core code paths:

| Wordbook | Loaded by |
|---|---|
| `lib/<lang>.lng` | `nav.php` merges it on every page for the top-menu title; `lib/module.conf.php` reads it for the dashboard launcher tile. |
| `lib/lang/<lang>.lng` | auto-loaded by `app.inc.php` inside the module. |
| `lib/lang/<lang>_customizer.lng` | the tform wordbook for the settings form. |

`.github/scripts/lang_check.php` runs in CI and enforces two rules:

- **Key parity** against each wordbook's English source. ISPConfig substitutes wordbooks rather than merging them — it falls back to `en.lng` only when the per-language file is *absent* — so a file that exists but omits a key makes the raw key name render in the UI.
- **An 8-character budget on `top_menu_customizer`.** That string is the nav label (`Branding` in English) and it also drives the dashboard launcher tile, where the core dashlet truncates anything longer to 7 characters plus `..`.

The script parses `.lng` files as text and never `include()`s them. They are PHP, and they arrive through pull requests. Keep it that way.

### The CI contract checks

**Brand-token contract parity** greps every `themes/*/brand.php` for each of the twelve keys on CI's hard-coded contract list and fails if one is missing. The two `*_on_dark` logo keys are listed separately from `logo_url` because `logo_on_dark` is not a substring of `logo_url_on_dark`, so each name has to appear: a design implementing only one of the pair would render the wrong-brightness mark on half its surfaces. The `logo_variant_*` keys are the operator's per-surface override, and a design that ignores them overrides an explicit choice with its own assumption. `rail_hex_light` is on the list even though only a design with a light colour mode can paint with it — clarity does; classic reads it and documents the no-op in code, which `tests/brand/probe_classic.php` checks is a read and not a comment. The loop walks the directory rather than naming a design, so a third design cannot opt out. The check catches a key being dropped or renamed; it does not prove the value is used correctly.

**Favicon endpoint contract parity** is separate because the icon is served by its own endpoint per design, and a check that only walked `brand.php` would never notice a design shipping no `favicon.php`. It requires every `themes/*/` to have one and to read both stored values: with only `favicon` a design ignores the by-path override, with only `favicon_url` it ignores the upload. A third step proves clarity's committed shells link that endpoint rather than hardcoding an icon asset; classic's shells are generated, so `install.sh` verifies its own output.

## Ground rules

Rule 1 applies everywhere. Rules 2, 3 and 7 are about clarity's stylesheets; classic ships none, and its equivalent rule is that `brand.php` may only override stock's own selectors, never require a change to stock markup.

1. **Never modify an ISPConfig core file.** Everything ships inside `themes/<design>/` and `interface/web/customizer/`; the one sanctioned exception is the documented `$conf['theme']` line users set themselves.
2. **Stylesheets read only semantic tokens.** No hard-coded colours outside `tokens.css`. A new colour means a new token. The Branding page's own inline `<style>` follows the same rule through `var(--pz-…, var(--nz-…, …))` chains, and carries two exceptions that `tests/brand/probe_page.php` enforces by name: the single literal `#F2F5F7` in `.nz-prev-lightbody` (the light-mode sidebar sample must depict light mode under any design), and the single `.nz-drop:focus-within` rule (the drop zone's `<input type="file">` is visually clipped, so the zone that wraps it carries the focus indicator).
3. **Every new token needs a light-mode value** in the remap block, except `--nz-rail-accent`, which is deliberately never remapped.
4. **Vendor assets are referenced explicitly from `themes/default/…`.** ISPConfig templates fall back to the default theme; static assets do not.
5. **Preserve the JS shell contract**: `#pageContent` inside `form#pageForm`, the `#topnav-container` and `#sidebar` AJAX sinks, the pushy drawer elements, `data-capp` and `data-icon-class` on module links. Breaking these breaks navigation in ways CSS cannot show.
6. **Keep WCAG AA contrast.** If you change a foreground/background pair, state the computed ratio in the PR.
7. **Icons are Clarity shapes as CSS masks**, inlined as data-URIs in `icons.css` and tinted by `currentColor`: never a new icon font, never an emoji. The stock icon fonts are still loaded as vendor CSS by the shell templates because core markup emits their class names; `icons.css` overrides the glyphs those classes render.

## Developing and testing a change

Install on a **test panel**, not production. The usage lines are in [README.md](README.md) and in each script's `--help`. Pass `--theme` or `--module` to install one half while working on it, and `--design=classic` or `--design=all` when the change touches the stock-look design.

The default symlink install means edits to the clone appear on the panel immediately; hard-refresh with `Ctrl+Shift+R`. If a browser clings to stale assets, bump the `?ver=` query string on the links in `themes/clarity/templates/main.tpl.htm`. Classic's `brand.php`, `title.php` and `favicon.php` are live through the symlink the same way; its `templates/` are not, so changes to the generator take effect only on the next `install.sh` run.

On clarity, check every visual change in **dark and light mode**, and check the **mobile drawer** if you touched the frame. Classic has no mode toggle: check it with a non-default accent and rail colour set on the Branding page, and check the login screen separately, since `brand.php?scene=login` emits rules the app scene never sees.

The Branding page is the one page here with a layout of its own, so it needs checking at 1440, 1280 and 1024 under each installed design, and in both colour modes on clarity. Run `tests/brand/probe_page.php` first: it asserts the token chains, the `nz-`/`#nz-brandpage` prefixing, the single permitted focus rule and every id the inline script binds. `tests/brand/probe_frozen.php` hashes the frozen tail of the template — from the `// The iframe uploader injects` comment to EOF, covering the message observer and the two-step upload driver with its click-time CSRF mint. Work above that comment; a red frozen probe means the region moved, and moving it is a security decision to argue for in the commit message, not a hash to regenerate.

Four things still need a human: the drop zones by **keyboard** (Tab must reach each one and show where it is, Space must open the picker) and by **drag and drop**; the `<details>` hints, which must open in place without moving the control beside them; the sticky save bar, which must not cover the last field; and **JavaScript off**, where the form must still save — the check that proves the preview stayed an enhancement.

### The mockup harness (optional)

`mockup/build.py` renders clarity's real templates with sample content, offline. It is useful for screenshots and pixel-diff regression testing, and it does not cover classic, whose shell only exists after an install against a real panel.

```bash
git clone https://git.ispconfig.org/ispconfig/ispconfig3.git .refs/ispconfig3
pip install playwright && playwright install chromium
python3 mockup/build.py --shoot      # writes mockup/shots/*.png
```

Renders are deterministic, so before and after runs can be compared with ImageMagick (`compare -metric AE old.png new.png diff.png`); zero differing pixels means a refactor changed nothing visually.

### Clarity browser regressions

`python3 -B tests/ui/run.py` exercises the actual Clarity shell against the stock dashboard/DNS/client markup with synthetic data and fully intercepted browser requests. It requires the optional Playwright dependency above, its Firefox browser, and an ISPConfig source checkout under `.refs/ispconfig3` (or an explicit `--core-web` directory). It checks alert containment/dismissal, DNS and client spacing, helper versus submit-footer behaviour, username navigation by click/Enter/Space, asynchronous permitted-module changes, long labels and mobile drawer operation in both modes at three widths. It uses the existing mockup renderer without rebuilding its webroot. Native server endpoints are fixture responses: the checks do not prove live permissions, database writes or installation correctness.

Pass `--output /tmp/clarity-ui` to retain screenshots, computed geometry, request records and source hashes outside the repository. Without it the output uses a temporary directory removed after the run. `--theme-ref <git-revision>` renders that revision's theme files against the same core/fixtures; the current regression assertions intentionally fail on older affected revisions. The runner does not install dependencies, fetch core, write to either source tree or contact a panel. Browser checks are optional local checks, not part of the PHP-only CI job.

Each username input method starts from a fresh form/list, checking that it issues only the native navigation requests and no save, filter or helper action. This catches core's document-wide Enter shortcut intercepting buttons outside the form. The runner also checks the native DNS-wizard and APS inline-submit footer shapes. `--only light-limits-desktop` selects one fixture for a focused reproduction; the default runs the complete matrix.

## Submitting a pull request

- Keep PRs small and focused: one fix or one feature.
- Include before and after screenshots for anything visual — both modes for clarity, and both designs if the change touches the shared brand contract.
- Fill in the PR checklist. It enforces the ground rules above.
- By contributing you agree your work is licensed under the repository's [MIT license](LICENSE).
