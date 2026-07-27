# Clarity — Design Language

**Scope: this document describes the `clarity` design only.** The ISPConfig
Theme Customizer extension ships two designs, and they have opposite goals.
`clarity` (`themes/clarity/`) is the one with a design language of its own —
everything below is about it. `classic` (`themes/classic/`) deliberately has
none: it is the stock ISPConfig look, re-coloured from the same Branding page,
with its shell generated from the panel's own stock templates at install time.
None of the tokens, surfaces or component rules below apply to it — its rule is
that it looks like stock. See `themes/classic/README.md`.

**Clarity** is a ground-up dark and light interface for ISPConfig:
DirectAdmin-Evolution frame anatomy fused with VMware Clarity's dark surface
system, anchored on the brand blue **`#0065AB`**.

The single source of truth is
**`themes/clarity/assets/stylesheets/clarity/tokens.css`** — a self-contained
`:root { --nz-* }` block, no build step. Components read **semantic aliases
only** (`--nz-card`, `--nz-action`, …), never ramp steps, never raw hex.

---

## 1. Where the look comes from

| Ingredient | Source | What we took |
|---|---|---|
| Surface temperature | VMware Clarity dark (`@cds/core` 6.17 `theme.dark.css`, MIT) | the "construction" blue-grey ramp (hue 198): page `#17252B`, card `#21333B`, raised `#2D4048`, well `#1B2B32`; status hues (green 500/800, ochre 400/900, red 500/900) |
| Frame anatomy & elevation | DirectAdmin Evolution dark (live demo, tokens extracted 2026-07-08) | deep-navy brand band on the left, elevation as a surface-lightness ladder + 1px hairlines (not shadows), big calm search bar, uppercase micro-headers, small radii |
| Brand | `#0065AB` | the whole blue ramp is re-anchored on it (hue ~205); the rail navy `#01243D` is its 1100 step, the dark action blue `#2EA9FF` its 400 step |

Clarity dark and DirectAdmin Evolution read as one family — desaturated dark
blue-grey surfaces with a light action blue. This design sits deliberately in
that family while staying unmistakably its own (the navy rail and every
interactive element derive from `#0065AB`).

## 2. Principles

1. **Elevation is lightness, not shadow.** `#17252B` page → `#21333B` card →
   `#2D4048` hover. Shadows exist but whisper; hairlines
   (`rgba(133,147,153,…)` at 10–55%) define edges.
2. **One brand zone.** The navy rail (`#01243D`) is the only saturated brand
   surface. Content chrome stays neutral so data reads first.
3. **Blue is the action.** Links `#66C0FF`, indicators/active states
   `#2EA9FF`, primary buttons `#007ACC` with white text. Green/ochre/red are
   reserved for genuine status.
4. **Quiet structure, loud data.** Table headers, section headers and rail
   group titles are 11px uppercase letter-spaced muted text — DirectAdmin's
   micro-header voice. Body content is 13–14px `Inter` at comfortable
   contrast (`#E3EAED` on cards).
5. **Small radii, tight controls.** 4px controls, 8px cards, 10px modals,
   36px control height (Clarity standard density). A console, not a
   marketing site.
6. **Token-driven.** A future light variant (or re-brand) is an alias remap
   in `tokens.css`, not a component rewrite.

## 3. The frame

```
┌──────────┬──────────────────────────────────────────────┐
│  ◧ logo  │ [☰] (  ⌕ Search………………… )        ●3  ⏻ Logout │  topbar 60px
│──────────│──────────────────────────────────────────────│
│ ▍Home    │  Page title 24/500                           │
│  Help    │  MICRO-HEADER                                │
│  Client  │  ┌tile┐ ┌tile┐ ┌tile┐ ┌tile┐                 │
│  Sites   │  ┌────────── card ──────────┐                │
│  Email   │  │ Caption 14/600           │                │
│  …       │  │ TH 11 CAPS · rows 13px   │                │
│──────────│  └──────────────────────────┘                │
│ GROUP    │                                              │
│  item    │   content column, max 1240px                 │
│  item    │                                              │
│ (tree /  │                                              │
│  news)   │                                              │
└──────────┴──────────────── footer ─────────────────────-┘
   248px rail, #01243D, full-height navy band
```

- **Rail** hosts the logo, the module nav (`#topnav-container`, markup from
  this design's own `topnav.tpl.htm`), and the contextual `#sidebar` (module
  tree, or news on the dashboard) under a hairline. Active module = navy-blue
  fill (`#002D4D`) + 3px rounded `#2EA9FF` bar + white text.
- **Topbar** is sticky, blurred page-color; the global search is a single
  bordered bar (card surface, icon left, focus ring); the datalog counter is
  a quiet pulsing blue chip; logout is a ghost session control (danger color
  only on hover).
