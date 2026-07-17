# Contributing to Clarity Theme for ISPConfig

Bug reports, fixes, and ideas are all welcome. This page is the practical
guide; the design language itself (tokens, surfaces, component rules) lives in
[DESIGN.md](DESIGN.md).

## Reporting a bug

Open a [bug report](../../issues/new/choose). The template asks for the things
that make a theme bug diagnosable: ISPConfig version, theme version, browser,
dark or light mode, a screenshot, and any browser-console errors.

One check before filing: **switch your user back to the stock `default` theme
and try again.** If the problem is still there, it's an ISPConfig issue, not a
theme issue — take it to the
[ISPConfig bugtracker](https://git.ispconfig.org/ispconfig/ispconfig3/-/issues)
or the [HowtoForge forum](https://forum.howtoforge.com/) instead.

## How the theme is put together

There is no build step. The theme is three template overrides plus plain CSS
and one JS file, loaded in this order:

| File | Role |
|---|---|
| `themes/clarity/templates/` | `main.tpl.htm` (app frame), `topnav.tpl.htm` (module rail), `main_login.tpl.htm` (login scene) — the only templates overridden; everything else falls back to `themes/default`. |
| `assets/stylesheets/clarity/tokens.css` | **The design DNA.** Every color, radius, and shadow as a semantic `--nz-*` token. Dark values at `:root`, light mode as a pure remap block on `:root[data-nz-theme='light']`. |
| `assets/stylesheets/clarity/icons.css` | Clarity icon shapes as CSS `mask` data-URIs, tinted by `currentColor`. Generated file — edit with care. |
| `assets/stylesheets/clarity/base.css` | Functional port of stock `ispconfig.css` — layout/behavior rules the panel's JS depends on, none of its looks. |
| `assets/stylesheets/clarity/app.css` | The frame: rail, topbar, sidebar, drawer, login-adjacent chrome. |
| `assets/stylesheets/clarity/components.css` | Everything inside the content pane: tables, forms, buttons, alerts, tabs, select2, datetimepicker, charts. |
| `assets/stylesheets/clarity/login.css` | The login scene — loaded only by `main_login.tpl.htm` (with `tokens.css`), not by the app frame. |
| `assets/javascripts/nz-theme.js` | Progressive enhancement only: theme switcher, Chart.js theming, drawer/search/a11y behavior. The panel works with it absent. |

`themes/clarity/BUILT-AGAINST.txt` records exactly which stock contracts the
theme relies on — read it before touching a template.

## Ground rules (what keeps it update-proof)

1. **Never modify an ISPConfig core file.** Everything ships inside
   `themes/clarity/`; the one sanctioned exception is the documented
   `$conf['theme']` line users set themselves.
2. **Components read only semantic tokens.** No hard-coded colors outside
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
7. **Icons are Clarity shapes as CSS masks.** No icon fonts, no emoji.

## Developing and testing a change

```bash
git clone https://github.com/wadejbeckett/clarity-theme-ispconfig.git
cd clarity-theme-ispconfig
./install.sh /usr/local/ispconfig     # on a TEST panel, not production
```

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
