# phosphor — design spec

Status: for approval. Nothing here is implemented. Written 2026-09-07 against `themes/clarity/`, `DESIGN.md`, `CONTRIBUTING.md`, `README.md`, `interface/web/customizer/`, `mockup/build.py` and the Earlier brand tokens (`earlier-brand`, `tokens/tokens.json`). Supersedes Part A of `context/design-plan-2026-09-06.md`; Parts B and C of that draft are unaffected.

## Purpose and scope

`phosphor` is a third design for the ISPConfig Theme Customizer, selectable in ISPConfig's Design picker alongside `clarity` and `classic`. Clarity is not touched. The brief is *black glass with phosphor glow*: a true-black console whose surfaces are translucent panes over the ground and whose only warm thing is an amber accent that is allowed, in four named places, to glow. It is **dark only in v1** — no light remap, and its shell does not render the theme switcher.

It is vendor-neutral: no literal "Earlier" in any file, asset, comment or string. The Earlier token system is the *starting point* for palette and rhythm, and where this spec departs from it, it says so. An operator's identity arrives through the Branding page exactly as it does on the other two designs.

The envelope is unchanged: no ISPConfig core file modified; everything ships in `themes/phosphor/` and `interface/web/customizer/`; the only rows written are ones core already owns (`sys_ini`, and `sys_config` for the donation switch); the brand-token contract is honoured, so CI's walk of `themes/*/` passes on merit. The new key `rail_hex_light` is in scope for the Branding page.

## Aesthetic direction

**Ground ladder.** Three rungs, not five. `#06080A` is the page — the tube with no signal, marginally deeper and cooler than the brand's own `#07090b`. Above it sit *panes*, not surfaces: `rgba(176, 208, 255, 0.07)` with a 10px backdrop blur. That tint over that ground composites to `#12161B`, the brand's own `ground.surface-1` — the glass reproduces the ladder rather than replacing it, and the cool cast is what makes it read as glass and not as a lighter grey. Where a pane nests inside a pane, or `backdrop-filter` is unsupported, the opaque `#12161B` is used directly. **Glass never stacks on glass:** one blurred depth per screen region.

**Hairlines and the lit edge.** Every pane carries a 1px border at 10% white and one brighter top edge at 16% of the glass tint, lit from the same direction the accent comes from. That edge is the whole of the glass affordance — no gradient wash, no inner shadow, no bevel.

**Glow.** Glow is amber and appears in exactly four places: the login mark and its cursor, a primary button on hover or focus, the active rail marker, and the chart line's fill. Nowhere else. Glow on a status colour is prohibited (a green glow reads as an alert); glow at body text size is prohibited. Elevation shadows are black, not amber, and appear only on popovers, menus and modals. On a black ground a second glowing thing halves the first one's meaning, which is what the budget protects.

**Restraint rules.** Amber is the only warm colour on screen; status hues appear only on genuine status. Mono is a role, not a decoration — it marks machine-authored values (numerals, table headers, rail group titles, code, counters) and prose is always the grotesk. No tracked-out caps eyebrow above headings: caps mono is reserved for the two places where it encodes "machine-set". No middle-dot meta strings, no arrow suffixes on buttons, and no identical rounded card for everything — tables, instrument tiles and forms get three distinct treatments.

**Type.** Space Grotesk (400/500/700) for everything a person wrote; JetBrains Mono (400/500, with its true italic) for everything a machine set. Both SIL OFL 1.1, self-hosted and re-subset from source into `themes/phosphor/assets/fonts/`, exactly as Inter is today — no CDN, no external request. `font-synthesis: none` wherever the grotesk is used; emphasis is mono italic, because Space Grotesk ships no italic and a synthesised oblique is the tell.

**Motion.** 120ms micro, 240ms standard, 480ms for the one login reveal, 1100ms cursor blink; `cubic-bezier(0.4, 0, 0.2, 1)` throughout, linear for the blink. No fade-and-slide-up on content sections. Under `prefers-reduced-motion: reduce` the cursor stops blinking and stays solid, the login reveal renders at its final state, glow transitions become instantaneous, and no transform animates. Reduced motion never removes an element — the logo in particular must still render, which is the regression the customizer fixed at v3.1.1.