- **Mobile** (<960px): the rail hides; ISPConfig's stock pushy drawer takes
  over, skinned to the same navy.

## 4. Component voice (dark values)

- **Buttons.** Clarity voice: 36px tall, 12px/600 UPPERCASE at `+0.12em`.
  Primary = `#007ACC` fill, white text — used by `.formbutton-success`,
  `[data-submit-form]`, `.btn-primary` (Save, Add new). Everything else is a
  ghost: transparent, 1px solid `#6A7A81` border (Clarity
  object-border-color), raised-surface hover. Danger = outlined `#FF674D`,
  tint fill on hover. Inside table rows, action buttons demote to borderless
  icon controls.
- **Inputs.** Recessed well `#1B2B32`, 1px `rgba(133,147,153,.55)` border,
  4px radius; focus = `#2EA9FF` border + 3px `rgba(46,169,255,.4)` halo.
  Native checkboxes/radios via `accent-color`.
- **Tables.** Card-wrapped (8px radius, hairline, `overflow-x:auto`).
  `caption` = card title bar; header band = construction-1000 (Clarity's
  tint, darker than the card) with 11px caps muted text; cells 8×12px
  (Clarity standard density); hover = faint blue wash; whole-cell links
  inherit text color; `tr.danger` = translucent red tint, normal text.
- **Tabs.** Flat strip on a hairline; active = white text + 3px `#2EA9FF`
  inset underline (Clarity border-width-300). No boxes.
- **Alerts.** Clarity dark anatomy: solid status surface (blue-800 /
  green-800 / ochre-900 / red-900) + 1px status-color border, light text.
  In light mode the surfaces remap to the Clarity 50-tints with dark text.
- **Meters.** 18px pill track (`#1B2B32`), status-colored fill, centered
  11px white overlay label (stock markup contract).
- **Badges.** Solid Clarity shade fills (green-800, ochre-600 with dark
  text, red-800/900, blue-800), 12px radius, white text elsewhere.
- **Charts.** Chart.js draws dark axes, so canvases sit on a light "paper"
  panel (`--nz-paper`, `#E3EAED`, 8px radius) — a deliberate light island.
- **Login.** Full-viewport `#17252B` scene with two soft radial brand glows,
  one 384px card, stacked 40px inputs, full-width primary submit; secondary
  actions are text-quiet.

## 5. Icons

All glyphs are official **VMware Clarity icon shapes** (`@cds/core/icon`,
MIT), rendered via CSS masks over the stock ISPConfig markup —
`icons.css` maps every reachable `icon-*`, `glyphicon-*` and `fa-*` class
(32 classes, 29 shapes) to a Clarity outline shape tinted by
`currentColor`. The legacy ispconfig icon font still loads (vendor
dependency) but no covered glyph renders from it.

## 6. Dark / light switcher

The topbar sun/moon control toggles `data-nz-theme` on `<html>`
(persisted in `localStorage`, applied pre-paint by a boot snippet in both
shells; `assets/javascripts/nz-theme.js` handles the click). Light mode is
a **pure alias remap** in `tokens.css` using Clarity *light* values
(`#F1F6F8` page, white cards, construction-light text ramp, 50-tint status
surfaces) — the navy rail stays brand in both modes. No component rule
changes per mode.

## 7. White-labeling

