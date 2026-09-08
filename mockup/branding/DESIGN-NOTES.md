# Branding page — design notes

Mockup of the redesigned `interface/web/customizer/templates/customizer_edit.htm`, rendered inside clarity's real shell by `mockup/build.py`. Nothing under `interface/`, `themes/` or `install.sh` was touched; `mockup/build.py` gained page entries and a shot label, nothing else.

Files: `branding.html` (the fragment), `branding.css` (the module CSS as it would ship, inline-able verbatim into the template's `<style>` block), `sidenav-tools.html` (the Tools sidebar so the page renders in its real place), `shots/`.

Build: `cd mockup && python3 build.py --shoot --only=branding` — the approved mockup, `dark-branding` and `light-branding`. The shipped page is a separate target, `--only=branding-shipped`; `--only` matches a whole page name (with or without its `dark-`/`light-` prefix) and never a fragment of one, so neither run overwrites the other's shots even though both land in `shots/`.

## The verdict being answered

"UI for config page looks lazy" — walls of inline help text, four flat fieldsets with no hierarchy, a small abstract preview of grey bars, stock checkboxes, bare file inputs. Each of those is addressed below by name.

## The idea

**The page is a proof sheet.** This is the only page in the panel where the thing being edited and the thing being looked at are the same thing, so the picture leads and the controls follow. A press proof carries the pull at the top and its colour bar along the trailing edge — the marks as supplied, the name, the measured colours — and that is exactly the information this page has to show. It gives the page a hierarchy that is about the subject rather than about card widgets, and it earns the mono voice for the values (hex, ratios, px) because those are machine-measured, not decoration.

The one bold moment is the proof, and only the proof. Everything else is deliberately quiet: no shadows, no gradients, no tinted panels, one accent (the operator's own, in the picture where it belongs).

## Hierarchy, top to bottom

1. **Page header** — h1 + one line. Unchanged.
2. **`#nz-msg-slot`** — kept directly under the header, where the uploader's banner is relocated to. It sits immediately above the marks that an upload has just changed, which is the reason not to move it into the save bar.
3. **The proof** (the hero) — a full-width mount holding two pulls side by side: the panel (browser tab strip, sidebar with the resolved mark and three real nav labels one of which is active, topbar with search and a user pill, a content card with a heading and an accent primary button) and the login screen on the login background. Below them, inside the same mount and separated by a hairline, **the legend**: the two supplied marks on the grounds they are for, the panel name and the status facts, the light-mode sidebar sample (hidden unless set), and the three colour chips with their hex values.
4. **Identity** and **Colour** — two cards side by side. The right column continues with **Favicon** and **Login screen** so the two columns end level.
5. **Panel visibility** — full width, six labelled switches in a 3-column list.
6. **The save bar** — sticky, hairline over the page ground, `Cancel` then `Save changes` with the primary outermost, and the save flash slot on the left.

Four materials, so the page does not read as one card kit repeated five times: the proof is a recessed viewport on the page ground; the cards are flat card fill with a hairline; the drop zone is a *control* (dashed edge, input well, control radius); the switch list and the save bar are hairlines only, no box.

## Decisions, including where the brief was refined

- **The summary went under the proof, not above it.** Two reasons. Compositionally it reads as the pull's legend — name, marks, colour bar — rather than as a second header competing with the h1. Technically it is what keeps the existing preview script correct with **zero JS changes**: `paintSurfacePane()` uses `querySelector` (first match only), so the first `.nz-prev-rail`, `.nz-prev-rail-light` and `.nz-prev-login` in the DOM must be the *pulls*, not the chips. DOM order is therefore proof → legend, and visual order is the same.
- **The marks are shown once, in the legend, not also inside the drop zones.** There is exactly one `#used_logo` / `#used_logo_on_dark` slot each, and no way to mirror them without new JS. Between the two placements the legend wins: side by side on their own grounds is the one view that answers "is the right artwork in the right slot", which the wordbook says is the point. The drop zones are not left bare — each carries its own `#used_logo_more` strip ("Also used on"), which is *different* information: every surface after the first, per installed design. So the summary shows what you supplied and the control shows where it lands.
- **The preview is not sticky.** A sticky 340px rail is what made it small and abstract, which is the verdict being fixed. Instead the feedback an operator needs *while typing a colour* was moved onto the colour's own line as a measured contrast readout, and the proof is for confirming the whole. Trade-off stated plainly: scrolled down to the Identity card, the proof is off-screen. If that turns out to matter more than the size does, the alternative is to keep this layout and add a compact sticky strip on scroll — but that needs JS and is not in this mockup.
- **The legend row separates facts with hairlines, not middle dots.** "Clarity active · 2 marks · Favicon set" is three independent facts, not one sentence, and a dot-joined meta string is the commonest generated-page tell. Same content, read as a legend.
- **Card titles use the panel's card-title voice, not clarity's tracked-caps `legend` voice.** The `<fieldset><legend>` structure is kept exactly (screen-reader grouping and the existing wordbook keys survive), but a caps eyebrow over each of five cards is chrome repeated five times. Clarity already has both voices; `.panel-heading` is this one.
- **Favicon became its own card** rather than a fifth block inside Identity. It has one slot and no light/dark pair — the wordbook says so itself — and splitting it is what makes the two columns end level. It uses the existing `favicon_head_txt` legend key.
- **The active nav marker moved back onto the active item.** The shipped page put the accent rule on the rail's left edge because the preview's nav items were blank bars and a 2px stub beside one read as a text cursor. The items now carry real labels, so the marker is a marker again — which is also what clarity's own rail draws. The JS still paints `.nz-prev-accent-rule` through `style.color`; only its position changed.
- **The tab-icon preview became a browser tab strip** on top of the panel pull, which is where a tab icon lives. One tab and a hairline — no window buttons, which would be costume rather than information.
- **Switches, not checkboxes.** tform emits a bare `<input type="checkbox">`, so the track is the input itself (`appearance: none`) and the thumb is a **sibling span**, never a pseudo-element: pseudo-elements on `input` are not rendered at all in some engines and this control must not depend on one. Nothing in the switch rules touches focus, so the input keeps whatever indicator the active design gives it.
- **Drop zones are the file input itself, sized up.** A browser already accepts a file dropped onto an `<input type="file">`, so the zone is a real drop target with no script, and the chosen filename stays visible — which a script-driven overlay would hide. `::file-selector-button` is styled to the panel's ghost-button voice.
- **The brand in the mockup is invented (Karoo Hosting) and its colours are deliberately not clarity's.** A preview painted in the design's own palette proves nothing. The ratios shown (4.93 / 12.50 / 17.79 / 15.75) were computed from those hexes, not invented.

## What each hint became

Every paragraph of inline help is now either a single muted line in the flow or a native `<details>` "?" next to the thing it explains. Four one-line leads keep Bootstrap’s `help-block` class, because that is what a muted line under a field is called here; no multi-line wall of help text survives in the reading flow.

| Wordbook key | Was | Now |
|---|---|---|
| `company_name_hint_txt` | help-block | muted line under the field, verbatim |
| `logo_variants_hint_txt` | 5-line paragraph above the pair | one-line summary + "?" carrying the rest |
| `logo_hint_txt` | 5-line paragraph | "?" on "Mark for light backgrounds"; formats/size stay as the drop-zone note |
| `logo_on_dark_hint_txt` | 6-line paragraph | "?" on "Mark for dark backgrounds" |
| `logo_url_hint_txt` | 8-line paragraph | "?" on "Path or URL instead" |
| `logo_url_on_dark_hint_txt` | 4-line paragraph | "?" on "Path or URL instead" |
| `logo_variant_nav_hint_txt`, `logo_variant_login_hint_txt` | two 8-line paragraphs, one per select | one "?" on "Placement" — they said the same thing twice |
| `favicon_intro_txt` | paragraph | one-line summary in the flow |
| `favicon_hint_txt` | 5-line paragraph | "?" on the Favicon card; size/format stays as the drop-zone note |
| `favicon_url_hint_txt` | 4-line paragraph | "?" on "Path or URL instead" |
| `colour_hint_txt` | help-block under login_bg | unchanged — it already was one muted line, so it ships verbatim as the `help-block` under `login_bg` |
| `rail_contrast_txt` + ratio | help-block under the field | the measurement on the colour's own line: `12.50:1 text contrast` |
| `rail_hex_light_hint_txt` | help-block | "?" on "Sidebar, light mode" |
| `show_*_hint_txt` (4) | help-block per row | "?" beside each switch label |
| `credits_hint_txt` | help-block | "?" on "Footer credit: the design" |

The "?" is a `<summary>` with an `aria-label` ("More about …"), so it announces as a button with a purpose rather than as a question mark.

## What the build will need

**New wordbook keys** (en, plus the six translated files):

| Key | Proposed value | Why |
|---|---|---|
| `preview_panel_txt` | `Panel` | the frame now shows tab + sidebar + content, so `preview_nav_txt` ("Navigation") no longer names it |
| `hint_more_txt` | `More about %s` | accessible name for every "?" disclosure |
| `drop_hint_txt` | `Drop a file here or choose one.` | the drop zone's own line |
| `accent_short_txt` / `rail_short_txt` / `rail_light_short_txt` | `Accent` / `Sidebar` / `Light sidebar` | chip captions under a 56px swatch; the full labels do not fit |
| `contrast_short_txt` | `text contrast` | the noun after the ratio |
| `also_used_on_txt` | `Also used on` | label above the `*_more` surfaces strip |
| `summary_design_txt` / `summary_marks_txt` / `summary_favicon_txt` / `summary_favicon_none_txt` | `%s active` / `%d marks` / `Favicon set` / `No favicon` | the three status facts; needs a small server-side summary builder in `customizer_edit.php` |

**Shortened label values for existing keys** (recommended, not required — this is translation churn, so it is a call for the owner): the six `show_*_txt` labels drop their "Show the …" prefix, because a switch already says "show" (`Design picker`, `Software version`, `News feed`, `Donation panel`, `Footer credit: ISPConfig`, `Footer credit: the design`). Likewise `logo_variant_nav_txt` → `Sidebar and header`, `logo_url_txt` / `logo_url_on_dark_txt` / `favicon_url_txt` → `Path or URL instead` (the block heading already says which mark).

**New ids, and the JS lines that would fill them** (all optional — each degrades to today's behaviour if the JS is not extended):

| Id / hook | Needs | If not wired |
|---|---|---|
| `#nz-save-flash` | one line in the existing `#nz-msg-slot` relocation observer, moving core's `.alert-notification` into it | the slot stays empty and the banner stays where core renders it |
| `#nz-accent-ratio`, `#nz-login-ratio` | two `setRatio()` calls — `data.colours.accent` and `data.colours.login` already carry `ratio` | the readouts sit at `—` |
| `.nz-brandchip-hex` (three) | mirror the field value into the caption inside `paintPreview()` | the hex captions go stale until the page reloads |
| `.nz-prev-login-brand` | one `paintNavBrand('.nz-prev-login-brand', login[0])` — the payload already carries login entries | the login pull shows the panel name, which is exactly today's behaviour |

Nothing else changes: every field id and name, `#nz-msg-slot`, the five server-rendered preview slots, the three uploader buttons and their file inputs, the three remove links, the four hex text/picker pairs, `.nz-prevframe` (the save flash), `.nz-brandpreview` (the failure note), `.nz-actions`, and the `data-submit-form` / `data-load-content` contract are carried over unchanged. Every field keeps its `.form-group > label.col-sm-3 + div.col-sm-9` shape, which `markField()`'s `closest('.form-group')` depends on. Verified by script against the shipped template: no id, class or field name the inline script touches is missing, and the first `.nz-prev-rail`, `.nz-prev-rail-light` and `.nz-prev-login` in the DOM are the pulls.

## Constraints — how each was met

- **Colour tokens.** Every colour is `var(--nz-…, <neutral literal>)`; the shipping copy prepends `--pz-` to each chain. Verified: the only raw hexes in `branding.css` are fallback arguments, plus **one deliberate literal** — `.nz-prev-lightbody { background: #F2F5F7 }`, the stock theme's own page grey. That pane depicts a design's *light* colour mode, so `--nz-page` under a dark design would paint a light sidebar on a dark ground and depict neither mode. This is the same exception, for the same reason, that the shipped page already carries.
- **`nz-` classes, `#pageContent` / `#nz-brandpage` prefixes.** Verified by grep: every rule is prefixed, none is unprefixed. `.nz-chip` was renamed `.nz-brandchip` — clarity already ships `.nz-chip` for the dashboard metric pills and the two collided.
- **No `!important`.** The only occurrence in the file is the word inside a comment.
- **No `outline`, no focus styling.** Not one `:focus` or `outline` declaration.
- **Container query on the module's wrapper.** `#nz-brandpage { container-type: inline-size }`, `@container nzbrand (max-width: 900px)` and a second step at 560px, with an `@supports not (container-type: inline-size)` viewport fallback set wide (1200px / 860px) so it errs toward one column. `#pageContent` is never given containment.
- **WCAG AA.** All text is a token pair clarity already measures, at 11px and up (the smallest are `--nz-text-muted` on `--nz-card`: 6.49:1 dark, ~5.0:1 light). The mini-panel's 10px chrome labels are the operator's own measured inks, chosen server-side by the same contrast rule the panel uses. Contrast was computed for the mockup brand: accent 4.93:1, sidebar 12.50:1, light sidebar 17.79:1, login 15.75:1.
- **Dark and light.** Both rendered and reviewed; see the shots.

## Not honoured, and why

- **"A brand summary card at the top."** It is a legend under the proof instead, inside the same mount. Reasons above: composition, and the preview script's first-match painting, which would otherwise need a JS change to `paintSurfacePane()`.
- **"Logo uploaders as drop zones with the current mark inside."** The zones are real drop targets, but the current mark is in the legend — there is one slot per variant and mirroring it needs JS that does not exist. Each zone carries its `*_more` surfaces strip instead.
- **"Contrast readout on one line"** is shown on all four colour rows, but only the two sidebar readouts are live today; accent and login background need the two `setRatio()` calls listed above (the payload already carries the numbers).

## Shots

| File | Viewport | Shows |
|---|---|---|
| `shots/dark-branding-fold.png` | 1440×900, viewport | what an operator sees on landing: the proof, the legend, the top of the cards, the sticky save bar doing its job |
| `shots/dark-branding-desktop.png` | 1440×900, full page | the whole layout (the sticky bar is pinned static for the capture, or it would paint at the fold and cover what is behind it) |
| `shots/light-branding-fold.png` | 1440×900, viewport | clarity light mode |
| `shots/light-branding-desktop.png` | 1440×900, full page | clarity light mode, whole layout |
| `shots/dark-branding-narrow.png` | 1000×900, full page | the collapse: one column, proof stacked, switches at two columns |