**The one bold moment.** The login scene, and only the login scene. A full-viewport black field; a 34px terminal grid fades in behind a single 384px glass card under a radial mask; the panel name sits above the card in Space Grotesk 500 with a mono block cursor blinking after it. One orchestrated 480ms reveal, and nothing else moves. No horizon beam — that is clarity's signature and it stays clarity's. The app frame is not atmospheric: film grain sits on the page ground only at 0.03 opacity, and the CRT scanline is **off** in the frame and on only at login, because it costs legibility over an eight-hour table-reading day. Both are deliberate departures from the brand's "global" scope and are recorded as such.

## Token table

Emitted as `--pz-*` custom properties in `themes/phosphor/assets/stylesheets/phosphor/tokens.css`. Components read semantic aliases only; no component rule may contain a raw hex.

| Token | Value | Note |
|---|---|---|
| `--pz-ground` | `#06080A` | page |
| `--pz-band` | `#0B0E12` | rail and topbar base, under the blur |
| `--pz-rail-text` / `-text-hover` / `-heading` | `rgba(255,255,255,.88)` / `#FFFFFF` / `rgba(255,255,255,.66)` | shipped defaults only; `brand.php` re-emits these byte-identically for the shipped band and re-derives them for any other |
| `--pz-rail-hover` / `-active` / `-edge` / `-accent` | `rgba(255,255,255,.05)` / `#12171E` / `rgba(255,255,255,.07)` / `var(--pz-accent)` | `-active` is the band shaded 15%; `-accent` is the marker that carries the glow |
| `--pz-glass` | `rgba(176, 208, 255, 0.07)` | pane fill; composites to `#12161B` on ground |
| `--pz-pane-solid` | `#12161B` | opaque fallback and nested panes |
| `--pz-raised` | `#171D23` | input wells, table header band |
| `--pz-lift` | `#1D242C` | hover strata |
| `--pz-edge` / `-soft` / `-strong` / `-input` | `rgba(255,255,255,.10)` / `.055` / `.16` / `.35` | hairlines; input edge measures ≥3:1 on every rung |
| `--pz-edge-top` | `rgba(176, 208, 255, 0.16)` | the lit top edge |
| `--pz-accent` / `-dim` | `#FFA301` / `#D17800` | 9.96:1 on `#07090B` (brand-measured); ≥9.9:1 on `#06080A` |
| `--pz-accent-subtle` / `-medium` / `-strong` | amber at 8% / 10% / 28% | fills, washes, borders |
| `--pz-on-accent` | `#06080A` | ink on any filled accent. White is prohibited (2.00:1) |
| `--pz-ink-bright` / `-base` / `-sub` / `-muted` | `#FFFFFF` / `#EDEDED` / `#C1C5C5` / `#9AA0A0` | headings / body / labels / captions |
| `--pz-ink-placeholder` | `#9AA0A0` | 6.33:1 on `--pz-raised` (computed) |
| `--pz-ink-faint` | `#62696A` | decorative and disabled only — fails AA at text sizes |
| `--pz-success` / `-warning` / `-danger` / `-info` | `#00FF88` / `#F2D935` / `#FF4D4D` / `#00D4FF` | alerts are a 10% tint + 28% border + full-value text, not solid panels |
| `--pz-radius-ctl` / `-card` / `-modal` / `-pill` | `4px` / `8px` / `16px` / `9999px` | |
| `--pz-blur` / `-strong` | `10px` / `18px` | panes / topbar and modal |
| `--pz-glow-ring` | `0 0 0 1px var(--pz-accent)` | |
| `--pz-glow-soft` | `0 10px 34px -12px rgba(255,163,1,.60)` | primary hover, directional |
| `--pz-glow-text` | `0 0 12px rgba(255,163,1,.45)` | login mark and cursor only |
| `--pz-shadow-lift` | `0 22px 50px -28px rgba(0,0,0,.90)` | popovers, menus, modals |
| `--pz-focus` | `0 0 0 2px var(--pz-ground), 0 0 0 4px var(--pz-accent)` | two-stop ring |
| `--pz-fs-title` / `-section` / `-cardtitle` / `-body` / `-secondary` / `-caption` / `-numeral` / `-code` | `24px` / `16px` / `14px` / `14px` / `13px` / `11px` / `28px` / `12.5px` | caption, numeral and code are mono |
| `--pz-track-label` | `0.1em` | mono caps labels only |
| `--pz-rail-w` / `-topbar-h` / `-content-max` / `-content-pad` | `232px` / `56px` / `1280px` / `28px` | |
| `--pz-ctl-h` / `-ctl-h-sm` | `36px` / `26px` | |
| `--pz-fast` / `-base` / `-slow` / `-blink` | `120ms` / `240ms` / `480ms` / `1100ms` | |
| `--pz-grid-pitch` | `34px` | login grid only |

