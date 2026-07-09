# the earlier name — Design Language

**the earlier name** (`themes/earlier-dark/`) is a ground-up dark interface for
ISPConfig: DirectAdmin-Evolution frame anatomy fused with VMware Clarity's
dark surface system, anchored on the Earlier brand blue **`#0065AB`**.

The single source of truth is
**`themes/earlier-dark/assets/stylesheets/earlier/tokens.css`** — a self-contained
`:root { --nz-* }` block, no build step. Components read **semantic aliases
only** (`--nz-card`, `--nz-action`, …), never ramp steps, never raw hex.

---

## 1. Where the look comes from

| Ingredient | Source | What we took |
|---|---|---|
| Surface temperature | VMware Clarity dark (`@cds/core` 6.17 `theme.dark.css`, MIT) | the "construction" blue-grey ramp (hue 198): page `#17252B`, card `#21333B`, raised `#2D4048`, well `#1B2B32`; status hues (green 500/800, ochre 400/900, red 500/900) |
| Frame anatomy & elevation | DirectAdmin Evolution dark (live demo, tokens extracted 2026-07-08) | deep-navy brand band on the left, elevation as a surface-lightness ladder + 1px hairlines (not shadows), big calm search bar, uppercase micro-headers, small radii |
| Brand | Earlier `#0065AB` | the whole blue ramp is re-anchored on it (hue ~205); the rail navy `#01243D` is its 1100 step, the dark action blue `#2EA9FF` its 400 step |

Clarity dark and DirectAdmin Evolution read as one family — desaturated dark
blue-grey surfaces with a light action blue. the earlier name sits deliberately in
that family while staying unmistakably Earlier (the navy rail and every
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
   34px control height. A console, not a marketing site.
6. **Token-driven.** A future light variant (or re-brand) is an alias remap
   in `tokens.css`, not a component rewrite.

## 3. The frame

```
┌──────────┬──────────────────────────────────────────────┐
│  ◧ earlier  │ [☰] (  ⌕ Search………………… )        ●3  ⏻ Logout │  topbar 60px
│──────────│──────────────────────────────────────────────│
│ ▍Home    │  Page title 22/600                           │
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
  the theme's own `topnav.tpl.htm`), and the contextual `#sidebar` (module
  tree, or news on the dashboard) under a hairline. Active module = navy-blue
  fill (`#002D4D`) + 3px rounded `#2EA9FF` bar + white text.
- **Topbar** is sticky, blurred page-color; the global search is a single
  bordered bar (card surface, icon left, focus ring); the datalog counter is
  a quiet pulsing blue chip; logout is a ghost session control (danger color
  only on hover).
- **Mobile** (<960px): the rail hides; ISPConfig's stock pushy drawer takes
  over, skinned to the same navy.

## 4. Component voice (dark values)

- **Buttons.** Primary = `#007ACC` fill, white text — used by
  `.formbutton-success`, `[data-submit-form]`, `.btn-primary` (Save, Add
  new). Everything else is a ghost: transparent, 1px `rgba` border, muted
  text, raised-surface hover. Danger = outlined `#FF674D`, tint fill on
  hover. Inside table rows, action buttons demote to borderless icon
  controls.
- **Inputs.** Recessed well `#1B2B32`, 1px `rgba(133,147,153,.55)` border,
  4px radius; focus = `#2EA9FF` border + 3px `rgba(46,169,255,.4)` halo.
  Native checkboxes/radios via `accent-color`.
- **Tables.** Card-wrapped (8px radius, hairline, `overflow-x:auto`).
  `caption` = card title bar; headers 11px caps muted; rows hairline-divided,
  hover = faint blue wash; whole-cell links inherit text color (the row is
  the affordance); `tr.danger` = translucent red tint + `#FFA494` text.
- **Tabs.** Flat strip on a hairline; active = white text + 2px `#2EA9FF`
  inset underline. No boxes.
- **Alerts.** Translucent status tint + 3px status left bar + status-300
  text. Same voice at login.
- **Meters.** 18px pill track (`#1B2B32`), status-colored fill, centered
  11px white overlay label (stock markup contract).
- **Badges.** Pill, status tint + 1px status border + status-300 text.
- **Charts.** Chart.js draws dark axes, so canvases sit on a light "paper"
  panel (`--nz-paper`, `#E3EAED`, 8px radius) — a deliberate light island.
- **Login.** Full-viewport `#17252B` scene with two soft radial brand glows,
  one 384px card, stacked 40px inputs, full-width primary submit; secondary
  actions are text-quiet.

## 5. Typography

`Inter` (self-hosted, SIL OFL 1.1) → system stack. Body 14/1.45, secondary
13, captions 11 caps at `+0.07em`, page title 22/600 at `-0.015em`, card
titles 14/600, weights 400/500/600 only.

## 6. Architecture (how it stays upgrade-safe)

- `themes/earlier-dark/` is **self-contained**: own templates
  (`main.tpl.htm`, `topnav.tpl.htm`, `main_login.tpl.htm`), own CSS
  (`tokens.css` → `base.css` → `app.css` → `components.css`, login pages:
  `tokens.css` → `login.css`), own fonts/favicons/logo.
- Vendor CSS/JS load from `themes/default/...` by explicit path (ISPConfig
  theme assets have **no fallback**). The stock skin files
  (`ispconfig.css`, `theme.min.css`, `responsive.min.css`, `login.css`) are
  **not loaded**; their functional subset (flags sprite, sort-glyph
  mechanics, float utilities, overlay-label meters, …) is ported into
  `base.css` (function only, no skin).
- Every stock JS contract is honored — see `themes/earlier-dark/BUILT-AGAINST.txt`
  for the full list and the `ispconfig_version` gating rules (stamped by
  `install.sh`).
- Validation harness: `mockup/build.py` renders the theme's real templates
  with a vlibTemplate-compatible engine + captured/synthesized content
  fragments (`mockup/fragments/`), and screenshots dashboard, list, form and
  login pages at desktop + mobile.

## 7. Reusing the DNA elsewhere

1. Copy `themes/earlier-dark/assets/stylesheets/earlier/tokens.css` verbatim.
2. Reference `--nz-*` aliases only.
3. Follow §3/§4 shapes. The look survives any markup because every surface,
   edge and state derives from the same three ramps.

## Provenance & licensing

- VMware Clarity `@cds/core` 6.17 — MIT (token values only; no code bundled).
- DirectAdmin Evolution — visual reference only (anatomy/metrics studied from
  the public demo; no assets copied).
- Inter — SIL OFL 1.1, self-hosted.
- ISPConfig — BSD; no core file modified, everything ships as a theme.