The normal route is the extension's **Branding** page, which stores logo, panel
name and colours in `sys_ini`; `themes/clarity/brand.php` reads those keys and
emits a stylesheet that overrides the shipped values (see
[SECURITY.md](SECURITY.md) for that endpoint's exposure).

The logo has **two variants, named after the background they sit on**, because
one panel runs designs whose headers disagree about brightness: Clarity's rail
is navy and wants a white mark, the stock look's header is `#f2f5f7` and wants a
dark one. Each design asks for the variant matching its own background and falls
back to the other when that variant is unset — so a panel with a single stored
logo behaves exactly as it always did. Within a variant, a referenced path
(`logo_url` / `logo_url_on_dark`) wins over the uploaded image, and with no logo
at all but a panel name given, the name renders as a CSS text wordmark in the
same slots.

Below that, the shipped logo is a plain file: replace
`themes/clarity/assets/images/wordmark-white.svg` with any SVG/PNG
(white/light artwork recommended — it sits on the navy brand
band). Heights are fixed in CSS; width follows the file's aspect ratio.
That file is the fallback the panel paints when nothing is set, and the
design's tokens are the fallback for every colour the Branding page does not
override — a re-brand should never need a component rewrite.

The footer carries the two courtesy credits in separate spans
(`.nz-credit-ispconfig`, `.nz-credit-theme`, with the `·` separator in
`.nz-credit-sep`) so each is individually hideable from the Branding page.
Both ship visible; hiding the theme credit alone also hides the separator, so
nothing renders with an orphaned middot. Neither toggle touches a licence
notice.

## 8. Typography

`Inter` (self-hosted, SIL OFL 1.1) → system stack. Body 14/1.4286 at
`-0.00714em` (Clarity body metrics), secondary 13, captions 11 caps at
`+0.07em`, page title 24/500 (Clarity title), headings medium (500), card
titles 14/600 (micro-header family), weights 400/500/600 only.

## 9. Architecture (how it stays upgrade-safe)

- `themes/clarity/` is **self-contained**: own templates — three shell
  (`main.tpl.htm`, `topnav.tpl.htm`, `main_login.tpl.htm`) and three dashboard
  dashlets (`templates/dashboard/{dashboard,modules,metrics}.htm`), all six
  pinned with their stock contracts in `BUILT-AGAINST.txt` — own CSS
  (`tokens.css` → `base.css` → `app.css` → `components.css`, login pages:
  `tokens.css` → `login.css`), own fonts/favicons/logo.
- Vendor CSS/JS load from `themes/default/...` by explicit path (ISPConfig
  theme assets have **no fallback**). The stock skin files
  (`ispconfig.css`, `theme.min.css`, `responsive.min.css`, `login.css`) are
  **not loaded**; their functional subset (flags sprite, sort-glyph
  mechanics, float utilities, overlay-label meters, …) is ported into
  `base.css` (function only, no skin).
- Every stock JS contract is honored — see `themes/clarity/BUILT-AGAINST.txt`
  for the full list and the `ispconfig_version` gating rules (stamped by
  `install.sh`).
- Validation harness: `mockup/build.py` renders this design's real templates
  with a vlibTemplate-compatible engine + captured/synthesized content
  fragments (`mockup/fragments/`), and screenshots dashboard, list, form and
  login pages at desktop + mobile.

## 10. Reusing the DNA elsewhere

1. Copy `themes/clarity/assets/stylesheets/clarity/tokens.css` verbatim.
2. Reference `--nz-*` aliases only.
3. Follow §3/§4 shapes. The look survives any markup because every surface,
   edge and state derives from the same three ramps.

## 11. VMware Clarity alignment & deviation register

Audited token-by-token against `@cds/core` 6.17 **dark, standard density**
(2026-07-09). Adopted directly: surface ladder roles, text hierarchy
(color-400/300/200 roles), link/highlight = blue-400 role, selected =
blue-900 solid, 36px controls, 24/500 title + medium headings, 8×12 table
cells + dark header band, 3px active indicators, solid alert/badge
anatomy, 4px control radius scale, `0 1px 2px rgba(0,0,0,.6)` shadow,
`rgba(0,0,0,.6)` backdrop.

Deliberate deviations (each for brand, anatomy, or WCAG):

| Deviation | Clarity says | Why we differ |
|---|---|---|
| Blue ramp anchored `#0065AB` (hue 205) | hue-198 blues | Brand blue; role mapping mirrors Clarity |
| Page = construction-1100 | app bg = construction-1000 | 2-step card-on-page elevation (DirectAdmin anatomy) |
| Card 8px / modal 10px / login 14px radius | 4px | card-elevation family |
| Translucent hairlines on cards/rows | solid construction-500/400 borders | quieter data chrome |
| 11px/600 caps micro-headers | no caps voice | frame signature |
| Primary = blue-600 + white, darkening hover | blue-400 + black text | brand action voice; AA 4.5–6.1:1 |
| Boxed 36px inputs | underline inputs | stock Bootstrap markup, input-groups |
| Custom 2px accent focus ring | native `Highlight` outline | deterministic cross-browser visibility |
| Placeholder `#82939B`, readable disabled values | construction-500 (2.9:1) | Clarity's own values fail AA |
| Light warning-alert text | construction-900 on ochre-900 (2.07:1) | Clarity's own value fails AA |
| `Inter` | Clarity City / Avenir Next | brand; no proprietary fonts |
| shadow-2 / shadow-pop extensions | only `0 1px 2px` ladder | menus need lift on dark |

## Provenance & licensing

- VMware Clarity `@cds/core` 6.17 — MIT. Surface and status token *values* are
  derived from Clarity's dark theme, and 29 icon shapes are bundled verbatim as
  data-URI SVG masks in `themes/clarity/assets/stylesheets/clarity/icons.css`.
  Because artwork ships, the MIT copyright and permission notice ships with it,
  in that file's header. (An earlier version of this line said "token values
  only; no code bundled" — that was wrong, and wrong in the direction that
  matters for a licence obligation.)
- DirectAdmin Evolution — visual reference only (anatomy/metrics studied from
  the public demo; no assets copied).
- Inter — SIL OFL 1.1, self-hosted.
- ISPConfig — BSD; no core file modified. Everything ships inside the
  extension's own directories, this design's being `themes/clarity/`.