**Derived from brand keys, never literal.** `accent_hex` replaces `--pz-accent`; `--pz-accent-dim` is derived by the same OKLCH lightness drop (0.136) the brand uses, `--pz-on-accent` is chosen by measured contrast between the ground and white — so a pale custom accent gets dark ink and a dark one gets light ink — and every amber-alpha token is re-expressed from the new hue by `color-mix()`. `rail_hex` replaces `--pz-band`; **every rail ink is derived from `rail_hex` by measured contrast and no rail ink is a literal outside `tokens.css`'s shipped defaults**, using the algorithm `themes/clarity/brand.php:859` already ships (`brand_rail_vars`: compare the white and black ratios rather than pivoting on lightness; walk an alpha ladder until AA clears; choose the ink against the *lightest* of band / hover / active; tint the hover downward where lightening it would cost the ratio). Phosphor reuses that algorithm and emits it under its own `--pz-rail-*` names. `login_bg` replaces the login field's base, and the grid mask re-derives from it. `rail_hex_light` is read and documented as a no-op — phosphor has no light scope to paint — regardless of how open question 1 is answered.

## Component inventory

Phosphor overrides the same seven templates clarity does, for the same reason — they are the set already proven upgrade-safe — and inherits every other template from `themes/default`, styled by CSS alone. Contracts are pinned in `themes/phosphor/BUILT-AGAINST.txt`, without which CI fails.

| From clarity | Phosphor does |
|---|---|
| `main.tpl.htm` | own copy: rail 232px, topbar 56px blurred over `--pz-band`, no theme-switcher control, `favicon.php` linked, JS shell contract preserved (`#pageContent` in `form#pageForm`, `#topnav-container`, `#sidebar`, pushy drawer, `data-capp`) |
| `topnav.tpl.htm` | own copy: active item = 3px amber left rule + 8% amber wash + bright ink + the marker's glow |
| `main_login.tpl.htm` | own copy: the grid, the card, the cursor |
| `dashboard/{dashboard,modules,metrics,donate}.htm` | own copies; `metrics` becomes instrument tiles — mono numeral at 28px, tabular figures, an amber sparkline, and the live last value, not decoration |
| `tokens.css` | rewritten; dark values at `:root` only, `color-scheme: dark`, no remap block |
| `base.css` | **copied verbatim** — it is a functional port of stock `ispconfig.css` with no skin in it; any divergence between the two copies is a bug |
| `icons.css` | **copied verbatim**, MIT notice header included, tinted by `currentColor`. No new icon font, no emoji |
| `app.css` | rewritten: frame, rail, topbar, sidebar, drawer |
| `components.css` | rewritten: tables (glass-wrapped, `--pz-raised` header band, 11px mono caps, 8×12 cells, amber-wash row hover), forms (recessed `--pz-raised` wells, `--pz-edge-input` border, amber focus ring), buttons (36px, primary = amber fill with `--pz-on-accent` text, everything else ghost, in-row actions borderless), tabs (flat strip, 3px amber inset underline), alerts (tint + border + status text), meters, badges, select2, datetimepicker |
| `login.css` | rewritten |
| `nz-theme.js` | becomes `pz-theme.js`: switcher removed, Chart.js palette read from computed `--pz-*`, drawer/search/a11y behaviour kept. Progressive enhancement only — the panel works with it absent |

Two deliberate departures from clarity's component voice. Charts do **not** sit on a light "paper" island: the Chart.js defaults are themed dark (amber line, amber-to-transparent fill, 6% white gridlines, mono ticks), which is why `pz-theme.js` exists rather than a shared file. And alerts are tinted glass rather than solid status panels, because a solid green panel on this ground is the loudest thing on screen.

Inherited untouched from stock: every non-shell template, the legacy icon fonts (loaded as vendor CSS from `themes/default/assets/`, with no covered glyph rendering from them), jQuery, Chart.js, select2, the datetimepicker and the pushy drawer JS. The stock skin stylesheets are not loaded.

## Branding page redesign

Two columns: settings left, a sticky live preview right, collapsing to one column below 1024px with the preview above the fields. Groups, in order — **Identity** (panel name; the light-background mark; the dark-background mark; placement; the favicon), **Colour** (`accent_hex`, `rail_hex`, `rail_hex_light`, `login_bg`), **Login screen** (`custom_login_text`, `custom_login_link`), **Panel visibility** (design picker, version surfaces, news feed, donation dashlet, the two footer credits). Each group is a `<fieldset>` whose legend is the group heading, so the existing wordbook keys and the screen-reader structure survive.

Each uploader sits **inline with its variant**: the current-mark preview on a swatch of that background, the file input, Upload and Remove, and the by-reference field, in one block per variant — so "which artwork, for which background" is one decision the operator can check by eye. Placement (`logo_variant_nav`, `logo_variant_login`) stays a third block after both variants rather than moving inside either, because it is scoped by *surface* and is a statement about the pair; nesting it under one mark would read as a property of that mark, which is the reasoning the current template already carries. The three uploaders keep the existing two-step fetch driver and its click-time CSRF mint unchanged; that is the part that must never be reimplemented.

**Preview behaviour.** Three panes — Navigation, Login, Tab icon. Colour, panel name and rail-ink changes render in JS as you type, from the same measured-contrast rule the reader uses, so a white rail shows dark ink immediately. Anything depending on *variant resolution* is not guessed in JS: that resolver exists in exactly three PHP copies CI proves agree, and a fourth copy in JavaScript would break the guarantee. Instead a debounced request to a new read-only `interface/web/customizer/preview.php` — admin-only, the same three checks in the same order, no writes, candidate values in the query — returns the fragment rendered by `lib/preview.inc.php`. Upload and Remove already refresh all three previews from the server response; that stays.

**Validation.** Server-side tform validators remain authoritative and unchanged. The banner keeps its relocation into `#nz-msg-slot` under the page header, and the offending field is additionally marked inline (`aria-invalid`, a danger edge, the message beneath the control) by matching the banner's `errmsg` key to its field. A client-side mirror of the hex and path patterns gives advisory feedback on blur; it never blocks submission.

**Save feedback.** The button reads "Save changes", the confirmation reads "Changes saved" in the header slot, and the preview flashes once to the applied state. The vocabulary stays constant across the flow.

**Staying inside tform.** `customizer.tform.php` gains exactly one field, `rail_hex_light`, with the same anchored `/^(#[0-9A-Fa-f]{6})?$/` validator the other three hexes use. No new formtype, no change to `db_table`, `tab_default` or the auth preset. The redesign is markup, inline CSS and inline JS in `templates/customizer_edit.htm` — a CSS grid on the template's own wrapper, not a change to Bootstrap's grid. There is **no dedicated stylesheet**: every colour is `var(--pz-…, var(--nz-…, <stock fallback>))`, so the page inherits phosphor's look, clarity's, or stock's, from whichever design is active.

## Mockup plan

`mockup/build.py` is parameterised over the design directory (today `DARK` is hard-coded to `themes/clarity`), so one harness renders either. What it already does is unchanged: render the design's *real* templates through the mini vlibTemplate engine, fill `#topnav-container` / `#sidebar` / `#pageContent` from `mockup/fragments/`, strip scripts, re-inject only a controlled per-page bootstrap. Two new fragments are needed: `client-limits-form.html` and `customizer-branding.html`, the latter being the real `customizer_edit.htm` rendered with its tmpl_vars so the redesign is proven in real markup rather than drawn.

| Screen | Viewports | Must prove |
|---|---|---|
| Login | 1440×900, 390×844 | the one bold moment; the glow budget holds; the 384px card and the grid still read at 375px |
| Dashboard | 1440×900, 390×844 | glass over black at one depth; mono numerals against grotesk prose; the amber chart theming with no paper island |
| Sites list | 1440×900 | 13px table density, mono caps header band, row hover, demoted in-row actions, `overflow-x` containment |
| Client edit → Limits | 1440×900, 1280×900 | the cluttered form survives: two-column form grid at ≥1280px, section captions in the mono voice, glass not stacked on glass |
| Branding page | 1440×900, 1024×900 | the two-column layout, inline uploaders, the preview column, and the single-column collapse |

Reviewed as screenshots first; refinement happens live on the panel afterwards. Renders stay deterministic so before/after runs pixel-diff.

## Accessibility

Body ink targets AAA (≥7:1) on the ground and holds AA 4.5:1 as a floor on every rung including the composited glass; `--pz-ink-faint` is barred from text entirely — it is for decorative and disabled affordances only. Amber on the ground measures 9.96:1 (brand-measured on `#07090B`); ink on any amber fill is `--pz-on-accent`, never white. The input edge holds ≥3:1 against both the well and the page, per SC 1.4.11. Rail inks are asserted, not assumed — see Testing.

Focus is the two-stop ring, always paired with `outline: 2px solid transparent; outline-offset: 2px` so the indicator survives forced-colors mode where box-shadows are dropped, and it is applied on `:focus-visible` with no `:focus { outline: none }` anywhere. Interactive targets are ≥24×24px (SC 2.5.8); standard controls are 36px.

The mobile drawer traps focus while open, closes on Escape, returns focus to its toggle, keeps `aria-expanded` current on the toggle, and marks the frame behind it inert. Glass must never be load-bearing for contrast: where `backdrop-filter` is unsupported the opaque fallback applies, and every pair is measured against that fallback rather than against the blurred composite.

## Testing

`tests/brand/` gains `probe_phosphor.php` and `run.php` gains a fourth reader, so the decision-matrix parity diff becomes four-way — clarity, classic, module and phosphor must agree on every (stored, background, default) input. `probe_render.php` gains a `phosphor` argument. The rail-ink assertions from `probe_clarity.php` are carried over to phosphor's `brand_rail_vars` against the `--pz-rail-*` names, including the `want_ratio` honesty rule (hold to 4.5:1, or to the best any ink could do on that backdrop where 4.5 is unreachable), the ink-against-the-lightest-stratum rule, and the requirement that the shipped band re-emit values byte-identical to `tokens.css`.

CI's **Brand-token contract parity** step already walks `themes/*/brand.php`; phosphor satisfies its eleven keys on merit. `rail_hex_light` joins the list only if the answer to open question 1 is yes. **Favicon endpoint contract parity** requires `themes/phosphor/favicon.php` reading both `favicon` and `favicon_url`; **Designs link their favicon endpoint** requires phosphor's shells to link it and to hardcode no icon asset; the dashlet-override step requires a `BUILT-AGAINST.txt` entry per override.

One new CI step, a **rail-ink scanner**: it fails if any `themes/*/assets/stylesheets/**/*.css` other than that design's `tokens.css` declares a literal colour on a rail selector or a `--*-rail-*` custom property, and it fails if a rail ink declared in `tokens.css` is not among the values that design's `brand.php` re-emits for the shipped band. Today the "no ink literal on a rail" rule is enforced only at the reader; the scanner closes the stylesheet side, which is where a regression would actually be typed, and it walks `themes/*/` so clarity is covered by the same step.

## Out of scope

Light mode for phosphor. New icon artwork. Any change to clarity or classic beyond the shared `rail_hex_light` key. New overridden templates or any change to the seven existing override contracts. Changes to `install.sh` beyond registering a third design name. Making the Branding page's redesign conditional on the active design. A JavaScript copy of the logo-variant resolver. The clarity relook (Part B of the 2026-09-06 draft) and the v4.0.0 release contents (Part C) — both still stand, separately.

## Open questions

1. **Does `rail_hex_light` join CI's hard-coded contract list?** Only clarity can act on it; phosphor and classic would satisfy a grep-based check with a documented no-op, which makes the check pass on a comment rather than on behaviour. Add it (the contract stays complete, at the cost of two token no-ops) or leave it off (the list keeps meaning "keys a design must act on")?
2. **Is a fourth module endpoint acceptable?** The live preview needs `interface/web/customizer/preview.php` — admin-only, read-only, same three checks — to keep the logo-variant resolver in one language. The alternative is a JS approximation that can disagree with what the panel renders, which is the one thing the preview exists to prevent. This expands the module's documented attack surface in SECURITY.md.
3. **What happens when an operator sets a light `login_bg` on phosphor?** The grid, the glow and the glass all assume a dark field. Honour it exactly (the scene degrades, the operator's setting wins), or clamp the field to a dark composite and honour the hue only (the scene survives, the operator's explicit value is not what they see)?
