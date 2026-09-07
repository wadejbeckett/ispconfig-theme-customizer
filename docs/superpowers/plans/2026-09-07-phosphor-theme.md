# phosphor Design Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship `themes/phosphor/` — a third, dark-only ISPConfig design ("black glass with phosphor glow") that satisfies the existing brand-token contract on merit, alongside `clarity` and `classic`, with no ISPConfig core file modified.

**Architecture:** phosphor is a sibling of `clarity`: its own seven template overrides, its own six-file stylesheet layer under `assets/stylesheets/phosphor/`, its own three pre-auth brand endpoints (`brand.php`, `title.php`, `favicon.php`), and its own progressive-enhancement script. It inherits every other template from `themes/default` and every vendor asset from `themes/default/assets/`. All colour lives in `tokens.css` as `--pz-*` custom properties; `brand.php` overrides those properties at request time from `sys_ini`, deriving every rail ink from `rail_hex` by measured contrast using the algorithm `themes/clarity/brand.php` already ships.

**Tech Stack:** PHP 8 (no framework, no Composer — the endpoints are dependency-free and pre-auth), plain CSS custom properties (no build step), vanilla ES5-compatible JavaScript, ISPConfig's vlibTemplate `.htm` templates, bash installers, `tests/brand/` PHP probes, GitHub Actions CI, Python 3 + Playwright for the offline mockup harness.

**Spec:** `docs/superpowers/specs/2026-09-07-phosphor-design.md` — read it before Task 1 and keep it open. This plan argues from that spec; where the plan departs from it, the departure is stated inline and repeated in the Deviation register below.

**Sibling plan (NOT this plan):** the Branding-page redesign, the new `rail_hex_light` brand key, `interface/web/customizer/preview.php`, and the branding mockup fragment are covered by a separate plan. This plan touches `interface/web/customizer/` **not at all**. Where `rail_hex_light` appears here it is only because phosphor's reader must *read* it as a documented no-op.

## Global Constraints

Every task's requirements implicitly include this section.

- **No ISPConfig core file is modified.** Everything ships inside `themes/phosphor/`. The only files outside it that change are `tests/`, `.github/`, `install.sh`, `uninstall.sh`, `mockup/` and the repository's own docs.
- **New theme directory is `themes/phosphor/` only.** Do not touch `themes/clarity/` or `themes/classic/` except where a CI step is generalised to walk `themes/*/` (which must keep passing for both).
- **Vendor-neutral.** No vendor, operator or company name in any file, asset, comment or string. The panel's identity arrives through the Branding page.
- **Dark only in v1.** No light remap block, no `:root[data-*-theme='light']` scope anywhere, no theme-switcher control in the shell, no `localStorage` theme bootstrap.
- **Every brand key in the contract is honoured.** CI's hard-coded list is exactly: `accent_hex`, `rail_hex`, `login_bg`, `logo_url`, `logo_url_on_dark`, `logo_on_dark`, `logo_variant_nav`, `logo_variant_login`, `show_version`, `show_design_picker`, `company_name`. `rail_hex_light` is additionally read and documented as a **no-op** — phosphor has no light scope to paint.
- **No literal ink on a rail surface.** Every rail ink derives from `rail_hex` by measured contrast (`brand_rail_vars()`), and the only place a rail ink may be written as a literal is `themes/phosphor/assets/stylesheets/phosphor/tokens.css`.
- **Components read semantic tokens only.** No raw hex, `rgb()`, `rgba()` or named colour in `app.css`, `components.css`, `login.css` — only `var(--pz-…)`. `tokens.css` is the single exception; `base.css` and `icons.css` are verbatim/near-verbatim ports and are exempt by an explicit allowlist in the scanner.
- **Pre-auth endpoints follow clarity's pattern character-for-character** wherever the spec says parity: the config-locate cascade, the mysqli read, the MIME re-assertion, the ETag/`Cache-Control` policy, the DB-fault no-op path, and every validator regex.
- **Fonts are self-hosted OFL subsets.** No CDN, no external request, ever — from any file, including the mockup.
- **`BUILT-AGAINST.txt` is stamped for ISPConfig 3.3.1p1** and lists every one of the seven overrides; CI fails if a dashboard override has no entry.
- **Accessibility floors:** body ink ≥7:1 on the ground and ≥4.5:1 on every rung *including the opaque glass fallback* `#12161B`; `--pz-ink-faint` is barred from text; ink on any amber fill is `--pz-on-accent`, never white; control boundaries ≥3:1 (SC 1.4.11); interactive targets ≥24×24px; focus is the two-stop ring paired with a transparent `outline` so it survives forced-colors.
- **Glow budget is four places and no more:** the login mark and its cursor, a primary button on hover/focus, the active rail marker, the chart line's fill.
- **Local PHP is 8.3.6 CLI without `mysqli`, `dom`, `xml` or `mbstring`.** See *What runs locally vs only in CI* below.
- **Version:** this ships in the next major, which also carries the GPLv3 relicence per the project's own decision. **This plan does not do the relicence** — no `LICENSE` change, no header change. It is a separate task tracked elsewhere; Task 14 only records the dependency.

## What runs locally vs only in CI

| Check | Locally? | Command |
|---|---|---|
| PHP syntax on themes/, tests/, .github/ | yes | `find themes tests .github -name '*.php' -print0 \| while IFS= read -r -d '' f; do php -l "$f"; done` |
| `tests/brand/run.php` (all probes + resolver parity) | **yes** — verified passing on this machine at 271 assertions before any change; the probes never open a database | `php tests/brand/run.php` |
| The new rail-ink scanner | yes | `php .github/scripts/rail_scan.php` |
| Byte-identity / prefix-equivalence of `base.css` and `icons.css` | yes | `bash .github/scripts/design_asset_parity.sh` |
| Cache-buster, favicon-link, dashlet-override, contract-parity greps | yes | run the same shell bodies from `.github/workflows/ci.yml` |
| `bash -n install.sh uninstall.sh` and the unknown-flag check | yes | `bash -n install.sh; bash install.sh --definitely-not-a-flag` |
| Mockup render + screenshots | yes (Playwright is installed here) | `python3 mockup/build.py --design=phosphor --shoot` |
| `tests/svg/run.php` | **CI only** — needs `dom`/`xml`; it is untouched by this plan | — |
| `.github/scripts/lang_check.php` | CI (it parses `.lng` as text, so it may run locally too) — untouched by this plan | — |
| `themes/phosphor/brand.php` end-to-end against a real `sys_ini` | **neither** — needs `mysqli` and a panel. The DB block is excluded from the probes by design (`probe_render.php` slices the file below it). Prove it on a test panel before release; this plan does not claim it. |

## File Structure

Created under `themes/phosphor/` (all new):

| Path | Responsibility |
|---|---|
| `brand.php` | The brand READER. Emits `--pz-*` overrides + logo/wordmark/credit/version/picker rules from `sys_ini`. Pre-auth, read-only, always valid CSS. |
| `title.php` | Branded `document.title` + `<img alt>` + text-wordmark failover. Pre-auth JS endpoint. |
| `favicon.php` | Tab-icon endpoint. Pre-auth, never 404s. |
| `README.md` | What the design is, what is inside, how to re-theme it. |
| `BUILT-AGAINST.txt` | Upgrade-safety contract: the seven overrides and what each preserves. CI requires an entry per dashboard override. |
| `templates/main.tpl.htm` | App frame: rail 232px, blurred 56px topbar, no theme switcher. |
| `templates/topnav.tpl.htm` | Module rail nav. |
| `templates/main_login.tpl.htm` | The login scene: grid, card, cursor. |
| `templates/dashboard/dashboard.htm` | Dashboard page override. |
| `templates/dashboard/modules.htm` | Module launcher dashlet. |
| `templates/dashboard/metrics.htm` | Instrument tiles + the four canvases. |
| `templates/dashboard/donate.htm` | Donation dashlet (disclosure, no script). |
| `assets/stylesheets/phosphor/tokens.css` | **The only file that may contain a colour literal.** Dark values at `:root`; `color-scheme: dark`; no remap block. |
| `assets/stylesheets/phosphor/icons.css` | clarity's icons.css with its selector scope renamed `.nz`→`.pz`. MIT notice travels with it. |
| `assets/stylesheets/phosphor/base.css` | **Byte-identical** copy of clarity's base.css. |
| `assets/stylesheets/phosphor/app.css` | Frame: rail, topbar, sidebar, drawer, content column, footer. |
| `assets/stylesheets/phosphor/components.css` | Tables, instrument tiles, launcher, buttons, forms, accordion, tabs, alerts, action bar, select2, datetimepicker. |
| `assets/stylesheets/phosphor/login.css` | The login scene only. |
| `assets/fonts/space-grotesk/{space-grotesk.css,*.woff2,LICENSE.txt}` | Grotesk 400/500/700. |
| `assets/fonts/jetbrains-mono/{jetbrains-mono.css,*.woff2,LICENSE.txt}` | Mono 400/500 + true italic 400. |
| `assets/javascripts/pz-theme.js` | Chart.js dark theming, drawer, search shortcuts, a11y enhancement. Progressive enhancement only. |
| `assets/images/wordmark-white.svg` | The neutral shipped mark (the `content:` swap target for a custom logo). |
| `assets/favicon/*` | The shipped icon set + platform artefacts. |

Modified outside the theme:

| Path | Change |
|---|---|
| `tests/brand/probe_phosphor.php` | New: phosphor's helper probe (rail ink, OKLCH drop, halo rule, token contrast, resolver matrix). |
| `tests/brand/run.php` | Registers `phosphor` as a fourth probe and `render:phosphor` as a third render probe. |
| `tests/brand/probe_render.php` | Token prefix and design-specific assertions parameterised by design. |
| `.github/scripts/rail_scan.php` | New: the rail-ink scanner + the "no literal colour outside tokens.css" rule. |
| `.github/scripts/design_asset_parity.sh` | New: `base.css` byte-identity and `icons.css` prefix-equivalence. |
| `.github/workflows/ci.yml` | Two new steps; four existing steps generalised from `themes/clarity/…` to a walk of `themes/*/`. |
| `install.sh`, `uninstall.sh` | Register `phosphor` in `ALL_DESIGNS` and the `--design=` case arm. |
| `mockup/build.py` | Parameterised over the design directory (`--design=`); clarity stays the default and its output stays byte-identical. |
| `README.md`, `DESIGN.md`, `CONTRIBUTING.md`, `UPGRADING.md`, `SECURITY.md` | Documentation. There is **no `CHANGELOG`** in this repository (verified: `find . -maxdepth 2 -iname 'CHANGELOG*'` returns nothing), so nothing to update there. |

## Deviation register — where this plan departs from the spec or the mockup

Each of these is a decision this plan makes because the spec or the approved mockup is under-specified or measurably wrong. Implement the plan's version; the reasons are in the tasks.

1. **`--pz-rail-active` is `#1D252F`, not the mockup's `#12171E`.** The spec says the active stratum is "the band shaded 15%". Measured: `brand_shade('#0B0E12', 15)` = `#1D252F`. `tokens.css` must ship the value `brand.php` re-emits for the shipped band, or the scanner's byte-identity half fails by construction.
2. **Rail tokens are written in `brand_rgba()`'s exact output format** — `rgba(255, 255, 255, 0.88)`, spaces after commas, leading zero — not the mockup's `rgba(255,255,255,.88)`. Same reason.
3. **`base.css` is byte-identical but needs four alias tokens.** clarity's `base.css` contains six `var(--nz-…, fallback)` references across four distinct properties (`--nz-success-text`, `--nz-success-tint`, `--nz-danger-text`, `--nz-danger-tint`). Renaming them would break the spec's "any divergence between the two copies is a bug". Instead `tokens.css` declares those four `--nz-*` names as aliases onto phosphor tokens, and `base.css` stays byte-identical.
4. **`icons.css` is not literally verbatim.** It is clarity's file with `.nz `→`.pz `, `body.nz-login`→`body.pz-login`, `body.nz `→`body.pz ` (140 substitutions) plus a 9-line provenance header, exactly as the approved mockup does it. CI asserts the equivalence rather than byte-identity.
5. **The shell keeps `<img>` brand slots.** The mockup's rail draws a CSS `.pz-mark` glyph plus a text `.pz-wordmark`, and the login draws a `.pzl-brandname` span. Neither is reachable by `brand.php`'s `content:` override or `title.php`'s `alt`/error-swap, so a branded panel would show phosphor's own mark instead of the operator's. The shipping templates therefore use `<img>` in `#logo`, `.pz-topbar-brand` and `.pzl-brand`, as clarity does; the glyph-plus-name look is what the *shipped* wordmark SVG and the company-name branch render.
6. **`font-display: swap`, not the mockup's `block`.** `block` hides text for up to 3s. The mockup used it for screenshot determinism; the shipping theme follows clarity's `inter.css`.
7. **Five tokens the spec's table does not list** are added to `tokens.css`, each because a component rule would otherwise carry a literal: `--pz-glow-rail` (the active marker's glow, which the mockup writes as `rgba(255,163,1,.65)` on a rail selector — the exact thing the scanner forbids), `--pz-scanline`, `--pz-grid-mask`, `--pz-mark-halo` (the rescue halo `brand.php` chooses per stored variant) and `--pz-backdrop` (the drawer scrim, a surface the single-page mockup never renders). Four `--nz-*` alias tokens are also declared, for the reason in item 3.
8. **`--pz-accent-dim` is derived in PHP, not in CSS.** `brand.php` is loaded after `tokens.css`, so a CSS-side `oklch(from …)` derivation would be dead the moment `brand.php` emits an accent. The OKLCH lightness drop is implemented in PHP and pinned by a test: `brand_oklch_drop('#FFA301', 0.136)` must return exactly `#D17800` (verified numerically while writing this plan).
9. **The amber-alpha tokens ARE re-expressed in CSS** with `color-mix(in srgb, var(--pz-accent) N%, transparent)` inside an `@supports` gate, with the shipped `rgba()` literal as the ungated declaration. Custom properties accept almost any value at parse time, so an ungated unsupported value would win and then fail at computed-value time — the `@supports` gate is what makes the fallback real.
10. **Spec open question 3 (a light `login_bg` on phosphor) is answered "honour it exactly".** The operator's explicit value wins; the grid line, the scanline and the mark halo are then derived from it by measured contrast so the scene degrades instead of vanishing. Flag this to the owner if they wanted the clamp instead — it is a one-function change (`brand_login_overlays()`).
11. **Spec open questions 1 and 2 are out of scope here.** `rail_hex_light` joining CI's contract list, and `preview.php`, belong to the sibling plan. phosphor's reader is written so that *either* answer needs no change to it.

---

### Task 1: The token layer and the three pre-auth brand endpoints

This is deliberately one task and not four. The moment `themes/phosphor/` exists on disk, CI's **Brand-token contract parity** and **Favicon endpoint contract parity** steps walk it (`for reader in themes/*/brand.php`, `for design in themes/*/`) and fail if `brand.php` or `favicon.php` is missing. The smallest commit that leaves CI green therefore contains the directory, all three endpoints and the token file they are asserted against. `probe_phosphor.php` also asserts that the shipped band re-emits `tokens.css`'s exact values, so `tokens.css` cannot be deferred either.

**Files:**
- Create: `themes/phosphor/assets/stylesheets/phosphor/tokens.css`
- Create: `themes/phosphor/brand.php`
- Create: `themes/phosphor/title.php`
- Create: `themes/phosphor/favicon.php`
- Test: `tests/brand/probe_phosphor.php`
- Modify: `tests/brand/run.php` (register the fourth probe and the third render probe)
- Modify: `tests/brand/probe_render.php` (parameterise the token prefix and the design-specific blocks)

**Interfaces:**
- Consumes: `tests/brand/harness.php`'s `load_helpers()`, `t_ok()`, `t_eq()`, `t_done()`, `h_lum()`, `h_contrast()`, `h_flatten()`, `h_decls()`, `h_rails()`, `h_variant_matrix()` — all already exist, none change.
- Produces, in `themes/phosphor/brand.php` (function names are identical to clarity's on purpose; `run.php` gives every probe its own process, so there is no collision):
  - `brand_parse_config(string $config): array`
  - `brand_hex(array $branding, string $key): string` — `''` or a validated `#rrggbb`
  - `brand_logo_variant(string $ref, string $data): string`
  - `brand_logo_variant_pref(string $stored, string $bg_hex, string $design_default): string`
  - `brand_logo_for_pref(string $pref, string $on_light, string $on_dark): string`
  - `brand_logo_var(string $src, array &$vars): string` — returns `--pz-brand-logo` or `--pz-brand-logo-alt`
  - `brand_is_dark(string $hex): bool`
  - `brand_luminance(string $hex): float`
  - `brand_contrast(string $a, string $b): float`
  - `brand_readable(string $hex, float $l, string $bg, float $min): string`
  - `brand_rail_vars(string $rail, string $accent = ''): string` — the `--pz-rail-*` block
  - `brand_rail_white(string $bg, array $alphas, float $min): string`
  - `brand_flatten(string $hex, float $alpha, string $bg): string`
  - `brand_mark_halo_filter(string $logo_on_light, string $logo_on_dark): string` — **phosphor-specific**, replaces clarity's `brand_login_light_filter()`
  - `brand_oklch_drop(string $hex, float $dl): string` — **phosphor-specific**
  - `brand_on_accent(string $accent): string` — **phosphor-specific**, returns `#FFFFFF` or `#06080A` by measured contrast
  - `brand_login_overlays(string $login_bg): string` — **phosphor-specific**, the grid/scanline/halo re-derivation
  - `brand_shade`, `brand_rgba`, `brand_hex_to_hsl`, `brand_hsl_to_hex` — unchanged from clarity
- Later tasks rely on: every `--pz-*` name declared in `tokens.css` (Tasks 3–7 read them), and `brand_rail_vars()`'s exact token names (Task 4's scanner cross-checks them against `tokens.css`).

---

- [ ] **Step 1: Write `tokens.css`**

Create `themes/phosphor/assets/stylesheets/phosphor/tokens.css`. Values are the spec's token table verbatim, with the four corrections from the Deviation register (rail-active `#1D252F`, `brand_rgba()` formatting on the rail alphas, the four `--nz-*` base.css aliases, the three extra tokens).

```css
/* ============================================================
 * PHOSPHOR — DESIGN TOKENS (dark only)
 * ------------------------------------------------------------
 * The single source of truth for every colour, radius, shadow,
 * size and duration in this design. Component rules read ONLY
 * the semantic aliases below — never a raw hex, never an rgba.
 * .github/scripts/rail_scan.php enforces that.
 *
 * Dark values at :root and nothing else: phosphor has no light
 * scope in v1, which is why there is no remap block and why
 * brand.php emits no light-mode selectors.
 *
 * Brand-derived tokens. brand.php re-emits these at request
 * time from the sys_ini [branding] keys, so the values here are
 * SHIPPED DEFAULTS, not constants:
 *   accent_hex -> --pz-accent, --pz-accent-dim, --pz-on-accent
 *   rail_hex   -> --pz-band and the whole --pz-rail-* family
 *   login_bg   -> the login field plus --pz-grid-line
 * The rail values below are written in brand_rgba()'s exact
 * output format because .github/scripts/rail_scan.php asserts
 * that the reader re-emits them byte for byte on the shipped
 * band: a cosmetic reformat here is a build failure there.
 * ============================================================ */

:root {
  color-scheme: dark;

  /* ---- ground ladder: three rungs, not five ---- */
  --pz-ground:     #06080A;
  --pz-band:       #0B0E12;
  --pz-glass:      rgba(176, 208, 255, 0.07);
  --pz-pane-solid: #12161B;
  --pz-raised:     #171D23;
  --pz-lift:       #1D242C;

  /* ---- rail: shipped defaults for --pz-band. brand.php re-derives
     every one of these for any other band, by measured contrast. ---- */
  --pz-rail-text:       rgba(255, 255, 255, 0.88);
  --pz-rail-text-hover: #FFFFFF;
  --pz-rail-heading:    rgba(255, 255, 255, 0.66);
  --pz-rail-hover:      rgba(255, 255, 255, 0.05);
  --pz-rail-active:     #1D252F;
  --pz-rail-edge:       rgba(255, 255, 255, 0.07);
  --pz-rail-accent:     var(--pz-accent);

  /* ---- hairlines and the lit edge ---- */
  --pz-edge:        rgba(255, 255, 255, 0.10);
  --pz-edge-soft:   rgba(255, 255, 255, 0.055);
  --pz-edge-strong: rgba(255, 255, 255, 0.16);
  --pz-edge-input:  rgba(255, 255, 255, 0.35);
  --pz-edge-top:    rgba(176, 208, 255, 0.16);

  /* ---- accent: the only warm colour on screen ---- */
  --pz-accent:     #FFA301;
  --pz-accent-dim: #D17800;   /* = OKLCH lightness of --pz-accent minus 0.136 */
  --pz-on-accent:  #06080A;   /* white on this amber is 2.00:1 — prohibited */
  --pz-accent-subtle: rgba(255, 163, 1, 0.08);
  --pz-accent-medium: rgba(255, 163, 1, 0.10);
  --pz-accent-strong: rgba(255, 163, 1, 0.28);

  /* ---- ink ---- */
  --pz-ink-bright:      #FFFFFF;
  --pz-ink-base:        #EDEDED;
  --pz-ink-sub:         #C1C5C5;
  --pz-ink-muted:       #9AA0A0;
  --pz-ink-placeholder: #9AA0A0;
  --pz-ink-faint:       #62696A;   /* decorative + disabled ONLY: 3.58:1, fails AA */

  /* ---- status: only ever on genuine status ---- */
  --pz-success: #00FF88;
  --pz-warning: #F2D935;
  --pz-danger:  #FF4D4D;
  --pz-info:    #00D4FF;

  /* base.css is a BYTE-IDENTICAL port of clarity's file and carries six
     var(--nz-…, fallback) references across these four names. Aliasing them
     here is what lets that file stay byte-identical — the alternative was to
     edit the copy, which is the one thing its provenance forbids. */
  --nz-success-text: var(--pz-success);
  --nz-success-tint: var(--pz-accent-subtle);
  --nz-danger-text:  var(--pz-danger);
  --nz-danger-tint:  rgba(255, 77, 77, 0.10);

  --pz-radius-ctl:   4px;
  --pz-radius-card:  8px;
  --pz-radius-modal: 16px;
  --pz-radius-pill:  9999px;

  --pz-blur:        10px;
  --pz-blur-strong: 18px;

  /* ---- glow: four places, named. nowhere else. ---- */
  --pz-glow-ring: 0 0 0 1px var(--pz-accent);
  --pz-glow-soft: 0 10px 34px -12px rgba(255, 163, 1, 0.60);
  --pz-glow-text: 0 0 12px rgba(255, 163, 1, 0.45);
  /* Glow 3 of 4 — the active rail marker. It is a token and not a literal in
     app.css because it is painted ON the rail, and the rail is a surface the
     operator repaints; rail_scan.php rejects any colour literal there. */
  --pz-glow-rail: 0 0 10px 0 rgba(255, 163, 1, 0.65);

  --pz-shadow-lift: 0 22px 50px -28px rgba(0, 0, 0, 0.90);
  --pz-focus: 0 0 0 2px var(--pz-ground), 0 0 0 4px var(--pz-accent);

  /* ---- type ---- */
  --pz-sans: 'Space Grotesk', 'Segoe UI', system-ui, -apple-system, sans-serif;
  --pz-mono: 'JetBrains Mono', ui-monospace, 'SFMono-Regular', Menlo, monospace;
  --pz-fs-title:     24px;
  --pz-fs-section:   16px;
  --pz-fs-cardtitle: 14px;
  --pz-fs-body:      14px;
  --pz-fs-secondary: 13px;
  --pz-fs-caption:   11px;
  --pz-fs-numeral:   28px;
  --pz-fs-code:      12.5px;
  --pz-track-label:  0.1em;

  /* ---- metrics ---- */
  --pz-rail-w:      232px;
  --pz-topbar-h:    56px;
  --pz-content-max: 1280px;
  --pz-content-pad: 28px;
  --pz-ctl-h:       36px;
  --pz-ctl-h-sm:    26px;

  /* ---- motion ---- */
  --pz-fast:  120ms;
  --pz-base:  240ms;
  --pz-slow:  480ms;
  --pz-blink: 1100ms;
  --pz-ease:  cubic-bezier(0.4, 0, 0.2, 1);

  /* ---- the login scene, and only the login scene ---- */
  --pz-grid-pitch: 34px;
  /* Its own token, not a reuse of --pz-edge: it is masked, sits under the
     scanline, and re-derives from login_bg, so it has to be tunable without
     moving every hairline in the panel. */
  --pz-grid-line: rgba(176, 208, 255, 0.16);
  --pz-grid-mask: radial-gradient(ellipse 640px 500px at 50% 46%, rgba(0, 0, 0, 1) 0%, rgba(0, 0, 0, 0.62) 46%, rgba(0, 0, 0, 0) 82%);
  --pz-scanline: repeating-linear-gradient(to bottom, rgba(0, 0, 0, 0) 0px, rgba(0, 0, 0, 0) 2px, rgba(0, 0, 0, 0.22) 3px, rgba(0, 0, 0, 0.22) 4px);
  /* The rescue halo for a mark drawn for a background it is not on. Every
     phosphor surface is dark, so unlike clarity's dark drop-shadow this one
     is light; brand.php chooses between it and `none` per stored variant. */
  --pz-mark-halo: drop-shadow(0 0 8px rgba(255, 255, 255, 0.55));
}

/* The amber-alpha tokens re-expressed from the accent's own hue, so that an
 * operator's accent_hex carries into every wash, tint and border without
 * brand.php having to emit six more declarations.
 *
 * Gated on @supports, and the gate is load-bearing rather than cautious: a
 * custom property accepts almost any value at PARSE time, so an ungated
 * color-mix() would win over the rgba() above it on source order in every
 * engine, and would then fail at computed-value time in one that cannot
 * evaluate it — leaving the token guaranteed-invalid and every rule reading it
 * unstyled. The gate is what makes the literal above a real fallback.
 *
 * --pz-accent-dim is deliberately NOT derived here. brand.php is linked AFTER
 * this sheet, so a CSS-side derivation would be dead the moment an operator
 * sets an accent; it is computed in PHP instead, and pinned by a test. */
@supports (color: color-mix(in srgb, red 8%, transparent)) {
  :root {
    --pz-accent-subtle: color-mix(in srgb, var(--pz-accent)  8%, transparent);
    --pz-accent-medium: color-mix(in srgb, var(--pz-accent) 10%, transparent);
    --pz-accent-strong: color-mix(in srgb, var(--pz-accent) 28%, transparent);
    --pz-glow-soft: 0 10px 34px -12px color-mix(in srgb, var(--pz-accent) 60%, transparent);
    --pz-glow-text: 0 0 12px color-mix(in srgb, var(--pz-accent) 45%, transparent);
    --pz-glow-rail: 0 0 10px 0 color-mix(in srgb, var(--pz-accent) 65%, transparent);
  }
}
```

The `@supports` block is inside `tokens.css`, so the rail scanner's rule 1a — which forbids a `--*-rail-*` declaration **outside** `tokens.css` — is satisfied, and rule 2 skips `--pz-glow-rail` because it is not a `--pz-rail-*` name. Keep it that way: a rail token redeclared in an `@supports` block in `app.css` would pass rule 1a's file check and still be a rail ink the reader cannot move.

- [ ] **Step 2: Write the failing probe**

Create `tests/brand/probe_phosphor.php`. It must fail right now, because `themes/phosphor/brand.php` does not exist and `load_helpers()` exits 2 on an unreadable path.

```php
<?php
/**
 * themes/phosphor/brand.php — the pure helpers.
 *
 * Run through tests/brand/run.php, which gives this file a process of its own:
 * clarity, classic and phosphor all define the same function names.
 *
 * Structure mirrors probe_clarity.php deliberately. Where an assertion is the
 * same question about a different token family, it is written the same way, so
 * a reviewer can diff the two probes and see only the design differences.
 */

require_once __DIR__ . '/harness.php';

$TOKENS = __DIR__ . '/../../themes/phosphor/assets/stylesheets/phosphor/tokens.css';
$BRAND  = __DIR__ . '/../../themes/phosphor/brand.php';

load_helpers($BRAND);

/**
 * The ratio to hold an ink to on a backdrop: 4.5:1 where 4.5:1 exists, and the
 * best any ink could manage where it does not. Identical to probe_clarity.php's
 * — a mid grey tops out at 4.69:1 against black, so a flat 4.5 would be
 * demanding the impossible and would hide the real defect, which is the rule
 * picking the WORSE of the two ink directions.
 */
function want_ratio($backdrop) {
    $best = max(h_contrast('#FFFFFF', $backdrop), h_contrast('#000000', $backdrop));
    return min(4.5, $best - 0.01);
}

/* ---- the rail ink family ------------------------------------------------ */
foreach (h_rails() as $rail) {
    $d = h_decls(brand_rail_vars($rail));

    t_eq("rail $rail: --pz-band is the operator's own colour",
        isset($d['--pz-band']) ? $d['--pz-band'] : null, $rail);

    foreach (array('--pz-rail-text', '--pz-rail-text-hover', '--pz-rail-heading') as $ink) {
        if (!t_ok("rail $rail: $ink is emitted", isset($d[$ink]))) continue;
        $flat = h_flatten($d[$ink], $rail);
        if (!t_ok("rail $rail: $ink parses", $flat !== '', $d[$ink])) continue;
        $c = h_contrast($flat, $rail);
        $w = want_ratio($rail);
        t_ok(sprintf('rail %s: %s reads at %.2f:1 (>= %.2f)', $rail, $ink, $c, $w), $c >= $w, "flattened $flat");
    }

    // The strata are backgrounds the same inks are printed on, so the ratio has
    // to hold against them too — a token set that only considered the base band
    // goes unreadable on the selected item alone.
    foreach (array('--pz-rail-active', '--pz-rail-hover') as $bgtok) {
        if (!t_ok("rail $rail: $bgtok is emitted", isset($d[$bgtok]))) continue;
        $bg = h_flatten($d[$bgtok], $rail);
        if (!t_ok("rail $rail: $bgtok parses", $bg !== '', $d[$bgtok])) continue;
        foreach (array('--pz-rail-text', '--pz-rail-text-hover') as $inktok) {
            if (!isset($d[$inktok])) continue;
            $ink = h_flatten($d[$inktok], $bg);
            if ($ink === '') continue;
            $c = h_contrast($ink, $bg);
            $w = want_ratio($bg);
            t_ok(sprintf('rail %s: %s on %s reads at %.2f:1 (>= %.2f)', $rail, $inktok, $bgtok, $c, $w), $c >= $w);
        }
    }

    if (isset($d['--pz-rail-active']) && isset($d['--pz-rail-hover'])) {
        t_ok("rail $rail: the active and hover strata are distinct",
            h_flatten($d['--pz-rail-active'], $rail) !== h_flatten($d['--pz-rail-hover'], $rail),
            $d['--pz-rail-active'] . ' vs ' . $d['--pz-rail-hover']);
        t_ok("rail $rail: the hover stratum is distinct from the band",
            h_flatten($d['--pz-rail-hover'], $rail) !== strtoupper($rail), $d['--pz-rail-hover']);
    }

    if (t_ok("rail $rail: --pz-rail-accent is emitted", isset($d['--pz-rail-accent']))) {
        $acc = h_flatten($d['--pz-rail-accent'], $rail);
        if ($acc !== '') {
            $c = h_contrast($acc, $rail);
            $w = min(3.0, max(h_contrast('#FFFFFF', $rail), h_contrast('#000000', $rail)) - 0.01);
            t_ok(sprintf('rail %s: --pz-rail-accent reads at %.2f:1 (>= %.2f)', $rail, $c, $w), $c >= $w, "flattened $acc");
        }
    }

    if (t_ok("rail $rail: --pz-rail-edge is emitted", isset($d['--pz-rail-edge']))) {
        t_ok("rail $rail: --pz-rail-edge is not the band itself",
            h_flatten($d['--pz-rail-edge'], $rail) !== strtoupper($rail));
    }
}

/* ---- the shipped band re-emits tokens.css, byte for byte ----------------
 * The rescue only exists for bands the design never anticipated. On the band
 * phosphor actually ships, the reader must produce the values already in
 * tokens.css or the panel changes appearance the moment an operator sets
 * rail_hex to the colour it already was.
 */
// Comments are stripped first: tokens.css documents its own tokens by name,
// and a scan of the raw file can read an explanation as a declaration.
$shipped = h_decls(preg_replace('#/\*.*?\*/#s', '', (string)file_get_contents($TOKENS)));
$emitted = h_decls(brand_rail_vars('#0B0E12'));
foreach (array('--pz-rail-text', '--pz-rail-text-hover', '--pz-rail-heading',
               '--pz-rail-hover', '--pz-rail-active', '--pz-rail-edge') as $tok) {
    t_eq("shipped band re-emits $tok byte-identically",
        isset($emitted[$tok]) ? $emitted[$tok] : null,
        isset($shipped[$tok]) ? $shipped[$tok] : null);
}
t_eq('shipped band active stratum is the band shaded 15%',
    isset($emitted['--pz-rail-active']) ? $emitted['--pz-rail-active'] : null,
    brand_shade('#0B0E12', 15));

/* ---- the OKLCH accent drop ----------------------------------------------
 * The spec fixes the dim accent as the shipped accent with its OKLCH lightness
 * dropped by 0.136. That is an exact number, so it gets an exact test: the
 * implementation has to land on the value tokens.css ships.
 */
t_ok('brand_oklch_drop() exists', function_exists('brand_oklch_drop'));
if (function_exists('brand_oklch_drop')) {
    t_eq('the shipped accent dropped 0.136 in OKLCH is the shipped dim accent',
        brand_oklch_drop('#FFA301', 0.136), '#D17800');
    t_eq('tokens.css ships that same value',
        isset($shipped['--pz-accent-dim']) ? $shipped['--pz-accent-dim'] : null, '#D17800');
    // A drop of zero is the identity, which is what proves the round trip
    // through OKLab is not quietly shifting the colour on its own.
    t_eq('a zero drop round-trips', brand_oklch_drop('#FFA301', 0.0), '#FFA301');
    t_eq('a drop that would go below black clamps to black', brand_oklch_drop('#FFA301', 5.0), '#000000');
}

/* ---- ink on a filled accent is measured, never assumed ------------------- */
t_ok('brand_on_accent() exists', function_exists('brand_on_accent'));
if (function_exists('brand_on_accent')) {
    t_eq('the shipped amber takes the dark ink', brand_on_accent('#FFA301'), '#06080A');
    t_eq('a dark custom accent takes the bright ink', brand_on_accent('#3B0A6E'), '#FFFFFF');
    t_eq('a pale custom accent takes the dark ink', brand_on_accent('#FFF9C4'), '#06080A');
    foreach (array('#FFA301', '#3B0A6E', '#FFF9C4', '#00D4FF', '#7F0000') as $a) {
        $ink = brand_on_accent($a);
        $c   = h_contrast($ink, $a);
        t_ok(sprintf('ink on %s reads at %.2f:1 (>= 4.5)', $a, $c), $c >= 4.5);
        t_ok("ink on $a is never white when white is the worse choice",
            !($ink === '#FFFFFF' && h_contrast('#06080A', $a) > $c));
    }
}

/* ---- the rescue halo ----------------------------------------------------
 * phosphor has no light surface anywhere, so the question clarity answers per
 * colour mode is answered here per STORED VARIANT: with both marks stored the
 * operator has asserted which is which and the mark keeps its own colours;
 * with one stored they asserted nothing (sys_ini.custom_logo is the only logo
 * column ISPConfig has ever had) and the halo is what keeps a dark mark
 * visible on a black ground.
 */
t_ok('brand_mark_halo_filter() exists', function_exists('brand_mark_halo_filter'));
if (function_exists('brand_mark_halo_filter')) {
    t_eq('both variants stored: the mark keeps its own colours',
        brand_mark_halo_filter('data:image/png;base64,AA', 'data:image/png;base64,BB'), 'filter: none;');
    t_ok('only the light-background mark: the halo stays',
        strpos(brand_mark_halo_filter('data:image/png;base64,AA', ''), 'drop-shadow') !== false);
    t_ok('only the dark-background mark: the halo stays',
        strpos(brand_mark_halo_filter('', 'data:image/png;base64,BB'), 'drop-shadow') !== false);
    t_ok('no mark at all: the halo stays',
        strpos(brand_mark_halo_filter('', ''), 'drop-shadow') !== false);
}

/* ---- the login scene follows the field ----------------------------------
 * The operator's login_bg is honoured exactly, so the overlays have to follow
 * it or a light field leaves an invisible grid, an inverted scanline and a
 * halo that rescues nothing. Asserted on both sides of the crossover.
 */
t_ok('brand_login_overlays() exists', function_exists('brand_login_overlays'));
if (function_exists('brand_login_overlays')) {
    $dark  = h_decls(brand_login_overlays('#06080A'));
    $light = h_decls(brand_login_overlays('#F5F5F5'));
    foreach (array('--pz-grid-line', '--pz-scanline', '--pz-mark-halo') as $tok) {
        t_ok("a dark field emits $tok",  isset($dark[$tok]));
        t_ok("a light field emits $tok", isset($light[$tok]));
        if (isset($dark[$tok]) && isset($light[$tok])) {
            t_ok("$tok differs between a dark and a light field", $dark[$tok] !== $light[$tok],
                $dark[$tok] . ' vs ' . $light[$tok]);
        }
    }
    // A dark field must restate tokens.css verbatim, or a login_bg that is
    // merely a different dark silently redesigns the scene.
    foreach (array('--pz-grid-line', '--pz-scanline', '--pz-mark-halo') as $tok) {
        t_eq("a dark field restates tokens.css's $tok", $dark[$tok], $shipped[$tok]);
    }
    // The grid line has to be visible on the field it is drawn over. 1.15:1 is
    // not a WCAG floor — a masked 1px decorative grid is not a control and not
    // text — it is the floor below which the scene has no grid at all.
    foreach (array('#06080A' => $dark, '#F5F5F5' => $light) as $bg => $set) {
        $c = h_contrast(h_flatten($set['--pz-grid-line'], $bg), $bg);
        t_ok(sprintf('the grid line is visible on %s (%.3f:1 >= 1.15)', $bg, $c), $c >= 1.15);
    }
}

/* ---- the token ladder holds its contrast floors --------------------------
 * Measured against the OPAQUE composite of every glass surface, never against
 * the blurred one: glass must never be load-bearing for contrast, and where
 * backdrop-filter is unsupported the opaque fallback is what the eye receives.
 */
$rungs = array('--pz-ground', '--pz-pane-solid', '--pz-raised', '--pz-lift');
$body  = array('--pz-ink-base' => 4.5, '--pz-ink-sub' => 4.5, '--pz-ink-muted' => 4.5,
               '--pz-ink-placeholder' => 4.5, '--pz-accent' => 4.5);
foreach ($body as $ink => $min) {
    foreach ($rungs as $rung) {
        $c = h_contrast($shipped[$ink], $shipped[$rung]);
        t_ok(sprintf('%s on %s reads at %.2f:1 (>= %.1f)', $ink, $rung, $c, $min), $c >= $min);
    }
}
// AAA on the page, which is the floor the spec sets for body ink specifically.
t_ok(sprintf('--pz-ink-base on --pz-ground reads at %.2f:1 (>= 7.0, AAA)',
        h_contrast($shipped['--pz-ink-base'], $shipped['--pz-ground'])),
    h_contrast($shipped['--pz-ink-base'], $shipped['--pz-ground']) >= 7.0);
// The glass tint must composite to the opaque fallback, or the two are two
// different surfaces and every pair above was measured against the wrong one.
t_eq('--pz-glass over --pz-ground composites to --pz-pane-solid',
    h_flatten($shipped['--pz-glass'], $shipped['--pz-ground']),
    strtoupper($shipped['--pz-pane-solid']));
// The faint ink is barred from text and the test says so rather than leaving
// it to a comment: if someone "fixes" it to pass AA, the bar has moved and the
// register in DESIGN.md is wrong.
t_ok('--pz-ink-faint is below AA, as documented (decorative and disabled only)',
    h_contrast($shipped['--pz-ink-faint'], $shipped['--pz-ground']) < 4.5);
// SC 1.4.11: a control boundary against both the well it encloses and the page.
foreach (array('--pz-raised', '--pz-pane-solid') as $well) {
    $edge = h_flatten($shipped['--pz-edge-input'], $shipped[$well]);
    foreach (array($well, '--pz-ground') as $against) {
        $c = h_contrast($edge, $shipped[$against]);
        t_ok(sprintf('--pz-edge-input on %s reads at %.2f:1 against %s (>= 3.0)', $well, $c, $against), $c >= 3.0);
    }
}
// White is prohibited on the amber fill, and the number is why.
t_ok(sprintf('white on --pz-accent is %.2f:1 — prohibited',
        h_contrast('#FFFFFF', $shipped['--pz-accent'])),
    h_contrast('#FFFFFF', $shipped['--pz-accent']) < 3.0);
t_ok(sprintf('--pz-on-accent on --pz-accent reads at %.2f:1',
        h_contrast($shipped['--pz-on-accent'], $shipped['--pz-accent'])),
    h_contrast($shipped['--pz-on-accent'], $shipped['--pz-accent']) >= 4.5);

/* ---- luminance is defined once ------------------------------------------ */
t_ok('brand_luminance() exists', function_exists('brand_luminance'));
if (function_exists('brand_luminance')) {
    foreach (h_rails() as $hex) {
        t_ok("luminance agrees with the spec for $hex", abs(brand_luminance($hex) - h_lum($hex)) < 1e-9);
        t_eq("brand_is_dark is brand_luminance < 0.5 for $hex", brand_is_dark($hex), brand_luminance($hex) < 0.5);
    }
}

t_ok('brand_contrast() exists', function_exists('brand_contrast'));
if (function_exists('brand_contrast')) {
    t_ok('black on white is ~21:1', abs(brand_contrast('#000000', '#FFFFFF') - 21.0) < 0.05);
    t_ok('a colour against itself is 1:1', abs(brand_contrast('#0065AB', '#0065AB') - 1.0) < 1e-9);
}

/* ---- the resolver, and the matrix run.php cross-compares ----------------- */
t_eq('explicit choice beats a contradicting background',
    brand_logo_variant_pref('on_dark', '#FFFFFF', 'on_light'), 'on_dark');
t_eq('an unrecognised stored value is automatic',
    brand_logo_variant_pref('garbage', '#FFFFFF', 'on_dark'), 'on_light');
t_eq('a trailing newline does not make a hex valid',
    brand_logo_variant_pref('', "#FF0000\n", 'on_dark'), 'on_dark');

$matrix = array();
foreach (h_variant_matrix() as $row) {
    $matrix[] = brand_logo_variant_pref($row[0], $row[1], $row[2]);
}
echo 'MATRIX ' . json_encode($matrix) . "\n";

t_done();
```

- [ ] **Step 3: Run the probe and watch it fail**

Run: `php tests/brand/probe_phosphor.php`
Expected: exit 2 with `cannot read …/themes/phosphor/brand.php` on stderr (that is `load_helpers()`'s own guard, not a PHP fatal).

- [ ] **Step 4: Create `themes/phosphor/brand.php` from clarity's, then apply the phosphor edits**

Start from an exact copy so the pre-auth machinery is character-for-character clarity's:

```bash
mkdir -p themes/phosphor/assets/stylesheets/phosphor
cp themes/clarity/brand.php themes/phosphor/brand.php
```

**Two string markers in the copied file are load-bearing and must survive every edit below**, because the test harness slices the file on them rather than including it (the first ~200 lines are a live endpoint that opens a database connection):

- `/* ---- resolve + validate the contract values ---- */` — `tests/brand/probe_render.php` slices from here to `echo $css;` and runs that region with `$branding`, `$custom_logo` and `$company_name` supplied directly. Everything the endpoint body needs must live between those two markers.
- `Helpers — pure, dependency-free.` — `tests/brand/harness.php`'s `load_helpers()` and `.github/scripts/rail_emit.php` both slice at this exact string. A helper that ends up *above* it silently leaves the tests, and a renamed marker makes both callers exit 2 rather than pass vacuously.

Then make **only** these changes. Everything not listed — the config-locate cascade, the mysqli block, the `mysqli_report(MYSQLI_REPORT_OFF)` idiom, the `company_name` normalisation with its byte-wise control-char strip and multibyte-safe 40-char cap, the MIME re-assertion, the ETag/`Cache-Control` policy, the `show_version` and `show_design_picker` rules, and every helper's body except where named — stays byte-identical to clarity's.

**4a. The header docblock.** Retitle it and replace clarity's "Design constraints" preamble's first paragraph with phosphor's. Keep every bullet about pre-auth safety, HTTP 200, injection safety and the `company_name`/`title.php` parity rule verbatim. Add these three paragraphs:

```php
 * phosphor is DARK ONLY in v1. There is no light scope anywhere in this
 * file: no :root[data-*-theme='light'] block, no colour-mode branch in the
 * login rules, and no per-mode logo slot. A future light scope is a new
 * block here and a remap in tokens.css, not a rewrite.
 *
 * config [branding] rail_hex_light is READ and is a DOCUMENTED NO-OP on
 * this design. It names the rail colour for a LIGHT scope, and phosphor has
 * no light scope to paint. It is named here, in code rather than only in a
 * comment, so that the key cannot be renamed on the writer side without this
 * file failing CI's contract grep along with every other design's reader —
 * a design that silently ignores a key it has been handed is exactly the
 * failure that grep exists to catch. Reading it and doing nothing is the
 * honest implementation; guessing a light rail from it would be worse.
 *
 * The ONE structural difference from themes/clarity/brand.php: because every
 * phosphor surface is dark, the "which mark suits this backdrop" question is
 * answered once rather than once per colour mode. That is why this file has
 * brand_mark_halo_filter() where clarity has brand_login_light_filter(), and
 * why the halo it can return is a LIGHT one.
```

**4b. `$css`'s banner.** Replace with:
```php
$css = "/* phosphor brand overrides — generated by themes/phosphor/brand.php */\n";
```

**4c. Read `rail_hex_light` beside the other three hexes**, immediately after the `$login_bg` line:

```php
$login_bg = brand_hex($branding, 'login_bg');

/* rail_hex_light — read, deliberately unused. See the header: phosphor has no
 * light scope, so there is no surface for it to paint. It is read here rather
 * than merely mentioned in a comment so the name lives in code, and it is
 * assigned to a variable that is then unset so no later edit can accidentally
 * start honouring it without also removing this note. */
$rail_light_unused = brand_hex($branding, 'rail_hex_light');
unset($rail_light_unused);
```

**4d. Replace the whole accent + rail block** (clarity's `/* ---- accent: re-hue the blue ramp … */` through the closing of the `elseif ($rail !== '')` branch) with:

```php
/* ---- accent ----------------------------------------------------------------
 * phosphor has no colour ramp to re-hue: the accent appears at exactly one
 * value plus a dim companion, and every wash and border that carries its hue is
 * a color-mix() of --pz-accent in tokens.css. So there is nothing to walk here
 * — one colour in, three declarations out.
 *
 * --pz-accent-dim is the same OKLCH lightness drop the shipped pair uses
 * (0.136), computed rather than approximated in HSL: OKLCH is perceptually
 * uniform and an HSL drop of the "same" amount changes a yellow and a blue by
 * visibly different amounts. It is computed HERE and not in tokens.css because
 * this sheet is linked AFTER tokens.css — a CSS-side oklch(from …) derivation
 * would be overridden by anything emitted below and would therefore be dead.
 *
 * --pz-on-accent is CHOSEN BY MEASUREMENT between the page ground and white, so
 * a pale custom accent gets dark ink and a dark one gets light ink. It is never
 * a constant: white on the shipped amber is 2.00:1, which is the defect this
 * decision exists to prevent from recurring under a different hue.
 */
if ($accent !== '') {
    $root  = '  --pz-accent: '     . $accent . ";\n";
    $root .= '  --pz-accent-dim: ' . brand_oklch_drop($accent, 0.136) . ";\n";
    $root .= '  --pz-on-accent: '  . brand_on_accent($accent) . ";\n";
    if ($rail !== '') {
        //* Hand the accent over rather than letting the rail re-derive one
        //* beside it: the marker on the rail and the accent in the content
        //* column are the same colour by definition, and two derivations of
        //* "the accent" are two chances to disagree.
        $root .= brand_rail_vars($rail, $accent);
    }
    $css .= ":root {\n{$root}}\n";
} elseif ($rail !== '') {
    // A rail set without an accent — the band, and the ink that has to read on it.
    $css .= ":root {\n" . brand_rail_vars($rail, '') . "}\n";
}
```

**4e. Replace the login-background block** with phosphor's. clarity paints two accent radials over the base and repeats the whole thing for light mode; phosphor paints the field flat and re-derives the scene's three overlays from it:

```php
/* ---- login field -----------------------------------------------------------
 * The field is flat: the atmosphere on this login screen is the 34px grid and
 * the scanline, not a gradient, and laying a radial over them would put a
 * second light source in a scene whose whole subject is one.
 *
 * Spec open question 3, answered: an operator who sets a light login_bg gets
 * exactly the colour they set. Their explicit value wins. What is NOT left to
 * chance is the scene on top of it — the grid line, the scanline and the mark's
 * rescue halo are re-derived from the field by measured contrast, so a light
 * field degrades into a legible light scene instead of an invisible dark one.
 */
if ($login_bg !== '') {
    $css .= "body.pz-login {\n  background: {$login_bg};\n}\n";
    $css .= ":root {\n" . brand_login_overlays($login_bg) . "}\n";
}
```

**4f. Replace the logo block.** The variant resolution, the `$has_logo` slot gate, the one-artwork-per-custom-property scheme and the reduced-motion gate are clarity's, unchanged in substance. What changes: the property and selector names, the login design default, and the removal of the light-mode rule.

```php
$logo_on_dark = brand_logo_variant(
    isset($branding['logo_url_on_dark']) ? $branding['logo_url_on_dark'] : '',
    isset($branding['logo_on_dark'])     ? $branding['logo_on_dark']     : ''
);
$logo_on_light = brand_logo_variant(
    isset($branding['logo_url']) ? $branding['logo_url'] : '',
    $custom_logo
);
// Gate on the SLOTS, not on a resolved value: a surface preference chooses
// between two marks and must never be able to decide there is no mark at all
// and drop the panel into the company-name branch.
$has_logo = ($logo_on_light !== '' || $logo_on_dark !== '');

// NAV — #logo img on the rail and .pz-topbar-brand img on the mobile header
// chip. Both sit on --pz-band, which rail_hex repaints through
// brand_rail_vars() above, so the band is the real luminance input here.
$nav_pref = brand_logo_variant_pref(
    isset($branding['logo_variant_nav']) ? $branding['logo_variant_nav'] : '',
    $rail,
    'on_dark'
);

// LOGIN — .pzl-brand img sits directly on body.pz-login, which login_bg
// repaints, so login_bg is a real luminance input.
//
// The design default is 'on_dark' and NOT clarity's '' sentinel. clarity passes
// '' because with nothing set its login field follows the colour mode and there
// is genuinely no single answer. phosphor has one colour mode and its unset
// field is --pz-ground (#06080A), so there is always an answer and passing a
// sentinel would only invent a case this design cannot be in.
$login_pref = brand_logo_variant_pref(
    isset($branding['logo_variant_login']) ? $branding['logo_variant_login'] : '',
    $login_bg,
    'on_dark'
);

if ($has_logo) {
    $nav_src   = brand_logo_for_pref($nav_pref,   $logo_on_light, $logo_on_dark);
    $login_src = brand_logo_for_pref($login_pref, $logo_on_light, $logo_on_dark);

    // Emit each DISTINCT artwork ONCE into a custom property and reference it
    // from the use sites. An uploaded mark is a base64 data URI up to the 45 KB
    // cap; repeating it per selector multiplied this sheet by the number of
    // slots, on a response served UNAUTHENTICATED on every login render.
    $logo_vars = array();
    $nav_var   = brand_logo_var($nav_src,   $logo_vars);
    $login_var = brand_logo_var($login_src, $logo_vars);
    $logo_root = '';
    foreach ($logo_vars as $src => $prop) {
        $logo_root .= "  {$prop}: url(\"{$src}\");\n";
    }
    $css .= ":root {\n{$logo_root}}\n";

    // Both dimensions auto plus a max box: the mark keeps its aspect ratio at
    // any width, where a fixed height would distort a wide one.
    $css .= "#logo img { content: var({$nav_var}); height: auto; width: auto; max-height: 26px; max-width: 180px; }\n";
    $css .= ".pz-topbar-brand img { content: var({$nav_var}); height: auto; width: auto; max-height: 18px; max-width: 120px; }\n";
    $css .= ".pzl-brand img { content: var({$login_var}); height: auto; width: auto; max-height: 36px; max-width: 100%; }\n";

    // The halo is a rescue, not decoration: it is what makes a mark readable on
    // a background it was not drawn for. Every phosphor surface is dark, so it
    // applies to all three slots rather than to a light-mode one — and unlike
    // clarity's it is a LIGHT halo, because the thing it has to rescue is a
    // dark mark on a black ground. login.css must therefore set no filter of
    // its own on .pzl-brand img; this is the only place `filter` is decided.
    $halo = brand_mark_halo_filter($logo_on_light, $logo_on_dark);
    $css .= "#logo img, .pz-topbar-brand img, .pzl-brand img { {$halo} }\n";

    // Mask the content swap: on a hard refresh the SHIPPED mark paints for a
    // frame or two before the custom image decodes — a white-label leak.
    //
    // Gated on prefers-reduced-motion: NO-PREFERENCE, and the gate is a fix and
    // not tidying: app.css and login.css both emit `animation: none !important`
    // under (prefers-reduced-motion: reduce), which beats this rule's normal
    // `animation` from any source order, while its `opacity: 0` has nothing
    // competing with it. Without the gate every reduced-motion visitor to a
    // branded panel gets opacity: 0 with nothing left to animate it back — no
    // logo at all. Outside the gate they get the mark immediately and pay the
    // one-frame flash, which is the right way round.
    $css .= "@media (prefers-reduced-motion: no-preference) {\n";
    $css .= "  @keyframes pzBrandIn { to { opacity: 1; } }\n";
    $css .= "  #logo img, .pz-topbar-brand img, .pzl-brand img { opacity: 0; animation: pzBrandIn 0.18s ease 0.05s forwards; }\n";
    $css .= "}\n";
} elseif ($company_name !== '') {
    // No mark, but the panel is named: the NAME becomes the wordmark.
    //
    // The two RAIL slots take the rail's own ink token, never a literal. They
    // sit on --pz-band, which rail_hex repaints, so a hardcoded white is a
    // wordmark that vanishes the moment the operator chooses a light sidebar —
    // and the panel NAME is the worst thing in the interface to lose.
    // --pz-rail-text-hover is #FFFFFF in tokens.css and brand_rail_vars()
    // restates it verbatim on every dark band, so nothing moves by default.
    //
    // The LOGIN slot is the scene's one bold moment and carries the text glow
    // (glow 1 of 4). It does not sit on the rail, so a rail token there would
    // follow the sidebar's colour onto a surface the sidebar never touches.
    //
    // Escape for the CSS string context rather than deleting: inside
    // content:"…" only " and \ have meaning and both escape losslessly.
    // CR/LF were already removed on read. Backslashes are doubled FIRST so the
    // backslash added in front of a quote is not itself doubled. < and > are
    // deliberately untouched — this sheet is only ever fetched through
    // <link rel='stylesheet'>, never inlined into <style>, so there is no HTML
    // context to break out of, and deleting them silently rewrote legitimate
    // panel names while title.php rendered them intact on the same screen.
    $wordmark_css = str_replace(array('\\', '"'), array('\\\\', '\\"'), $company_name);
    $css .= "#logo img, .pz-topbar-brand img { content: \"{$wordmark_css}\"; "
          . "font: 700 15px/1.3 'Space Grotesk', 'Segoe UI', system-ui, sans-serif; "
          . "color: var(--pz-rail-text-hover); white-space: nowrap; letter-spacing: -0.015em; }\n";
    $css .= ".pzl-brand img { content: \"{$wordmark_css}\"; "
          . "font: 500 26px/1.3 'Space Grotesk', 'Segoe UI', system-ui, sans-serif; "
          . "color: var(--pz-ink-bright); text-shadow: var(--pz-glow-text); "
          . "white-space: nowrap; letter-spacing: -0.02em; }\n";
}
```

**4g. The credit lines** — rename the three classes:

```php
if (!$show_ispc)  { $css .= ".pz-credit-ispconfig, .pz-credit-sep { display: none; }\n"; }
if (!$show_theme) { $css .= ".pz-credit-theme { display: none; }\n"; }
```

**4h. `show_version` and `show_design_picker`** — leave both blocks byte-identical to clarity's. Their selectors name core's own ids and core's own page, not this design's classes, and `probe_render.php` asserts the design-picker rule is the same single string across every design.

**4i. `brand_logo_var()`** — the two property names:

```php
        $vars[$src] = empty($vars) ? '--pz-brand-logo' : '--pz-brand-logo-alt';
```

**4j. `brand_rail_vars()`** — same algorithm, same comments, phosphor's token names and default accent. The dark branch's literals must equal `tokens.css`'s, which is what Step 2's byte-identity block proves.

```php
function brand_rail_vars($rail, $accent = '')
{
    //* The marker keeps the brand accent where there is one. #FFA301 is
    //* tokens.css's own --pz-accent, so with no accent_hex this branch re-emits
    //* the value the sheet already had and nothing moves.
    $accent_src = ($accent !== '') ? $accent : '#FFA301';

    $out = "  --pz-band: {$rail};\n";

    //* Which ink direction by MEASUREMENT, not by a lightness pivot. The
    //* crossover where black and white read equally well sits at luminance
    //* 0.1791, close enough to the 0.184 figure usually quoted that bands
    //* between the two — #767676 is one — get handed the WORSE of the two inks
    //* by a rule that rounds. Comparing the ratios is exact and needs no constant.
    if (brand_contrast('#FFFFFF', $rail) >= brand_contrast('#000000', $rail)) {
        //* A dark band: tokens.css's own values, so the shipped band and every
        //* band dark enough to carry them render exactly as they always have.
        //* Verified rather than assumed, because "dark" is a range and the
        //* alphas were chosen for one band: each ink walks up the design's own
        //* alpha ladder until it clears the ratio and only then falls back to
        //* solid white. The ladder's FIRST entry is the shipped value, so a
        //* band that never needed help never sees a different number.
        $active = brand_shade($rail, 15);

        //* The hover tint lightens the band, and on a band near the crossover
        //* that alone can push white ink under AA. Tinting the other way costs
        //* nothing — the row still changes, just downward — and buys the ratio
        //* back. The shipped band reads 17.46:1 on the lightening tint and
        //* keeps it, so nothing moves for the design as shipped.
        $hover     = 'rgba(255, 255, 255, 0.05)';
        $hover_hex = brand_flatten('#FFFFFF', 0.05, $rail);
        if (brand_contrast('#FFFFFF', $hover_hex) < 4.5) {
            $hover     = 'rgba(0, 0, 0, 0.05)';
            $hover_hex = brand_flatten('#000000', 0.05, $rail);
        }

        //* White ink is hardest to read on the LIGHTEST thing it is printed on,
        //* and it is printed on all three: the band, the selected row and the
        //* hover tint. Choosing against the band alone leaves the ink legible
        //* everywhere except the row under the pointer.
        $hard = $rail;
        foreach (array($active, $hover_hex) as $bg) {
            if (brand_luminance($bg) > brand_luminance($hard)) $hard = $bg;
        }

        return $out
            . "  --pz-rail-active: {$active};\n"
            . "  --pz-rail-edge: rgba(255, 255, 255, 0.07);\n"
            . '  --pz-rail-text: ' . brand_rail_white($hard, array(0.88, 1.0), 4.5) . ";\n"
            . "  --pz-rail-text-hover: #FFFFFF;\n"
            . "  --pz-rail-hover: {$hover};\n"
            . '  --pz-rail-heading: ' . brand_rail_white($hard, array(0.66, 0.78, 0.88, 1.0), 4.5) . ";\n"
            . '  --pz-rail-accent: ' . (brand_contrast($accent_src, $rail) >= 3.0
                    ? $accent_src
                    : brand_readable($accent_src, 59, $rail, 3.0)) . ";\n";
    }

    //* A light band — the case tokens.css has no values for at all, because
    //* this design has no light band. The inks are walked out of the band's OWN
    //* hue rather than dropped to black, so a branded rail still looks branded.
    list(, , $l) = brand_hex_to_hsl($rail);

    //* The strata move AWAY from the ink, i.e. lighter, so they cannot erode
    //* the contrast the ink is about to be chosen for. They are SHADES, not the
    //* black tints the dark branch uses: a black tint pushes the backdrop
    //* toward the crossover, and on a band sitting near it that is enough to
    //* flip which ink reads better.
    //*
    //* Direction comes from the HEADROOM, not a threshold. Clamping to 100
    //* collapses both strata onto pure white for every band above lightness 92
    //* — #FAFAFA, #F5F5F5, the near-whites an operator actually types — so a
    //* hovered row and a selected row would be painted the same colour.
    $dir    = (($l + 8.0) > 100.0) ? -1 : 1;
    $active = brand_shade($rail, $l + $dir * 8);
    $hover  = brand_shade($rail, $l + $dir * 4);

    //* Dark ink is hardest to read on the DARKEST thing it is printed on.
    $hard = $rail;
    foreach (array($active, $hover) as $bg) {
        if (brand_luminance($bg) < brand_luminance($hard)) $hard = $bg;
    }

    return $out
        . "  --pz-rail-active: {$active};\n"
        . "  --pz-rail-edge: rgba(0, 0, 0, 0.12);\n"
        . '  --pz-rail-text: ' . brand_readable($rail, $l, $hard, 7.0) . ";\n"
        . '  --pz-rail-text-hover: ' . brand_readable($rail, $l, $hard, 10.0) . ";\n"
        . "  --pz-rail-hover: {$hover};\n"
        . '  --pz-rail-heading: ' . brand_readable($rail, $l, $hard, 4.5) . ";\n"
        . '  --pz-rail-accent: ' . brand_readable($accent_src, 59, $hard, 3.0) . ";\n";
}
```

**4k. Replace `brand_login_light_filter()` with `brand_mark_halo_filter()`:**

```php
/**
 * What a brand slot does with a mark that may not have been drawn for the
 * background it is on: keep its own colours, or wear the rescue halo.
 *
 * clarity asks this per COLOUR MODE, because its login page is light in one of
 * them. phosphor has one mode and every one of its surfaces is dark, so the
 * question is asked once, of the STORED VARIANTS, and the answer applies to all
 * three slots at once.
 *
 * ONE stored variant means the operator asserted nothing: sys_ini.custom_logo is
 * the only logo column ISPConfig has ever had, and what that single mark was
 * drawn for depends entirely on the design it was uploaded against — on stock,
 * whose header is #F2F5F7, it is almost certainly dark artwork, and dark artwork
 * on #06080A is an invisible logo. That is the population this endpoint exists
 * to serve, so one variant keeps the rescue.
 *
 * With BOTH stored the assertion is real — two slots labelled by background —
 * and the mark keeps its own colours. That also holds when the operator has
 * FORCED a variant: an "Always X" control that second-guessed itself would have
 * no function.
 *
 * The halo is LIGHT, which is the one substantive difference from clarity's. It
 * is emitted as a var() rather than a literal so the value lives in tokens.css
 * with every other appearance decision.
 */
function brand_mark_halo_filter($logo_on_light, $logo_on_dark)
{
    if ($logo_on_light !== '' && $logo_on_dark !== '') {
        return 'filter: none;';
    }
    return 'filter: var(--pz-mark-halo);';
}
```

**4l. Add `brand_on_accent()`, `brand_oklch_drop()` and `brand_login_overlays()`** to the helper tail (below the `Helpers — pure, dependency-free.` marker — `tests/brand/harness.php` slices the file at that exact string, so anything above it is untestable):

```php
/**
 * The ink to print on a filled accent: the page ground, or white — whichever
 * actually reads on it, measured.
 *
 * A constant is what this used to be everywhere, and it is wrong for exactly
 * the reason the rail ink family exists: the accent is the operator's colour,
 * not the design's. White on the shipped amber is 2.00:1. A dark custom accent
 * flips the answer, and nothing else in this file would notice.
 *
 * The ground rather than pure black, so a filled control reads as a hole cut
 * back to the page rather than as a separate black object.
 */
function brand_on_accent($accent)
{
    return (brand_contrast('#FFFFFF', $accent) > brand_contrast('#06080A', $accent))
        ? '#FFFFFF'
        : '#06080A';
}

/**
 * $hex with its OKLCH lightness reduced by $dl, back as #rrggbb.
 *
 * OKLCH and not HSL because the drop has to look like the same drop on every
 * hue: HSL lightness is a channel average with no perceptual meaning, so the
 * "same" drop darkens a yellow far more than a blue. The shipped pair is the
 * fixed point that pins this — brand_oklch_drop('#FFA301', 0.136) is '#D17800',
 * which is the value tokens.css ships, and tests/brand/probe_phosphor.php holds
 * it there.
 *
 * sRGB -> linear -> LMS -> OKLab -> OKLCh, adjust L, and back. Constants are
 * Björn Ottosson's published matrices. Out-of-gamut results are clamped
 * per channel on the way out, which is a gamut CLIP and not a gamut MAP: it can
 * desaturate a very chromatic colour slightly. That is acceptable here because
 * the only consumer is a dimmed companion to a colour that is already in gamut,
 * and a mapper would be a great deal of arithmetic on a pre-auth endpoint.
 */
function brand_oklch_drop($hex, $dl)
{
    $c = ltrim($hex, '#');
    $lin = array();
    for ($i = 0; $i < 3; $i++) {
        $v = hexdec(substr($c, $i * 2, 2)) / 255;
        $lin[$i] = ($v <= 0.04045) ? ($v / 12.92) : pow((($v + 0.055) / 1.055), 2.4);
    }
    list($r, $g, $b) = $lin;

    $l_ = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
    $m_ = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
    $s_ = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
    $lc = ($l_ >= 0) ? pow($l_, 1 / 3) : -pow(-$l_, 1 / 3);
    $mc = ($m_ >= 0) ? pow($m_, 1 / 3) : -pow(-$m_, 1 / 3);
    $sc = ($s_ >= 0) ? pow($s_, 1 / 3) : -pow(-$s_, 1 / 3);

    $L = 0.2104542553 * $lc + 0.7936177850 * $mc - 0.0040720468 * $sc;
    $A = 1.9779984951 * $lc - 2.4285922050 * $mc + 0.4505937099 * $sc;
    $B = 0.0259040371 * $lc + 0.7827717662 * $mc - 0.8086757660 * $sc;

    $L = max(0.0, min(1.0, $L - (float)$dl));

    $lc = $L + 0.3963377774 * $A + 0.2158037573 * $B;
    $mc = $L - 0.1055613458 * $A - 0.0638541728 * $B;
    $sc = $L - 0.0894841775 * $A - 1.2914855480 * $B;
    $l3 = $lc * $lc * $lc;
    $m3 = $mc * $mc * $mc;
    $s3 = $sc * $sc * $sc;

    $out = array(
         4.0767416621 * $l3 - 3.3077115913 * $m3 + 0.2309699292 * $s3,
        -1.2684380046 * $l3 + 2.6097574011 * $m3 - 0.3413193965 * $s3,
        -0.0041960863 * $l3 - 0.7034186147 * $m3 + 1.7076147010 * $s3,
    );
    $hexout = '#';
    foreach ($out as $v) {
        $v = max(0.0, min(1.0, $v));
        $v = ($v <= 0.0031308) ? (12.92 * $v) : (1.055 * pow($v, 1 / 2.4) - 0.055);
        $hexout .= sprintf('%02X', (int)round(max(0.0, min(1.0, $v)) * 255));
    }
    return $hexout;
}

/**
 * The three login-scene overlays, re-derived from the field the operator set.
 *
 * The scene assumes a dark field: the grid is a light hairline, the scanline is
 * a black stripe and the mark's halo is white. Honouring a light login_bg
 * exactly — which is what this design does, because an explicit value the
 * operator typed must be the value they see — would leave all three invisible
 * or inverted. So the field is honoured and the overlays follow it: measured,
 * with the same white-versus-black comparison the rail ink uses, never a
 * lightness pivot.
 *
 * The scanline flips to a WHITE stripe on a light field rather than being
 * removed. Removing it would change the scene's identity on a colour change;
 * inverting it keeps the same texture at the same strength.
 */
function brand_login_overlays($login_bg)
{
    $light_reads = (brand_contrast('#FFFFFF', $login_bg) >= brand_contrast('#000000', $login_bg));
    if ($light_reads) {
        //* A dark field, i.e. the design as drawn: restate tokens.css verbatim
        //* so a login_bg that is merely a different dark moves nothing.
        return "  --pz-grid-line: rgba(176, 208, 255, 0.16);\n"
             . "  --pz-scanline: repeating-linear-gradient(to bottom, rgba(0, 0, 0, 0) 0px, rgba(0, 0, 0, 0) 2px, rgba(0, 0, 0, 0.22) 3px, rgba(0, 0, 0, 0.22) 4px);\n"
             . "  --pz-mark-halo: drop-shadow(0 0 8px rgba(255, 255, 255, 0.55));\n";
    }
    return "  --pz-grid-line: rgba(11, 20, 40, 0.20);\n"
         . "  --pz-scanline: repeating-linear-gradient(to bottom, rgba(255, 255, 255, 0) 0px, rgba(255, 255, 255, 0) 2px, rgba(255, 255, 255, 0.22) 3px, rgba(255, 255, 255, 0.22) 4px);\n"
         . "  --pz-mark-halo: drop-shadow(0 0 8px rgba(6, 8, 10, 0.55));\n";
}
```

- [ ] **Step 5: Run the probe again**

Run: `php tests/brand/probe_phosphor.php`
Expected: every line `ok`, a trailing `# N passed, 0 failed`, and one `MATRIX […]` line. If the byte-identity block fails, the mismatch is between `tokens.css` and `brand_rail_vars()`'s dark branch — fix `tokens.css`, not the reader, because the reader's format is `brand_rgba()`'s and is shared with clarity.

- [ ] **Step 6: Create `title.php`**

```bash
cp themes/clarity/title.php themes/phosphor/title.php
```

Change exactly two things and nothing else — the endpoint is otherwise character-for-character clarity's, including the `stripslashes()` before parsing, the identical control-character strip, the `json_encode` flag set, the `false`-return guard, and the uncapped `document.title` versus 40-char visible wordmark rule:

- the first docblock line, to name phosphor;
- the selector list in `arm()`, from `"#logo img,.nz-topbar-brand img,.nzl-brand img"` to `"#logo img,.pz-topbar-brand img,.pzl-brand img"`;
- the failover span's class, from `s.className="nz-wordmark-text"` to `s.className="pz-wordmark-text"`.

Add this note under the docblock, because the parity rule is a real constraint on future edits:

```php
 * The company_name normalisation here is byte-for-byte themes/clarity/
 * title.php's and byte-for-byte themes/phosphor/brand.php's. The three
 * co-render on the login page — the tab title and the alt text from here, the
 * CSS wordmark from there — and they must never derive different strings from
 * the same sys_ini row. Change one, change all three.
```

- [ ] **Step 7: Create `favicon.php`**

```bash
cp themes/clarity/favicon.php themes/phosphor/favicon.php
```

Change exactly two things:

- the docblock's first lines, to name phosphor and to point its "must stay identical to" reference at `themes/clarity/` and `themes/classic/`;
- nothing in the `$fallbacks` list — it already reads `__DIR__ . '/assets/favicon/…'`, which resolves into phosphor's own asset directory. Task 8 creates those three files; until then the endpoint takes its documented last resort (a 1×1 transparent PNG) rather than 404ing, which is exactly the behaviour it is written for.

Confirm the two contract keys are still read literally as `$branding['favicon']` and `$branding['favicon_url']` — CI's favicon step greps for `['favicon']` and `['favicon_url']` including the brackets.

- [ ] **Step 8: Register phosphor in `run.php`**

In `tests/brand/run.php`, add one entry to each map. Everything else in that file — the per-process `exec`, the `MATRIX ` line capture, the pairwise diff against the first probe — already generalises to any number of probes.

```php
$probes = array(
    'clarity'  => __DIR__ . '/probe_clarity.php',
    'classic'  => __DIR__ . '/probe_classic.php',
    'module'   => __DIR__ . '/probe_module.php',
    'phosphor' => __DIR__ . '/probe_phosphor.php',
);

$renders = array(
    'render:clarity'  => array(__DIR__ . '/probe_render.php', 'clarity'),
    'render:classic'  => array(__DIR__ . '/probe_render.php', 'classic'),
    'render:phosphor' => array(__DIR__ . '/probe_render.php', 'phosphor'),
);
```

Update the file's own docblock: "Three files carry that resolver" becomes four, and the list gains `themes/phosphor/brand.php`.

- [ ] **Step 9: Parameterise `probe_render.php` by design**

`probe_render.php` currently gates its rail assertions on `function_exists('brand_rail_vars')` and then asserts against `--nz-rail-*` names. phosphor also defines `brand_rail_vars()`, so without this change those assertions run against phosphor and fail on the token prefix — the design would look broken when it is the probe that is wrong.

Add, immediately after `$design` is read:

```php
/* The token prefix is a property of the DESIGN, not of the probe. The rail
 * assertions below are gated on the CAPABILITY (does this reader recolour a
 * rail at all?) rather than on a design name, so a copy of a reader under a new
 * name still runs them — but the NAMES it emits differ per design, so the
 * prefix has to travel with the design. A design absent from this map gets no
 * prefix and the rail block is skipped with a visible SKIP rather than a
 * vacuous pass. */
$prefixes = array('clarity' => '--nz', 'phosphor' => '--pz');
$P = isset($prefixes[$design]) ? $prefixes[$design] : '';
```

Then in the `if (function_exists('brand_rail_vars'))` block:

- replace the token list `array('--nz-rail-text', '--nz-rail-heading', '--nz-rail-accent')` with `array("$P-rail-text", "$P-rail-heading", "$P-rail-accent")`;
- replace the hardcoded `"clarity: $name emits $tok"` labels with `"$design: $name emits $tok"`;
- replace the white-rail regex `'/--nz-rail-text:\s*([^;]+);/'` with `'/' . preg_quote($P, '/') . '-rail-text:\s*([^;]+);/'`;
- add a guard at the top of the block: `if ($P === '') { t_ok("$design: rail token prefix is known", false, 'add it to $prefixes'); }` so an unregistered design fails loudly rather than silently skipping;
- make the shipped-band assertion design-specific, because clarity's navy and phosphor's band are different colours:

```php
    //* The shipped band still emits exactly the values in that design's
    //* tokens.css. Both designs are asserted here rather than in their probes
    //* because this is the sheet the panel actually serves, not the helper.
    $bands = array('clarity' => array('#01243D', '--nz-rail-text: rgba(255, 255, 255, 0.88);'),
                   'phosphor' => array('#0B0E12', '--pz-rail-text: rgba(255, 255, 255, 0.88);'));
    if (isset($bands[$design])) {
        $shipped = render($path, array('rail_hex' => $bands[$design][0]));
        t_ok("$design: the shipped band still emits its own text ink",
            strpos($shipped, $bands[$design][1]) !== false);
    }
```

- gate the two `filter:` assertions on `function_exists('brand_login_light_filter')` (clarity only) and add phosphor's equivalents:

```php
    if (function_exists('brand_mark_halo_filter')) {
        $both = render($path, array('logo_on_dark' => $png2), $png);
        t_ok("$design: two stored variants let the mark keep its colours",
            strpos($both, 'filter: none;') !== false);
        $one = render($path, array(), $png);
        t_ok("$design: one stored variant keeps the rescue halo",
            strpos($one, 'filter: var(--pz-mark-halo);') !== false
                && strpos($one, 'filter: none;') === false);
    }
```

Finally, change the `if (!function_exists('brand_rail_vars'))` classic block's guard to `if ($design === 'classic')`. It asserts one-surface-per-request behaviour that is specific to classic's `?scene=` design, not to "any reader without a rail", and leaving it capability-gated would make it silently vacuous for phosphor.

- [ ] **Step 10: Run the whole suite**

Run: `php tests/brand/run.php`
Expected: `== phosphor ==` and `== render:phosphor ==` sections all `ok`; the parity block reports `ok phosphor decides identically to clarity on all 168 inputs` alongside classic and module; final line `brand suite passed`; exit 0.

- [ ] **Step 11: Run the CI contract greps locally**

```bash
for reader in themes/*/brand.php; do
  for key in accent_hex rail_hex login_bg logo_url logo_url_on_dark logo_on_dark \
             logo_variant_nav logo_variant_login show_version show_design_picker company_name; do
    grep -q "$key" "$reader" || echo "MISSING $key in $reader"
  done
done
for design in themes/*/; do
  [ -f "${design}favicon.php" ] || echo "NO favicon.php in $design"
  for key in favicon favicon_url; do
    grep -q "\['$key'\]" "${design}favicon.php" || echo "MISSING $key in ${design}favicon.php"
  done
done
grep -c rail_hex_light themes/phosphor/brand.php
find themes tests -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f"; done
```
Expected: no `MISSING` and no `NO favicon.php` lines; the `rail_hex_light` count is ≥ 1; every `php -l` prints `No syntax errors detected`.

- [ ] **Step 12: Commit**

```bash
git add themes/phosphor/brand.php themes/phosphor/title.php themes/phosphor/favicon.php \
        themes/phosphor/assets/stylesheets/phosphor/tokens.css \
        tests/brand/probe_phosphor.php tests/brand/run.php tests/brand/probe_render.php
git commit -m "feat(phosphor): token layer and the three pre-auth brand endpoints

Adds themes/phosphor/ with tokens.css, brand.php, title.php and favicon.php.
The reader derives every rail ink from rail_hex by measured contrast, reusing
clarity's brand_rail_vars algorithm under --pz-* names, and reads rail_hex_light
as a documented no-op. tests/brand gains probe_phosphor.php; the resolver parity
diff is now four-way and probe_render.php is parameterised by design."
```

---

### Task 2: Self-hosted OFL fonts

**Files:**
- Create: `themes/phosphor/assets/fonts/space-grotesk/space-grotesk.css`
- Create: `themes/phosphor/assets/fonts/space-grotesk/{space-400,space-500,space-700}.woff2`
- Create: `themes/phosphor/assets/fonts/space-grotesk/LICENSE.txt`
- Create: `themes/phosphor/assets/fonts/jetbrains-mono/jetbrains-mono.css`
- Create: `themes/phosphor/assets/fonts/jetbrains-mono/{jetbrainsmono-400,jetbrainsmono-400-italic,jetbrainsmono-500}.woff2`
- Create: `themes/phosphor/assets/fonts/jetbrains-mono/LICENSE.txt`
- Test: `.github/scripts/no_external_requests.sh`
- Modify: `.github/workflows/ci.yml` (one new step)

**Interfaces:**
- Consumes: `--pz-sans` and `--pz-mono` from Task 1's `tokens.css`, whose first family names must match these `font-family` strings exactly (`'Space Grotesk'`, `'JetBrains Mono'`).
- Produces: two stylesheet paths that Task 8's shell templates link, in this order, before `tokens.css`:
  `themes/<design>/assets/fonts/space-grotesk/space-grotesk.css`
  `themes/<design>/assets/fonts/jetbrains-mono/jetbrains-mono.css`

- [ ] **Step 1: Write the failing check**

Create `.github/scripts/no_external_requests.sh`. It is the standing guarantee behind the README's "No external font, script or CDN request is added" claim, and it walks every design so clarity is covered by the same rule.

```bash
#!/usr/bin/env bash
# Every asset a design serves must come from the panel. A CDN reference in a
# stylesheet or a template is a request the operator did not consent to, from a
# host that can see who is logging into their panel and when — and on the login
# page it is made pre-authentication. Deny the whole class rather than the
# hosts, because the next one added will be a host nobody thought to list.
#
# Scope: the designs' own CSS, JS and templates. Vendor files under
# themes/default/ belong to ISPConfig and are not ours to police.
set -euo pipefail

fail=0
files=$(find themes -type f \( -name '*.css' -o -name '*.js' -o -name '*.htm' \) \
        -not -path 'themes/default/*')

for f in $files; do
  # data: URIs are inlined bytes, not requests, and every icon in icons.css is
  # one — so the pattern must be anchored on a SCHEME-AND-HOST, not on '//'.
  if grep -nEi "(url\(['\"]?|src=['\"]|href=['\"])(https?:)?//" "$f" \
     | grep -vEi "://(localhost|127\.0\.0\.1)" ; then
    echo "::error file=$f::references an off-panel host"
    fail=1
  fi
  if grep -nEi "@import[[:space:]]+(url\()?['\"]?(https?:)?//" "$f"; then
    echo "::error file=$f::@import from an off-panel host"
    fail=1
  fi
done

# A font family named in a stylesheet must actually be shipped beside it.
for css in themes/*/assets/fonts/*/*.css; do
  [ -e "$css" ] || continue
  dir=$(dirname "$css")
  while read -r woff; do
    [ -f "$dir/$woff" ] || { echo "::error file=$css::declares $woff, which is not in $dir"; fail=1; }
  done < <(grep -oE "url\((['\"]?)[^)'\"]+\.woff2\1\)" "$css" | sed -E "s/url\(['\"]?//; s/['\"]?\)//")
  [ -f "$dir/LICENSE.txt" ] || { echo "::error file=$dir::a self-hosted font ships without its licence"; fail=1; }
done

[ "$fail" -eq 0 ] || exit 1
echo "no external asset requests; every declared font file and licence is present"
```

- [ ] **Step 2: Run it and watch it fail**

Run: `bash .github/scripts/no_external_requests.sh`
Expected: FAIL — no `themes/phosphor/assets/fonts/*/` directory exists yet, so the second loop finds nothing and the script exits 0. **That is a vacuous pass, which is itself the defect**: make the script fail first by adding `themes/phosphor/assets/fonts/space-grotesk/space-grotesk.css` (Step 3) *before* the binaries, run the script, and confirm it reports the missing `.woff2` and the missing `LICENSE.txt`. Only then add the binaries.

- [ ] **Step 3: Write the two font stylesheets**

`themes/phosphor/assets/fonts/space-grotesk/space-grotesk.css`:

```css
/* ============================================================
 * Space Grotesk — self-hosted (SIL Open Font License 1.1)
 * Everything a PERSON wrote is set in this face. Everything a
 * MACHINE set is JetBrains Mono; see the sibling directory.
 *
 * Provenance: the Google Fonts latin subset, downloaded once
 * and served from the panel — the same handling as clarity's
 * Inter. No CDN, no external request, on any page, ever; the
 * login screen renders pre-authentication and a font fetched
 * from a third party would tell that party who is signing in.
 *
 * font-display: swap, matching clarity's inter.css. `block`
 * hides text for up to three seconds and the fallback stack in
 * tokens.css is deliberately close in metrics, so a brief FOUT
 * is the cheaper of the two failures.
 *
 * No italic face is shipped, and that is a design decision, not
 * an omission: Space Grotesk has no italic, and a synthesised
 * oblique is the tell. `font-synthesis: none` in base.css makes
 * that explicit, and emphasis is set in mono italic instead.
 *
 * URLs are relative to this file.
 * ============================================================ */

@font-face {
  font-family: 'Space Grotesk';
  font-style: normal;
  font-weight: 400;
  font-display: swap;
  src: url('space-400.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
@font-face {
  font-family: 'Space Grotesk';
  font-style: normal;
  font-weight: 500;
  font-display: swap;
  src: url('space-500.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
@font-face {
  font-family: 'Space Grotesk';
  font-style: normal;
  font-weight: 700;
  font-display: swap;
  src: url('space-700.woff2') format('woff2');
  unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
}
```

`themes/phosphor/assets/fonts/jetbrains-mono/jetbrains-mono.css` — the same shape, family `'JetBrains Mono'`, three faces: 400 normal (`jetbrainsmono-400.woff2`), 500 normal (`jetbrainsmono-500.woff2`), and **400 italic** (`jetbrainsmono-400-italic.woff2`, `font-style: italic`). Its header records that the true italic is shipped precisely because it carries the emphasis role the grotesk cannot.

- [ ] **Step 4: Place the binaries and the licences**

The six files already in the repository under `mockup/phosphor/fonts/` are the Google Fonts latin-subset woff2 binaries for exactly these faces. Promote them rather than re-downloading, so the shipping theme and the approved mockup are provably the same artwork:

```bash
mkdir -p themes/phosphor/assets/fonts/space-grotesk themes/phosphor/assets/fonts/jetbrains-mono
cp mockup/phosphor/fonts/space-400.woff2 mockup/phosphor/fonts/space-500.woff2 \
   mockup/phosphor/fonts/space-700.woff2 themes/phosphor/assets/fonts/space-grotesk/
cp mockup/phosphor/fonts/jetbrainsmono-400.woff2 mockup/phosphor/fonts/jetbrainsmono-400-italic.woff2 \
   mockup/phosphor/fonts/jetbrainsmono-500.woff2 themes/phosphor/assets/fonts/jetbrains-mono/
sha256sum themes/phosphor/assets/fonts/*/*.woff2 mockup/phosphor/fonts/*.woff2
```
Expected: each theme file's digest matches its mockup twin.

Write the two `LICENSE.txt` files. Both are the SIL Open Font License 1.1 in full — take the licence body verbatim from `themes/clarity/assets/fonts/inter/LICENSE.txt` (it is the same licence, and copying the copy avoids a transcription error), and replace only the first line's copyright holder:

- Space Grotesk: `Copyright (c) 2018 Florian Karsten (https://github.com/floriankarsten/space-grotesk)`
- JetBrains Mono: `Copyright (c) 2020 JetBrains s.r.o. (https://github.com/JetBrains/JetBrainsMono)`

Record the provenance and the refresh route in a `PROVENANCE.txt` beside each, so a future subset is reproducible without network archaeology:

```
Source: Google Fonts latin subset, woff2, retrieved for the phosphor mockup.
Licence: SIL OFL 1.1 (LICENSE.txt beside this file).
To re-subset from the upstream OTF/TTF instead of using Google's subset:
    pip install fonttools brotli
    pyftsubset <upstream>.ttf --flavor=woff2 --layout-features='*' \
      --unicodes='U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD' \
      --output-file=<name>.woff2
The unicode-range in the stylesheet beside this file MUST match the --unicodes
argument: a range that promises glyphs the file does not carry makes the browser
skip the fallback and render nothing for those codepoints.
```

- [ ] **Step 5: Run the check and confirm it passes**

Run: `bash .github/scripts/no_external_requests.sh`
Expected: `no external asset requests; every declared font file and licence is present`, exit 0. Then confirm it still catches a real fault:

```bash
mv themes/phosphor/assets/fonts/space-grotesk/space-500.woff2 /tmp/
bash .github/scripts/no_external_requests.sh; echo "exit=$?"
mv /tmp/space-500.woff2 themes/phosphor/assets/fonts/space-grotesk/
```
Expected: the tampered run prints `declares space-500.woff2, which is not in …` and `exit=1`.

- [ ] **Step 6: Add the CI step**

In `.github/workflows/ci.yml`, immediately after the **Cache-buster consistency** step:

```yaml
      - name: No external asset requests, and every font ships its licence
        # The login screen renders pre-authentication, so a font or script
        # pulled from a CDN would tell a third party who is signing in to the
        # panel and when — before they have signed in. The whole class is
        # denied rather than a list of hosts, because the next one added will
        # be a host nobody thought to list. The same step checks that a font
        # a stylesheet declares is actually shipped beside it and that its
        # licence travels with it, which is the other half of self-hosting.
        run: bash .github/scripts/no_external_requests.sh
```

- [ ] **Step 7: Commit**

```bash
chmod +x .github/scripts/no_external_requests.sh
git add themes/phosphor/assets/fonts .github/scripts/no_external_requests.sh .github/workflows/ci.yml
git commit -m "feat(phosphor): self-hosted Space Grotesk and JetBrains Mono

Both SIL OFL 1.1, latin subsets, served from the panel with their licences and
provenance. Adds a CI step that denies off-panel asset references across every
design and proves each declared font file and licence is present."
```

---

### Task 3: `base.css` and `icons.css`, with their parity checks

**Files:**
- Create: `themes/phosphor/assets/stylesheets/phosphor/base.css`
- Create: `themes/phosphor/assets/stylesheets/phosphor/icons.css`
- Test: `.github/scripts/design_asset_parity.sh`
- Modify: `.github/workflows/ci.yml` (one new step)

**Interfaces:**
- Consumes: the four `--nz-*` alias tokens declared in Task 1's `tokens.css` (`--nz-success-text`, `--nz-success-tint`, `--nz-danger-text`, `--nz-danger-tint`). Without them `base.css`'s six `var(--nz-…, fallback)` references fall through to hardcoded `green` / `#dfd` / `red` / `#fdd`, which is a light-mode palette on a black ground.
- Produces: two stylesheet paths Task 8's shells link, and the `.pz` / `body.pz-login` body-class scope that Tasks 4–6 and Task 8 must use consistently.

- [ ] **Step 1: Write the failing parity check**

Create `.github/scripts/design_asset_parity.sh`:

```bash
#!/usr/bin/env bash
# base.css and icons.css are PORTS, not original work, and the spec says so:
# base.css is a functional port of stock ispconfig.css with no skin in it, and
# icons.css is the icon artwork with its mask plumbing. A design that quietly
# diverges from the copy it was made from is carrying a fork nobody declared,
# and the divergence surfaces as a layout bug on one design only — which is the
# hardest kind to attribute. Assert the relationship instead.
#
#   base.css  — BYTE-IDENTICAL. It contains no design-scoped selector at all
#               (verified: zero '.nz' matches), so there is nothing to rename.
#               Its six var(--nz-…) references are satisfied by alias tokens in
#               each design's tokens.css, which is what lets it stay identical.
#   icons.css — identical after the SELECTOR SCOPE is renamed, plus a leading
#               provenance header. The artwork and the mask plumbing must not
#               differ by a byte; the scope must, or the file does not apply.
set -euo pipefail

REF=themes/clarity/assets/stylesheets/clarity
fail=0

if ! cmp -s "$REF/base.css" themes/phosphor/assets/stylesheets/phosphor/base.css; then
  echo "::error file=themes/phosphor/assets/stylesheets/phosphor/base.css::diverges from $REF/base.css"
  diff -u "$REF/base.css" themes/phosphor/assets/stylesheets/phosphor/base.css | head -40
  fail=1
fi

# Strip phosphor's provenance header (everything up to and including the line
# that closes it), undo the three scope substitutions, and diff.
tmp=$(mktemp)
sed -e '1,/^ \* ---* \*\/$/d' themes/phosphor/assets/stylesheets/phosphor/icons.css \
  | sed -e 's/body\.pz-login/body.nz-login/g' -e 's/body\.pz /body.nz /g' -e 's/\.pz /.nz /g' > "$tmp"
if ! cmp -s "$REF/icons.css" "$tmp"; then
  echo "::error file=themes/phosphor/assets/stylesheets/phosphor/icons.css::differs from $REF/icons.css by more than its selector scope"
  diff -u "$REF/icons.css" "$tmp" | head -40
  fail=1
fi
rm -f "$tmp"

# The alias tokens base.css depends on must exist in every design that ships a
# copy of it, or the fallbacks in that file paint a light palette.
for tok in --nz-success-text --nz-success-tint --nz-danger-text --nz-danger-tint; do
  grep -q -- "$tok:" themes/phosphor/assets/stylesheets/phosphor/tokens.css || {
    echo "::error::themes/phosphor tokens.css does not alias $tok, which base.css reads"
    fail=1
  }
done

[ "$fail" -eq 0 ] || exit 1
echo "base.css is byte-identical; icons.css differs only by its selector scope"
```

- [ ] **Step 2: Run it and watch it fail**

Run: `bash .github/scripts/design_asset_parity.sh`
Expected: FAIL — `cmp` reports the phosphor files do not exist.

- [ ] **Step 3: Copy `base.css` verbatim**

```bash
cp themes/clarity/assets/stylesheets/clarity/base.css \
   themes/phosphor/assets/stylesheets/phosphor/base.css
cmp themes/clarity/assets/stylesheets/clarity/base.css \
    themes/phosphor/assets/stylesheets/phosphor/base.css && echo identical
```
Do **not** edit it, not even its header comment — the header names clarity, and that is correct: it records where the port came from. The byte-identity check is the whole point.

- [ ] **Step 4: Create `icons.css`**

```bash
{
  cat <<'HDR'
/* ---------------------------------------------------------------------------
 * PROVENANCE — this file is themes/clarity/assets/stylesheets/clarity/icons.css
 * copied byte-for-byte except that its selector scope was renamed from `.nz` /
 * `body.nz-login` to `.pz` / `body.pz-login`, which is 140 substitutions and no
 * other change. The spec's component inventory says phosphor copies icons.css
 * VERBATIM; that promise is about the artwork and the mask plumbing, and the
 * scope prefix is what makes the copy load under phosphor's body class. The MIT
 * notice below travels with the artwork and must not be detached from it.
 * .github/scripts/design_asset_parity.sh asserts exactly this relationship.
 * ------------------------------------------------------------------------- */
HDR
  sed -e 's/body\.nz-login/body.pz-login/g' -e 's/body\.nz /body.pz /g' -e 's/\.nz /.pz /g' \
      themes/clarity/assets/stylesheets/clarity/icons.css
} > themes/phosphor/assets/stylesheets/phosphor/icons.css
```

Check the substitution caught everything and nothing else:

```bash
grep -c "\.nz" themes/phosphor/assets/stylesheets/phosphor/icons.css   # expect 0
grep -c "\.pz" themes/phosphor/assets/stylesheets/phosphor/icons.css   # expect 140
grep -n "MIT" themes/phosphor/assets/stylesheets/phosphor/icons.css | head -3
```
Expected: `0`, `140`, and the MIT notice present. The icons are tinted by `currentColor`, so no colour token is involved and none should be added.

- [ ] **Step 5: Run the parity check**

Run: `bash .github/scripts/design_asset_parity.sh`
Expected: `base.css is byte-identical; icons.css differs only by its selector scope`, exit 0.

Then prove the check is not vacuous:

```bash
printf '\n.pz .icon-sites::before { opacity: .5; }\n' >> themes/phosphor/assets/stylesheets/phosphor/icons.css
bash .github/scripts/design_asset_parity.sh; echo "exit=$?"
git checkout -- themes/phosphor/assets/stylesheets/phosphor/icons.css 2>/dev/null \
  || sed -i '$ d' themes/phosphor/assets/stylesheets/phosphor/icons.css
```
Expected: the tampered run reports the divergence and `exit=1`.

- [ ] **Step 6: Add the CI step**

In `.github/workflows/ci.yml`, immediately after the new external-requests step:

```yaml
      - name: Ported stylesheets have not drifted from their source
        # base.css and icons.css are ports shared between designs. A silent
        # divergence shows up as a layout or icon bug on ONE design, which is
        # the hardest kind to attribute — so the relationship is asserted here
        # rather than trusted. base.css must be byte-identical; icons.css may
        # differ only by its selector scope and its provenance header.
        run: bash .github/scripts/design_asset_parity.sh
```

- [ ] **Step 7: Commit**

```bash
chmod +x .github/scripts/design_asset_parity.sh
git add themes/phosphor/assets/stylesheets/phosphor/base.css \
        themes/phosphor/assets/stylesheets/phosphor/icons.css \
        .github/scripts/design_asset_parity.sh .github/workflows/ci.yml
git commit -m "feat(phosphor): ported base.css and icons.css, with a drift check

base.css is byte-identical to clarity's; its six var(--nz-*) references are
satisfied by alias tokens in phosphor's tokens.css so the copy need not be
edited. icons.css is the same file with its selector scope renamed. A new CI
step asserts both relationships."
```

---

### Task 4: `app.css` (the frame) and the rail-ink rule scanner

**Files:**
- Create: `themes/phosphor/assets/stylesheets/phosphor/app.css`
- Create: `.github/scripts/rail_scan.php`
- Modify: `themes/phosphor/assets/stylesheets/phosphor/tokens.css` (add `--pz-backdrop`)
- Modify: `.github/workflows/ci.yml` (one new step)

**Interfaces:**
- Consumes: every `--pz-*` token from Task 1; the `.pz` body class and `body.pz-login` scope established in Task 3.
- Produces, for Task 8's `main.tpl.htm` and Task 7's script — these class and id names are the contract between them:
  `#container`, `.pz-rail`, `.pz-brand`, `#logo`, `.pz-wordmark`, `.pz-modnav`, `.pz-modnav-item` (+ `.active`), `#topnav-container`, `#sidebar.pz-context`, `.pz-main`, `.pz-topbar`, `.menu-btn`, `.pz-topbar-brand`, `#headerbar`, `#searchform`, `#globalsearch`, `.pz-topbar-actions`, `.pz-user`, `.notification`, `#logout-button`, `#content`, `#footer`, `.pz-credit-ispconfig`, `.pz-credit-theme`, `.pz-credit-sep`, `.pz-skip`, `.pz-sr`, `.pz-wordmark-text`, `.pushy#pz-drawer`, `.site-overlay`, and `html.pz-loading` as the AJAX activity-bar hook.

- [ ] **Step 1: Write the failing scanner**

Create `.github/scripts/rail_scan.php`. It closes the stylesheet side of the "no ink literal on a rail" rule — today that rule is enforced only at the reader, and the stylesheet is where a regression is actually typed. It walks `themes/*/`, so clarity is covered by the same step.

```php
<?php
/**
 * The rail-ink scanner:  php .github/scripts/rail_scan.php
 *
 * Two rules, one class of bug.
 *
 * RULE 1 — no colour literal on a rail surface. A design's brand.php can
 * repaint the rail band and its strata to ANY colour the operator types, so an
 * ink written as a literal in a stylesheet is an ink that cannot follow it. The
 * failure mode is a navigation nobody can read on a rail the operator chose,
 * with no error anywhere; the reader-side rule has been enforced since the ink
 * family shipped, but nothing stopped the next rule someone adds to the rail
 * from reaching for #FFFFFF. Any declaration of a --*-rail-* custom property
 * outside that design's tokens.css, and any literal colour inside a rule that
 * paints itself with a rail token, fails here.
 *
 * RULE 2 — a rail ink declared in tokens.css must be a value that design's
 * brand.php re-emits for its SHIPPED band. Otherwise the panel changes
 * appearance the moment an operator sets rail_hex to the colour it already
 * was: the reader's defaults and the stylesheet's defaults would be two
 * different design decisions wearing one name.
 *
 * RULE 3 — components read tokens, never literals (CONTRIBUTING ground rule 2).
 * Applied only to the stylesheets a design AUTHORS. base.css and icons.css are
 * ports whose provenance forbids editing them, and tokens.css is where the
 * literals belong, so all three are allowlisted by name.
 *
 * Designs opt in by declaring their prefix and shipped band below. A design
 * with no entry is reported, not skipped: a silent skip is how a scanner comes
 * back falsely clean.
 */

$designs = array(
    // design   => array(token prefix, stylesheet dir, shipped band)
    'clarity'  => array('--nz', 'themes/clarity/assets/stylesheets/clarity',   '#01243D'),
    'phosphor' => array('--pz', 'themes/phosphor/assets/stylesheets/phosphor', '#0B0E12'),
);

// Ports and the token file itself. Everything else in a design's stylesheet
// directory is authored here and is held to rule 3.
$exempt_from_literals = array('tokens.css', 'base.css', 'icons.css');

$root = dirname(__DIR__, 2);
$fail = 0;

function say_err($file, $msg) {
    global $fail;
    echo "::error file=$file::$msg\n";
    $fail = 1;
}

/* Every design directory under themes/ that ships stylesheets must be known
 * here — a new design must not be able to opt out by omission. */
foreach (glob($root . '/themes/*/assets/stylesheets/*', GLOB_ONLYDIR) as $dir) {
    $rel = ltrim(str_replace($root, '', $dir), '/');
    $known = false;
    foreach ($designs as $d) { if ($d[1] === $rel) { $known = true; break; } }
    if (!$known) {
        say_err($rel, 'ships stylesheets but is not registered in rail_scan.php');
    }
}

foreach ($designs as $name => $spec) {
    list($prefix, $dir, $band) = $spec;
    $abs = $root . '/' . $dir;
    if (!is_dir($abs)) { say_err($dir, "design '$name' has no stylesheet directory"); continue; }

    $tokens_path = $abs . '/tokens.css';
    if (!is_readable($tokens_path)) { say_err($dir . '/tokens.css', 'missing'); continue; }
    // Comments are stripped everywhere in this script: tokens.css documents
    // its own tokens by name and app.css explains why a rail rule is written
    // the way it is, so a raw scan reports the explanation as the defect.
    $tokens_src = preg_replace('#/\*.*?\*/#s', '', file_get_contents($tokens_path));

    /* ---- rule 1 ---- */
    foreach (glob($abs . '/*.css') as $css_path) {
        $base = basename($css_path);
        if ($base === 'tokens.css') continue;
        $src = preg_replace('#/\*.*?\*/#s', '', file_get_contents($css_path));
        $rel = $dir . '/' . $base;

        // 1a: no --*-rail-* custom property may be DECLARED outside tokens.css.
        if (preg_match_all('/(--[a-z0-9]+-rail-[a-z0-9-]*)\s*:/i', $src, $m)) {
            foreach (array_unique($m[1]) as $tok) {
                say_err($rel, "declares the rail token $tok outside tokens.css");
            }
        }

        // 1b: inside any rule whose own body paints it with a rail token, no
        // colour may be a literal. Matching the BODY rather than the selector
        // is what makes this survive a renamed class.
        if (preg_match_all('/([^{}]+)\{([^{}]*)\}/', $src, $rules, PREG_SET_ORDER)) {
            foreach ($rules as $rule) {
                $body = $rule[2];
                if (strpos($body, 'var(' . $prefix . '-rail') === false
                    && strpos($body, 'var(' . $prefix . '-band') === false) continue;
                if (preg_match_all('/(?<![-\w#])(#[0-9A-Fa-f]{3,8}\b|rgba?\([^)]*\)|hsla?\([^)]*\)|\b(?:white|black|red|green|blue|silver|gray|grey)\b)/i', $body, $lit)) {
                    foreach (array_unique($lit[1]) as $v) {
                        say_err($rel, 'rule painted with a rail token hardcodes the colour ' . trim($v)
                            . '  [selector: ' . trim(preg_replace('/\s+/', ' ', $rule[1])) . ']');
                    }
                }
            }
        }
    }

    /* ---- rule 2 ---- */
    // Run the design's own reader in a subprocess: brand.php files across
    // designs define the same function names, so they cannot share a process.
    $probe = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/rail_emit.php')
           . ' ' . escapeshellarg($root . '/themes/' . $name . '/brand.php')
           . ' ' . escapeshellarg($band);
    $emitted_raw = shell_exec($probe);
    $emitted = decls((string)$emitted_raw);
    $shipped = decls($tokens_src);
    if (!$emitted) {
        say_err($dir, "could not read the rail tokens themes/$name/brand.php emits for $band");
        continue;
    }
    foreach ($shipped as $tok => $val) {
        if (strpos($tok, $prefix . '-rail-') !== 0) continue;
        // A var() alias is a reference, not an ink: --*-rail-accent is declared
        // as var(--*-accent) on purpose, so that repainting the accent moves
        // the marker. There is no literal for the reader to match.
        if (strpos($val, 'var(') === 0) continue;
        if (!isset($emitted[$tok])) {
            say_err($dir . '/tokens.css', "$tok is not among the tokens brand.php emits for the shipped band $band");
            continue;
        }
        if ($emitted[$tok] !== $val) {
            say_err($dir . '/tokens.css', "$tok is '$val' but brand.php emits '{$emitted[$tok]}' for the shipped band $band");
        }
    }

    /* ---- rule 3 ---- */
    foreach (glob($abs . '/*.css') as $css_path) {
        $base = basename($css_path);
        if (in_array($base, $exempt_from_literals, true)) continue;
        $src = preg_replace('#/\*.*?\*/#s', '', file_get_contents($css_path));
        // data: URIs are inlined bytes and legitimately carry %23-escaped hex.
        $src = preg_replace('/url\(([^)]*)\)/i', 'url()', $src);
        // Scan DECLARATION BODIES only, never selectors. An id selector can be
        // three hex characters by coincidence — #accordion, #dad — and a
        // whole-file scan would report a selector as a colour, which is the
        // kind of false positive that gets a scanner switched off.
        if (preg_match_all('/\{([^{}]*)\}/', $src, $bodies)) {
            foreach ($bodies[1] as $body) {
                if (!preg_match_all('/(?<![-\w#%])(#[0-9A-Fa-f]{3,8}\b|rgba?\([^)]*\)|hsla?\([^)]*\))/i', $body, $lit)) continue;
                foreach (array_unique($lit[1]) as $v) {
                    say_err($dir . '/' . $base, 'colour literal ' . trim($v) . ' — components read tokens only, add one to tokens.css');
                }
            }
        }
    }
}

if ($fail) { echo "rail scan FAILED\n"; exit(1); }
echo "rail scan passed: no rail ink literals, no unregistered design, tokens match every reader's shipped band\n";
exit(0);

/** Parse "--token: value;" pairs out of a CSS string. */
function decls($css) {
    $out = array();
    if (preg_match_all('/(--[a-z0-9-]+)\s*:\s*([^;]+);/i', (string)$css, $m, PREG_SET_ORDER)) {
        foreach ($m as $d) { $out[$d[1]] = trim($d[2]); }
    }
    return $out;
}
```

And its one-line subprocess helper, `.github/scripts/rail_emit.php` — it exists only because two `brand.php` files cannot occupy one process:

```php
<?php
/**
 * Print the --*-rail-* block one design's reader emits for a given band.
 *   php .github/scripts/rail_emit.php <path/to/brand.php> <#rrggbb>
 *
 * Loads only the reader's PURE HELPER TAIL, exactly as tests/brand/harness.php
 * does and for the same reason: the first ~200 lines of a brand.php are a live
 * pre-auth endpoint that opens a database connection. The marker is asserted so
 * a rename fails loudly instead of emitting nothing and passing the caller a
 * vacuous match.
 */
$path = isset($argv[1]) ? $argv[1] : '';
$band = isset($argv[2]) ? $argv[2] : '';
$src  = @file_get_contents($path);
if ($src === false) { fwrite(STDERR, "cannot read $path\n"); exit(2); }
$marker = 'Helpers — pure, dependency-free.';
$at = strpos($src, $marker);
if ($at === false) { fwrite(STDERR, "no helper marker in $path\n"); exit(2); }
$end = strpos($src, '*/', $at);
if ($end === false) { fwrite(STDERR, "unterminated helper banner in $path\n"); exit(2); }
eval(substr($src, $end + 2));
if (!function_exists('brand_rail_vars')) { fwrite(STDERR, "$path has no brand_rail_vars()\n"); exit(2); }
echo brand_rail_vars($band);
```

- [ ] **Step 2: Run the scanner on the current tree**

Run: `php .github/scripts/rail_scan.php`
Expected: **pass**, for clarity and for phosphor as they stand. Rules 1 and 3 iterate over whatever `.css` files exist, and phosphor currently has only `tokens.css`, `base.css` and `icons.css` — all three allowlisted or checked and clean — so there is nothing yet for them to catch.

If **clarity** fails here, stop and fix the scanner, not clarity: clarity is the known-good reference and a false positive on it is a scanner bug, not a design bug.

This task's failing test arrives in Step 3, where `app.css` is created carrying the mockup's rail glow as a literal and the scanner rejects it.

- [ ] **Step 3: Port the frame from the approved mockup, unchanged**

`mockup/phosphor/phosphor.css` lines 202–458 are section 3, `APP FRAME (app.css)`, and they are the approved artefact. Copy them verbatim into `themes/phosphor/assets/stylesheets/phosphor/app.css` under a header:

```bash
{
  cat <<'HDR'
/* ==========================================================================
   phosphor / app.css — the frame: rail, topbar, content column, drawer, footer
   ==========================================================================
   Ported from the approved mockup (mockup/phosphor/phosphor.css, section 3),
   which is the artefact this design was signed off on. Only the additions
   listed below are new, and each one is a surface the single-page mockup has
   no equivalent of: the mobile drawer, the AJAX activity bar, the wordmark
   failover, the skip link's focus state.

   Every colour here is a var(--pz-…). .github/scripts/rail_scan.php fails the
   build on a literal, and on a rail-painted rule that hardcodes its ink.
   ========================================================================== */
HDR
  sed -n '202,458p' mockup/phosphor/phosphor.css
} > themes/phosphor/assets/stylesheets/phosphor/app.css
php .github/scripts/rail_scan.php
```
Expected: **FAIL**, with
`rule painted with a rail token hardcodes the colour rgba(255,163,1,.65)  [selector: .pz-modnav-item.active::before]`.
That is the failing test for this task, and it is a real defect the mockup carries: the active marker's glow is painted on the rail, and the rail is the operator's surface.

- [ ] **Step 4: Fix the literal**

Replace that one declaration with the token Task 1 already ships:

```css
.pz-modnav-item.active::before {
  content: '';
  position: absolute;
  left: 0; top: 0; bottom: 0;
  width: 3px;
  background: var(--pz-rail-accent);
  box-shadow: var(--pz-glow-rail);
}
```

Run: `php .github/scripts/rail_scan.php`
Expected: pass.

- [ ] **Step 5: Port the design's own base layer**

The mockup's section 2 is labelled `BASE (base.css)`, and that label is misleading: the shipping `base.css` is the *functional* port of stock `ispconfig.css` (Task 3), which carries no skin at all. Everything in the mockup's section 2 — the reset, the body ground and type, the film grain, the link colour, the mono-italic emphasis rule, the focus ring, the screen-reader class and the skip link — **is** skin, and it belongs at the top of `app.css`. Without this step it lands nowhere and the design has no body background, no font and no focus indicator.

`app.css` already exists, so this block goes in front of what is there — write to a temporary file and move it back, or the frame section is lost:

```bash
{
  cat <<'HDR'
/* ---------- the design's own base layer ----------
   Reset, ground, type, focus and the two utility classes. NOT base.css:
   that file is the functional port of stock ispconfig.css and carries no
   skin, which is why it can be byte-identical across designs. Everything
   here is this design's look and belongs to this design alone.

   Film grain sits on the page ground at 0.03 and nowhere else, and the CRT
   scanline is OFF in the app frame — both are deliberate departures from a
   global treatment, because they cost legibility over an eight-hour
   table-reading day. The scanline is on at login only; see login.css. */
HDR
  sed -n '116,190p' mockup/phosphor/phosphor.css
  cat themes/phosphor/assets/stylesheets/phosphor/app.css
} > /tmp/phosphor-app.css && mv /tmp/phosphor-app.css themes/phosphor/assets/stylesheets/phosphor/app.css
head -20 themes/phosphor/assets/stylesheets/phosphor/app.css
```

Lines 116–190 run from `*, *::before, *::after { box-sizing: border-box; }` through `.pz-skip:focus { left: 8px; }`; confirm both endpoints in the output before moving on, because a `sed` range that drifts by one rule is a silent half-port.

Then the vendor floor (mockup lines 191–201). `base.css` already carries most of it from the stock port, and a duplicate here with a different value would silently override the port. Check each rule and copy only the ones `base.css` lacks:

```bash
for r in 'clear' 'right' 'text-right' 'text-center' 'marginTop15' 'fieldset-legend' 'hidden' 'noscript'; do
  printf '%-18s base.css:%s\n' "$r" "$(grep -c "$r" themes/phosphor/assets/stylesheets/phosphor/base.css)"
done
```
Anything reporting `base.css:0` is a rule the stock port does not provide and that phosphor must carry — copy that rule from the mockup. Anything non-zero is already handled; leave it out and note in a comment that it comes from `base.css`.

Run: `php .github/scripts/rail_scan.php` — expect pass, since section 2 carries no literal outside `:root` (verified while writing this plan).

- [ ] **Step 6: Add `--pz-backdrop` to `tokens.css`**

The drawer's scrim has no token yet — the mockup hides the rail on small screens and never renders a drawer, so the surface does not exist there. Add it beside the other ground tokens:

```css
  --pz-backdrop: rgba(0, 0, 0, 0.66);   /* the drawer scrim; the frame behind it is inert */
```

- [ ] **Step 7: Append the four frame surfaces the mockup has no equivalent of**

Append to `app.css`. These are ported in shape from `themes/clarity/assets/stylesheets/clarity/app.css` and re-expressed in phosphor tokens; the rail-painted ones deliberately take rail tokens and no literals.

```css
/* ---------- mobile drawer (pushy) ----------
   ISPConfig's own pushy.js clones the module nav into .pushy at DOM ready and
   toggles .pushy-open. The theme skins it and adds nothing: the drawer's
   markup, its overlay and its toggle are core's, which is what keeps this
   upgrade-safe.

   The ink here is a token and not a literal, and that is the rule for every
   rail surface rather than a note about this one — the drawer IS the rail on a
   small screen, so brand.php repaints it through exactly the same tokens. */

.menu-btn {
  display: none;
  align-items: center;
  justify-content: center;
  width: var(--pz-ctl-h);
  height: var(--pz-ctl-h);
  padding: 0;
  background: none;
  border: 1px solid var(--pz-edge);
  border-radius: var(--pz-radius-ctl);
  color: var(--pz-ink-base);
  font-size: 17px;
  cursor: pointer;
}
.menu-btn:hover { background: var(--pz-lift); color: var(--pz-ink-bright); }

.pushy {
  background: var(--pz-band);
  box-shadow: none;
  /* a closed drawer is offscreen but still focusable — remove it properly, or
     Tab walks into a menu nobody can see */
  visibility: hidden;
  transition: transform var(--pz-base) var(--pz-ease), visibility 0s linear var(--pz-base);
}
.pushy.pushy-open { visibility: visible; transition-delay: 0s, 0s; }
.pushy ul { list-style: none; margin: 0; padding: 10px; }
.pushy a {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 12px;
  border-radius: var(--pz-radius-ctl);
  color: var(--pz-rail-text);
  font-size: var(--pz-fs-secondary);
  font-weight: 500;
  text-decoration: none;
}
.pushy a:hover { background: var(--pz-rail-hover); color: var(--pz-rail-text-hover); text-decoration: none; }
.pushy a.active { background: var(--pz-rail-active); color: var(--pz-rail-text-hover); }
.pushy i.icon, .pushy .icon { width: 18px; height: 18px; flex: none; }
.pushy ul.subnavi { padding: 2px 0 4px 26px; }
.pushy ul.subnavi a { font-weight: 400; padding: 6px 10px; }
.pushy ul.subnavi a.subnav-header {
  font-family: var(--pz-mono);
  font-size: var(--pz-fs-caption);
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: var(--pz-track-label);
  color: var(--pz-rail-heading);
}
.site-overlay { background: var(--pz-backdrop); }

/* ---------- AJAX activity bar ----------
   pz-theme.js adds html.pz-loading around jQuery's ajaxStart/ajaxStop. The bar
   is amber, but it is not one of the four glows: it has no shadow, it is a 2px
   rule, and it exists for the seconds a request is in flight. */
.pz-topbar::after {
  content: '';
  position: absolute;
  left: 0; right: 0; bottom: -1px;
  height: 2px;
  background: linear-gradient(90deg, transparent, var(--pz-accent), transparent);
  background-size: 40% 100%;
  background-repeat: no-repeat;
  opacity: 0;
  pointer-events: none;
}
html.pz-loading .pz-topbar::after { opacity: 1; animation: pz-indeterminate 1.1s linear infinite; }
@keyframes pz-indeterminate {
  from { background-position: -40% 0; }
  to   { background-position: 140% 0; }
}

/* ---------- text wordmark failover ----------
   title.php replaces a brand <img> whose source failed to load with a
   <span class='pz-wordmark-text'> carrying the panel name, so a broken image
   never leaves a branded panel anonymous. It sits in the rail's brand slot and
   therefore takes the rail ink; login.css gives the login copy its own colour
   and out-specifies this. */
.pz-wordmark-text {
  font: 700 15px/1.3 var(--pz-sans);
  color: var(--pz-rail-text-hover);
  white-space: nowrap;
  letter-spacing: -0.015em;
}

/* ---------- skip link ----------
   Already present in the mockup's base section; restated here only for its
   focused state, which the mockup never renders. */
.pz-skip:focus { left: 8px; box-shadow: var(--pz-focus); }
```

- [ ] **Step 8: Add the responsive block**

Append the mockup's section 7 (`mockup/phosphor/phosphor.css` lines 1416–1445) minus the `.pz-upload` / `.pz-switchrow` rules, which belong to the Branding page and are the sibling plan's. Add `.menu-btn { display: flex; }` and `.pz-topbar-brand { display: inline-flex; }` inside the `max-width: 900px` block — the mockup declares them `display: none` at desktop and only re-enables the brand, which would leave the drawer with no toggle on a phone.

```css
@media (max-width: 1100px) {
  .pz-statgrid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 900px) {
  #container { background: none; }
  .pz-rail { display: none; }
  .menu-btn { display: inline-flex; }
  .pz-topbar-brand {
    display: inline-flex; align-items: center; gap: 8px;
    color: var(--pz-ink-bright); font-weight: 700; font-size: var(--pz-fs-body); text-decoration: none;
  }
  #searchform, .pz-user { display: none; }
  :root { --pz-content-pad: 16px; }
  .pz-statgrid { grid-template-columns: minmax(0, 1fr); }
}
```

- [ ] **Step 9: Add the reduced-motion block**

Append the mockup's section 8 (lines 1447–1464), with one addition: `animation` and `transition` are neutralised, but nothing may be *removed* — the v3.1.1 regression was a reduced-motion rule that left the logo at `opacity: 0` with nothing to animate it back.

```css
/* ==========================================================================
   REDUCED MOTION
   Nothing is removed here, only stilled. The regression this rule set is
   written against was a reduced-motion block that killed the animation while
   leaving opacity: 0 standing, which served every reduced-motion visitor a
   branded panel with no brand on it.
   ========================================================================== */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 1ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: 1ms !important;
  }
  html.pz-loading .pz-topbar::after { animation: none; opacity: 1; }
}
```

- [ ] **Step 10: Verify**

```bash
php .github/scripts/rail_scan.php
bash .github/scripts/design_asset_parity.sh
bash .github/scripts/no_external_requests.sh
php tests/brand/run.php | tail -3
```
Expected: `rail scan passed: …`; the parity and external-request scripts unchanged and green; `brand suite passed`.

Then prove rule 1 still bites, and that rule 2 is not vacuous:

```bash
printf '\n.pz-modnav-item.active { background: var(--pz-rail-active); color: #FFFFFF; }\n' \
  >> themes/phosphor/assets/stylesheets/phosphor/app.css
php .github/scripts/rail_scan.php; echo "exit=$?"      # expect the literal reported, exit=1
sed -i '$ d' themes/phosphor/assets/stylesheets/phosphor/app.css

sed -i 's/--pz-rail-heading:    rgba(255, 255, 255, 0.66);/--pz-rail-heading:    rgba(255, 255, 255, 0.60);/' \
  themes/phosphor/assets/stylesheets/phosphor/tokens.css
php .github/scripts/rail_scan.php; echo "exit=$?"      # expect the mismatch reported, exit=1
sed -i 's/--pz-rail-heading:    rgba(255, 255, 255, 0.60);/--pz-rail-heading:    rgba(255, 255, 255, 0.66);/' \
  themes/phosphor/assets/stylesheets/phosphor/tokens.css
php .github/scripts/rail_scan.php                      # green again
```

- [ ] **Step 11: Add the CI step**

In `.github/workflows/ci.yml`, immediately after the brand-readers step:

```yaml
      - name: Rail-ink scanner — no literal ink on a surface the operator repaints
        # The reader has enforced "no ink literal on a rail" since the ink
        # family shipped; nothing enforced it in the stylesheets, which is
        # where a regression is actually typed. This walks themes/*/, so a new
        # design cannot opt out by omission — an unregistered stylesheet
        # directory is a failure, not a skip. It also proves each design's
        # tokens.css agrees with what that design's brand.php emits for its own
        # shipped band, so the panel cannot change appearance when an operator
        # sets rail_hex to the colour it already was.
        run: php .github/scripts/rail_scan.php
```

- [ ] **Step 12: Commit**

```bash
git add themes/phosphor/assets/stylesheets/phosphor/app.css \
        themes/phosphor/assets/stylesheets/phosphor/tokens.css \
        .github/scripts/rail_scan.php .github/scripts/rail_emit.php .github/workflows/ci.yml
git commit -m "feat(phosphor): the app frame, and a rail-ink scanner for every design

app.css is the approved mockup's frame section plus the four surfaces a
single-page mockup has none of: the pushy drawer, the AJAX activity bar, the
wordmark failover and the skip link's focus state. The new scanner rejects a
colour literal on any rail-painted rule, any --*-rail-* declaration outside
tokens.css, any colour literal in an authored component sheet, and any
disagreement between a design's tokens.css and what its brand.php emits for its
shipped band. It caught one real defect in the mockup: the active marker's glow
was a literal painted on the rail."
```

---

### Task 5: `components.css`

**Files:**
- Create: `themes/phosphor/assets/stylesheets/phosphor/components.css`
- Modify: `themes/phosphor/assets/stylesheets/phosphor/tokens.css` (no new tokens expected; add one only if the scanner demands it, and say why in a comment)

**Interfaces:**
- Consumes: every `--pz-*` token; the `.pz` scope.
- Produces, for Tasks 7 and 9 — the dashboard and dashlet markup depends on these exact names: `.pz-dash-head`, `.pz-dash-head-row`, `.pz-dash-date`, `.pz-dash-chips`, `.pz-chip`, `.pz-chip-k`, `.pz-launcher`, `.pz-launcher-title`, `.pz-launcher-label`, `.pz-statwrap`, `.pz-statwrap-title`, `.pz-statgrid`, `.pz-statcard`, `.pz-statcard-label`, `.pz-statcard-value`, `.pz-statcard-chart`, `.pz-pane`, `.pz-donate`, `.pz-donate-cta`, `.pz-donate-dismiss`.

- [ ] **Step 1: Run the scanner to establish the pre-state**

Run: `php .github/scripts/rail_scan.php`
Expected: pass. Everything added in this task must keep it passing; that is the standing test for the whole task, and every step below ends by re-running it.

- [ ] **Step 2: Port the mockup's component section**

`mockup/phosphor/phosphor.css` lines 459–1071 are section 4, `COMPONENTS`, subsections 4a–4j: the pane, tables, instrument tiles, the module launcher, buttons, forms, the limits accordion, tabs, alerts and the form action bar. Copy them verbatim under a header:

```bash
{
  cat <<'HDR'
/* ==========================================================================
   phosphor / components.css — everything inside the content pane
   ==========================================================================
   Ported from the approved mockup (mockup/phosphor/phosphor.css, section 4).
   The three distinct treatments the spec calls for are 4b (tables), 4c
   (instrument tiles) and 4f (forms): one rounded card for everything is what
   this design is defined against.

   Glass never stacks on glass — one blurred depth per screen region. Where a
   pane nests inside a pane, the inner one takes --pz-pane-solid.

   Every colour is a var(--pz-…); .github/scripts/rail_scan.php enforces it.
   ========================================================================== */
HDR
  sed -n '459,1071p' mockup/phosphor/phosphor.css
} > themes/phosphor/assets/stylesheets/phosphor/components.css
php .github/scripts/rail_scan.php
```
Expected: pass — the mockup's component section carries no literal (verified while writing this plan: the only literals below `:root` were the rail glow, fixed in Task 4, the login mask and scanline, tokenised in Task 1, and three Branding-page rules that are the sibling plan's).

- [ ] **Step 3: Add the eleven surfaces the mockup's five pages never render**

The mockup covers login, dashboard, a list page, a tabbed edit form and the Branding page. The panel has more than that, and every one of these is stock markup phosphor must style or it falls back to unstyled Bootstrap 3 on a black ground. Port each in shape from the matching section of `themes/clarity/assets/stylesheets/clarity/components.css` (the section names are listed so they can be found by grep) and re-express in phosphor tokens:

| Append | Ported from clarity's section | phosphor's voice |
|---|---|---|
| badges / labels | `/* ---------- badges / labels ---------- */` | pill radius, mono caption, status tint + `--pz-accent-strong`-weight border, never a solid fill |
| pagination | `/* ---------- pagination ---------- */` | ghost buttons at `--pz-ctl-h-sm`, current page = `--pz-accent-subtle` fill + `--pz-accent` ink, mono numerals |
| progress meters | `/* ---------- progress meters ---------- */` | `--pz-raised` track, `--pz-accent` bar, no glow — a meter is data, and glow is budgeted |
| modal (`#datalogModal` and any Bootstrap modal) | `/* ---------- modal ---------- */` | `--pz-radius-modal`, `--pz-blur-strong` over `--pz-glass`, `--pz-shadow-lift`, `--pz-backdrop` scrim |
| select2 v3.5 | `/* ---------- select2 (v3.5) ---------- */` | matches the form well exactly: `--pz-raised`, `--pz-edge-input`, `--pz-focus` on the container, `--pz-accent-subtle` on the highlighted result |
| datetimepicker | `/* ---------- datetimepicker ---------- */` | popover on `--pz-pane-solid` (it is a pane inside a pane — no second blur), `--pz-shadow-lift`, today = `--pz-accent-subtle`, selected = `--pz-accent` fill + `--pz-on-accent` |
| tooltips (tipsy) | `/* ---------- tooltips ---------- */` | `--pz-pane-solid`, `--pz-edge`, `--pz-fs-caption` |
| monitor status blocks | `/* ---------- monitor status blocks ---------- */` | `<pre>` in mono at `--pz-fs-code`, `--pz-raised` well, `overflow-x: auto` |
| sort glyphs + the "Show all (n)" cap | `/* ---------- misc content furniture ---------- */` | mono, `--pz-ink-muted`, `--pz-accent` on the active sort |
| client/template editors: `pnl_formsarea` | `/* ---------- client / template editors ---------- */` | the accordion from 4g, no extra depth |
| donation dashlet | `/* ---------- donation dashlet ---------- */` | see the specificity rule below |

**The donate rules carry a hard constraint.** `base.css` and the mockup both set `a { color: var(--pz-accent); }`, and any rule colouring a donate anchor must out-specify that or it loses regardless of source order — which is how clarity's donate button first shipped rendering link-coloured on its own fill at 1.22:1. Write every donate anchor rule with the body-class prefix:

```css
/* ---------- donation dashlet (the override of dashlets/donate.htm) ----------
   Anchor rules here MUST be prefixed with body.pz. `a { color: … }` is (0,0,1)
   and `body.pz a` is (0,1,2); a bare `.pz-donate-cta` is (0,1,0) and loses to
   the second on specificity from any source order. That is not hypothetical —
   it is how the equivalent button first shipped on the other design, painted
   link-coloured on its own fill at 1.22:1. */
body.pz .pz-donate-cta {
  display: inline-flex;
  align-items: center;
  height: var(--pz-ctl-h);
  padding: 0 16px;
  background: var(--pz-accent);
  border: 1px solid var(--pz-accent);
  border-radius: var(--pz-radius-ctl);
  color: var(--pz-on-accent);
  font-weight: 700;
  font-size: var(--pz-fs-secondary);
  text-decoration: none;
  transition: box-shadow var(--pz-base) var(--pz-ease);
}
/* GLOW 2 of 4 — a primary button on hover or focus. */
body.pz .pz-donate-cta:hover,
body.pz .pz-donate-cta:focus-visible { box-shadow: var(--pz-glow-soft); text-decoration: none; }
body.pz .pz-donate-dismiss {
  color: var(--pz-ink-muted);
  font-size: var(--pz-fs-caption);
  text-decoration: underline;
  text-underline-offset: 2px;
}
body.pz .pz-donate-dismiss:hover { color: var(--pz-ink-base); }
```

After each block: `php .github/scripts/rail_scan.php` — expect pass. A literal you reach for is a token you have not added yet.

- [ ] **Step 4: Assert the glow budget in the file itself**

Grep the two authored stylesheets for the glow tokens and confirm the count is exactly four use sites across the design, one per named place:

```bash
grep -rn "var(--pz-glow-" themes/phosphor/assets/stylesheets/phosphor/*.css
```
Expected, and no more: `--pz-glow-rail` once in `app.css` (`.pz-modnav-item.active::before`), `--pz-glow-soft` on the primary-button hover/focus rules in `components.css`, `--pz-glow-text` twice in `login.css` after Task 6 (the brand name and the cursor — one place, two elements), `--pz-glow-ring` unused so far. `--pz-focus` is not a glow: it is the focus indicator and appears wherever focus does.

If `--pz-glow-ring` is still unused at the end of Task 6, delete it from `tokens.css` rather than leaving a token nothing reads — an unused token is a design decision nobody made.

- [ ] **Step 5: Add the donate-anchor specificity assertion to the probe**

Append to `tests/brand/probe_phosphor.php`, before the resolver section. This is the same class of guard `probe_clarity.php` carries, for the same defect, and it belongs with the tests rather than in a comment:

```php
/* ---- the donate anchors out-specify the link colour --------------------- */
$comp = (string)@file_get_contents(__DIR__ . '/../../themes/phosphor/assets/stylesheets/phosphor/components.css');
$comp = preg_replace('#/\*.*?\*/#s', '', $comp);   // the comment above the block names the classes
t_ok('components.css is readable and carries the donate block', strpos($comp, '.pz-donate') !== false);
$weak = array();
if (preg_match_all('/([^{}]*\.pz-donate-(?:cta|dismiss)[^{}]*)\{([^{}]*)\}/', $comp, $rules, PREG_SET_ORDER)) {
    foreach ($rules as $rule) {
        if (!preg_match('/(?<![-\w])color\s*:/', $rule[2])) continue;
        foreach (explode(',', $rule[1]) as $sel) {
            $sel = trim($sel);
            if ($sel === '' || strpos($sel, '.pz-donate-') === false) continue;
            if (strpos($sel, 'body.pz') !== 0) $weak[] = $sel;
        }
    }
}
t_ok('every donate anchor rule out-specifies the link colour', empty($weak), implode('; ', $weak));
```

- [ ] **Step 6: Verify**

```bash
php .github/scripts/rail_scan.php
php tests/brand/run.php | tail -3
bash .github/scripts/no_external_requests.sh
```
Expected: rail scan passes; `brand suite passed`; no external requests.

- [ ] **Step 7: Commit**

```bash
git add themes/phosphor/assets/stylesheets/phosphor/components.css \
        themes/phosphor/assets/stylesheets/phosphor/tokens.css tests/brand/probe_phosphor.php
git commit -m "feat(phosphor): components.css

The approved mockup's component section plus the eleven stock surfaces its five
pages never render — badges, pagination, meters, modals, select2, the
datetimepicker, tooltips, monitor blocks, sort furniture, the client editors and
the donation dashlet. Donate anchors are body-scoped so they out-specify the
link colour, and the probe now fails the build if one is not."
```

---

### Task 6: `login.css` — the one bold moment

**Files:**
- Create: `themes/phosphor/assets/stylesheets/phosphor/login.css`

**Interfaces:**
- Consumes: `--pz-grid-pitch`, `--pz-grid-line`, `--pz-grid-mask`, `--pz-scanline`, `--pz-glow-text`, `--pz-mark-halo`, `--pz-slow`, `--pz-blink`, `--pz-ease` from Task 1.
- Produces, for Task 8's `main_login.tpl.htm`: `body.pz-login`, `.pzl-grid`, `.pzl-scan`, `.pzl-grain`, `.pzl-scene`, `.pzl-brand`, `.pzl-cursor`, `.pzl-card`, `.pzl-hint`, `.pzl-custom`, `.pzl-footer`.

- [ ] **Step 1: Port the mockup's login section**

`mockup/phosphor/phosphor.css` lines 1072–1212 are section 5. Copy them verbatim under a header, then apply the four corrections below — each is a place where the mockup's single-file, single-page form does something the shipping theme must not.

```bash
{
  cat <<'HDR'
/* ==========================================================================
   phosphor / login.css — the login scene, and nothing else
   ==========================================================================
   Loaded by main_login.tpl.htm ONLY, alongside the font sheets, tokens.css and
   icons.css. The app frame never loads it and it never loads app.css: the two
   scenes share tokens and nothing else.

   This is the design's one bold moment. A full-viewport black field; a 34px
   terminal grid fading in behind a 384px glass card under a radial mask; the
   panel name above the card with a mono block cursor blinking after it. One
   orchestrated reveal, and nothing else on the page moves.

   The scanline and the grain are ON here and OFF in the app frame. That is a
   deliberate departure from a global treatment: they cost legibility over an
   eight-hour table-reading day, and the login screen is not that.

   No horizon beam. That is the other design's signature and it stays there.
   ========================================================================== */
HDR
  sed -n '1072,1212p' mockup/phosphor/phosphor.css
} > themes/phosphor/assets/stylesheets/phosphor/login.css
php .github/scripts/rail_scan.php
```
Expected: **FAIL**, reporting the mask's `rgba(0, 0, 0, …)` stops and the scanline's, as colour literals in an authored sheet. That is this task's failing test.

- [ ] **Step 2: Use the tokens Task 1 already ships**

```css
.pzl-grid {
  position: fixed;
  inset: 0;
  z-index: 0;
  pointer-events: none;
  background-image:
    linear-gradient(to right, var(--pz-grid-line) 1px, transparent 1px),
    linear-gradient(to bottom, var(--pz-grid-line) 1px, transparent 1px);
  background-size: var(--pz-grid-pitch) var(--pz-grid-pitch);
  -webkit-mask-image: var(--pz-grid-mask);
  mask-image: var(--pz-grid-mask);
  animation: pzl-gridin var(--pz-slow) var(--pz-ease) both;
}

.pzl-scan {
  position: fixed;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  opacity: .22;
  background: var(--pz-scanline);
}
```
Both tokens are re-emitted by `brand_login_overlays()` when the operator sets `login_bg`, which is what makes the scene follow the field instead of assuming it.

Run: `php .github/scripts/rail_scan.php` — expect pass.

- [ ] **Step 3: The three shipping-only corrections**

**3a. The brand slot is an `<img>`, not a span.** The mockup's `.pzl-brandname` span is unreachable by `brand.php`'s `content:` override and by `title.php`'s `alt`/error swap, so a branded panel would show phosphor's own name where the operator's mark belongs. Replace the `.pzl-brandname` rule with one that styles the image slot and its text failover identically:

```css
/* GLOW 1 of 4 — the login mark and its cursor, and nothing else on this page.
   The slot is an <img>: brand.php swaps its `content:` for the operator's mark
   or, with no mark and a panel name set, for the name as text; title.php sets
   its alt and replaces it with .pz-wordmark-text if the source fails. Style the
   image and the failover span the same way so all three states are one look. */
.pzl-brand img,
.pzl-brand .pz-wordmark-text {
  display: block;
  max-height: 36px;
  max-width: 100%;
  width: auto;
  height: auto;
  font: 500 26px/1.3 var(--pz-sans);
  letter-spacing: -.02em;
  color: var(--pz-ink-bright);
  text-shadow: var(--pz-glow-text);
}
```

**3b. No `filter` is set here.** clarity's `login.css` ink-darkens the login mark unconditionally, and every path through its `brand.php` then has to cancel that filter or a coloured mark is crushed. phosphor must not repeat it: the halo decision lives in `brand_mark_halo_filter()` and nowhere else. Add the note so a future edit does not reintroduce it:

```css
/* Deliberately NO `filter` on .pzl-brand img. The other design ink-darkens its
   login mark here for its light colour mode, which forces every path through
   its brand.php to cancel the filter or crush a coloured mark to near-black.
   phosphor has one mode, and the only filter its marks ever wear is the rescue
   halo brand.php chooses per stored variant. One decision, one place. */
```

**3c. The reveal is orchestrated, not per-element.** Keep the mockup's `pzl-reveal`, `pzl-gridin` and `pzl-blink` keyframes and their durations exactly (`--pz-slow`, `--pz-blink`, `steps(1, end)`), and keep the reduced-motion block that stills the cursor solid and renders the reveal at its final state. Verify the reduced-motion block ends with the cursor **visible**:

```bash
grep -A6 'prefers-reduced-motion' themes/phosphor/assets/stylesheets/phosphor/login.css
```
Expected: `.pzl-cursor { animation: none; opacity: 1; }` — `opacity: 1`, not `0`. A reduced-motion visitor must see a solid cursor, not no cursor.

- [ ] **Step 4: Add the login surfaces the mockup's one page does not render**

The login shell also serves the OTP page, the password-reset page and the forced-password-change page, all through `tmpl_dyninclude`. Port from `themes/clarity/assets/stylesheets/clarity/login.css`, sections `/* ---------- alerts on login/otp/reset ---------- */`, `/* ---------- password strength ---------- */` and `/* ---------- fine print ---------- */`, re-expressed in phosphor tokens: alerts as tinted glass (a 10% tint, a 28% border and full-value status text, never a solid panel), the strength meter on the `--pz-raised` track, the fine print at `--pz-fs-caption` in `--pz-ink-muted`.

After each: `php .github/scripts/rail_scan.php` — expect pass.

- [ ] **Step 5: Verify the glow budget is closed**

```bash
grep -c "var(--pz-glow-text)" themes/phosphor/assets/stylesheets/phosphor/login.css
grep -rn "var(--pz-glow-" themes/phosphor/assets/stylesheets/phosphor/*.css
```
Expected: `--pz-glow-text` appears exactly twice in `login.css` (the mark rule and the cursor rule), `--pz-glow-rail` once in `app.css`, `--pz-glow-soft` only on primary-button hover/focus in `components.css`. If `--pz-glow-ring` is still unreferenced anywhere, delete it from `tokens.css` now and re-run the rail scan.

- [ ] **Step 6: Commit**

```bash
git add themes/phosphor/assets/stylesheets/phosphor/login.css \
        themes/phosphor/assets/stylesheets/phosphor/tokens.css
git commit -m "feat(phosphor): login.css — the one bold moment

The approved mockup's login scene with the grid mask and the scanline moved to
tokens so brand.php can re-derive them from login_bg, the brand slot changed
from a span to the <img> the brand endpoints can actually reach, no filter set
here at all, and the OTP/reset surfaces the single-page mockup never renders."
```

---

### Task 7: `pz-theme.js`

**Files:**
- Create: `themes/phosphor/assets/javascripts/pz-theme.js`

**Interfaces:**
- Consumes: the class and id contract from Tasks 4–6; the `--pz-*` tokens, read from `getComputedStyle(document.documentElement)` so the chart palette follows a rebrand with no second copy of the palette in JS.
- Produces: `html.pz-loading` (the activity-bar hook `app.css` styles), and the Chart.js `beforeInit` plugin that Task 9's `metrics.htm` relies on for its colours. **It defines no `createChart`** — that function is core's contract and lives in the dashlet template.

- [ ] **Step 1: Write the file**

Start from `themes/clarity/assets/javascripts/nz-theme.js` and make these changes. Everything not listed — the drawer close-on-navigate and Escape handling, the `aria-expanded` MutationObserver, the Ctrl/Cmd+K and `/` search shortcuts, the jQuery `ajaxStart`/`ajaxStop` hooks, the `jQuery.fx.off` reduced-motion branch, the whole section 6 content-enhancement block with its `ICON_NAMES`, its keyboard sorting, its filter labels, its table cap and its `MutationObserver` re-application — is carried over unchanged apart from the prefix, because it is stock-markup enhancement that has nothing to do with the skin.

```bash
cp themes/clarity/assets/javascripts/nz-theme.js themes/phosphor/assets/javascripts/pz-theme.js
```

**1a. Delete section 1 entirely** — the dark/light switcher, the `KEY` constant, `mode()`, `syncToggle()` and the `.nz-theme-toggle` click delegate. phosphor is dark only, its shell renders no toggle, and a script that writes `data-*-theme` to `<html>` on a design with no light scope is a control that appears to do something and does not.

**1b. Rename the prefix throughout**: `nz-` → `pz-` in every class, id and CSS-custom-property name; `nzLine`/`nzFill` → `pzLine`/`pzFill`; `nzAreaGradient` → `pzAreaGradient`; `nz-loading` → `pz-loading`; `nz-copied` → `pz-copied`; `nz-active` → `pz-active`; `#nz-dash-chips` → `#pz-dash-chips`.

**1c. Replace the chart palette with phosphor's.** This is the one place the spec says phosphor deliberately parts company with the other design's component voice: the charts are themed **dark**, with no light "paper" island, which is why this file exists rather than a shared one.

```js
  /* ---------- Chart.js follows the tokens ----------
     Read from the computed custom properties, never retyped: a rebrand moves
     --pz-accent and the charts follow, with no second copy of the palette here
     to fall out of step.

     Themed DARK, and that is the deliberate departure. The other design floats
     its charts on a light paper island; on this ground a white plot area would
     be the brightest thing on the page and would read as the subject. Here the
     plot area is the pane it sits in, the gridlines are 6% white, the ticks are
     mono, and the only colour is the line.

     GLOW 4 of 4 is the fill under that line: an amber vertical gradient from
     34% to nothing. It is a fill and not a shadow — a glowing stroke on a chart
     reads as an alert. */
  function palette() {
    var css = getComputedStyle(document.documentElement);
    function tok(name, fallback) {
      var v = css.getPropertyValue(name);
      return (v && v.trim()) || fallback;
    }
    return {
      line: tok('--pz-accent', '#FFA301'),
      text: tok('--pz-ink-muted', '#9AA0A0'),
      grid: 'rgba(255, 255, 255, 0.06)',
      font: tok('--pz-mono', 'monospace').split(',')[0].replace(/['"]/g, '').trim()
    };
  }

  function pzAreaGradient(ctx, line) {
    var g = ctx.createLinearGradient(0, 0, 0, 64);
    g.addColorStop(0, mix(line, 0.34));
    g.addColorStop(1, mix(line, 0.0));
    return g;
  }

  /* The accent may be any CSS colour the operator's brand.php emitted, so parse
     the two forms brand.php can produce — #rrggbb and rgb()/rgba() — rather than
     assuming a hex. An unparseable value falls back to the shipped amber instead
     of producing 'NaN' stops, which Chart.js renders as no fill at all. */
  function mix(colour, alpha) {
    var m = /^#([0-9a-f]{6})$/i.exec(colour.trim());
    var r, g, b;
    if (m) {
      r = parseInt(m[1].slice(0, 2), 16);
      g = parseInt(m[1].slice(2, 4), 16);
      b = parseInt(m[1].slice(4, 6), 16);
    } else {
      m = /^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/i.exec(colour.trim());
      if (m) { r = +m[1]; g = +m[2]; b = +m[3]; }
      else { r = 255; g = 163; b = 1; }
    }
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }
```

**1d. Keep the `WeakSet` ownership scheme and the stock-colour detection verbatim.** Stock dashlet charts hardcode `rgb(75, 192, 192)` inline in the template, so global `Chart.defaults` cannot reach them; the existing code remembers which datasets the theme has taken over so a re-theme does not stamp on a chart that set its own colours deliberately. Change only the two stock constants' comment to name phosphor, and the colours applied.

**1e. Keep `themeChartConfig()` and the `beforeInit` plugin registration verbatim**, with `p.line`, `p.text`, `p.grid` and `p.font` from the new `palette()`, and `pzAreaGradient(ctx, p.line)` for the fill.

**1f. Add the drawer's focus trap, Escape and inert handling.** The spec requires it and neither clarity's script nor the mockup has it:

```js
  /* ---------- drawer focus management ----------
     The spec's accessibility floor: the drawer traps focus while open, closes
     on Escape, returns focus to its toggle, keeps aria-expanded current, and
     marks the frame behind it inert. Without the trap, Tab walks out of an open
     drawer into a page the user cannot see, which is worse than no drawer.

     `inert` is set on #container rather than on <body>, so the drawer and the
     overlay stay reachable. Browsers without inert support still get the trap
     below, which is the load-bearing half. */
  function trapFocus(container) {
    return function (e) {
      if (e.key !== 'Tab') return;
      var f = container.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
      if (!f.length) return;
      var first = f[0], last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    };
  }
```
Wire it to the existing `MutationObserver` that already watches `document.body`'s class for `pushy-active`: on open, set `#container.inert = true`, add the keydown listener and focus the drawer's first link; on close, remove the listener, clear `inert`, and return focus to `.menu-btn`.

- [ ] **Step 2: Syntax-check and prove it is optional**

```bash
node --check themes/phosphor/assets/javascripts/pz-theme.js 2>/dev/null \
  || php -r 'echo "no node; check by eye and in the mockup render\n";'
grep -c "nz-\|nzLine\|nzFill\|data-nz-theme\|theme-toggle\|localStorage" themes/phosphor/assets/javascripts/pz-theme.js
```
Expected: the check passes if `node` is present; the grep count is **0** — a surviving `nz-` name is a listener bound to a class the design does not emit, and a surviving `localStorage` is the switcher not fully removed.

The "progressive enhancement only" claim is proven in Task 12, by rendering the mockup with the script omitted and confirming every page still reads.

- [ ] **Step 3: Commit**

```bash
git add themes/phosphor/assets/javascripts/pz-theme.js
git commit -m "feat(phosphor): pz-theme.js

The other design's runtime with the dark/light switcher removed (this design has
one mode), the chart palette re-themed dark and read from the computed tokens so
a rebrand carries, and the drawer given the focus trap, Escape handling and
inert frame the spec requires. Progressive enhancement only: the panel works
with this file absent."
```

---

### Task 8: The three shell templates, the brand assets, and the CI steps that check them

**Files:**
- Create: `themes/phosphor/templates/main.tpl.htm`
- Create: `themes/phosphor/templates/topnav.tpl.htm`
- Create: `themes/phosphor/templates/main_login.tpl.htm`
- Create: `themes/phosphor/assets/images/wordmark-white.svg`
- Create: `themes/phosphor/assets/favicon/{favicon.ico,favicon-16x16.png,favicon-32x32.png,apple-touch-icon.png,android-chrome-192x192.png,android-chrome-512x512.png,mstile-150x150.png,safari-pinned-tab.svg,site.webmanifest,browserconfig.xml}`
- Create: `themes/phosphor/BUILT-AGAINST.txt` (the shell half; Task 9 appends the dashlet half)
- Modify: `.github/workflows/ci.yml` (generalise two steps from clarity to a walk of `themes/*/`)

**Interfaces:**
- Consumes: every class and id produced by Tasks 4–7; `brand.php`, `title.php` and `favicon.php` from Task 1; the two font stylesheets from Task 2.
- Produces, for Task 9 and for `install.sh`: the stock template-variable set each shell resolves, and the asset-version query string `?ver=1` which must be identical across both shells (CI's cache-buster step fails on a mismatch).

- [ ] **Step 1: Write the failing CI checks first**

Two existing steps name clarity's templates literally, so they would pass while phosphor's shells hardcoded an icon asset or shipped inconsistent `?ver=` values. Generalise both in `.github/workflows/ci.yml` **before** the templates exist, so the check is real.

Replace the **Cache-buster consistency** step's body:

```yaml
      - name: Cache-buster consistency
        # every ?ver= in a shell template must match — a mismatched bump ships
        # stale CSS/JS to browsers holding the old version (a real past bug).
        # The loop walks themes/*/templates/ rather than naming one design:
        # classic ships no committed templates and simply contributes none, and
        # a third design must not be able to opt out by not being listed.
        run: |
          set -e
          found=0
          for tpl in themes/*/templates/main.tpl.htm themes/*/templates/main_login.tpl.htm; do
            [ -f "$tpl" ] || continue
            found=$((found + 1))
            vers=$(grep -oE '\?ver=[0-9]+' "$tpl" | sort -u)
            count=$(printf '%s\n' "$vers" | grep -c . || true)
            if [ "$count" -ne 1 ]; then
              echo "::error file=$tpl::inconsistent asset versions: $(printf '%s ' $vers)"
              exit 1
            fi
            echo "$tpl: $vers"
          done
          if [ "$found" -eq 0 ]; then
            echo "::error::no committed shell templates found — the glob is wrong"
            exit 1
          fi
```

Replace the **Designs link their favicon endpoint** step's body the same way:

```yaml
      - name: Designs link their favicon endpoint
        # A committed shell that still hardcodes an icon asset is a design whose
        # endpoint exists but is never asked for — branding would apply
        # everywhere except the one surface that endpoint is for. classic's
        # shells are GENERATED at install time, so it contributes no committed
        # template here and install.sh verifies its own output instead (exactly
        # one icon link, pointing at favicon.php).
        run: |
          set -e
          fail=0
          found=0
          for tpl in themes/*/templates/main.tpl.htm themes/*/templates/main_login.tpl.htm; do
            [ -f "$tpl" ] || continue
            found=$((found + 1))
            if ! grep -q "favicon.php" "$tpl"; then
              echo "::error file=$tpl::does not link the design's favicon.php"
              fail=1
            fi
            if grep -qE "rel='(shortcut )?icon'[^>]*assets/favicon/" "$tpl"; then
              echo "::error file=$tpl::still points a tab icon at a shipped asset instead of favicon.php"
              grep -nE "rel='(shortcut )?icon'[^>]*assets/favicon/" "$tpl"
              fail=1
            fi
          done
          if [ "$found" -eq 0 ]; then
            echo "::error::no committed shell templates found — the glob is wrong"
            exit 1
          fi
          if [ "$fail" -ne 0 ]; then exit 1; fi
          echo "designs link their favicon endpoint ($found shells checked)"
```

Run both step bodies locally now.
Expected: they pass on clarity alone, and the `found` guard proves the glob matched something. This is the state phosphor's templates then have to satisfy.

- [ ] **Step 2: Write `main.tpl.htm`**

The full template. Every `tmpl_var` and every JS hook here is stock's; the departures are the frame markup, the phosphor asset layer, the three brand endpoints, and the absence of a theme toggle.

```html
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='utf-8' />
  <!-- No colour-mode bootstrap script. phosphor is dark only: there is nothing
       stored, nothing to read before first paint, and no toggle in this shell. -->

  <title><tmpl_var name="company_name"><tmpl_var name="app_title"></title>
  <!-- White-label: when a panel name is set it REPLACES the product title.
       Served by this design's own title.php, because core never sends
       company_name to the OTP page and a template-side fix cannot cover it. -->
  <script src='/themes/<tmpl_var name='current_theme'>/title.php'></script>

  <meta name='viewport' content='width=device-width, user-scalable=yes'>
  <meta name='description' lang='en' content='' />
  <meta name='keywords' lang='en' content='' />
  <meta name='robots' content='noindex, nofollow' />

  <!-- The TAB ICON is brandable: favicon.php serves the operator's own icon and
       falls back to this design's shipped one, so it is a no-op on an unbranded
       panel. No type/sizes attributes: what the endpoint returns depends on what
       the operator stored, and a browser skips a link whose declared type it
       cannot render. ONE <link>, one fetch — two icon links and the browser may
       pick either, showing the operator's mark on some tabs and ours on others.
       The remaining references stay theme assets on purpose: they are platform
       install artefacts (home-screen icon, pinned-tab mask, tile config), not
       the tab, and each has its own size and format contract. -->
  <link rel='icon' href='/themes/<tmpl_var name='current_theme'>/favicon.php'>
  <link rel='apple-touch-icon' sizes='180x180' href='/themes/<tmpl_var name='current_theme'>/assets/favicon/apple-touch-icon.png'>
  <link rel='manifest' href='/themes/<tmpl_var name='current_theme'>/assets/favicon/site.webmanifest'>
  <link rel='mask-icon' href='/themes/<tmpl_var name='current_theme'>/assets/favicon/safari-pinned-tab.svg' color='#FFA301'>
  <meta name='msapplication-TileColor' content='#0B0E12'>
  <meta name='msapplication-config' content='/themes/<tmpl_var name='current_theme'>/assets/favicon/browserconfig.xml'>
  <meta name='theme-color' content='#06080A'>

  <!-- Functional vendor CSS inherited from the stock 'default' theme. Template
       fallback does NOT extend to assets, so these paths are explicit and are
       never duplicated here.
       DELIBERATELY NOT LOADED: ispconfig.css, themes/default/theme.min.css,
       responsive.min.css, login.css — the stock skin. phosphor replaces them. -->
  <link rel='stylesheet' href='themes/default/assets/stylesheets/bootstrap.min.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/fonts.min.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/pushy.min.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/bootstrap-datetimepicker.min.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/select2.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/select2-bootstrap.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/font-awesome-4.7.0/css/font-awesome.min.css' />
  <link rel='stylesheet' href='themes/default/assets/stylesheets/bootstrap-icons.min.css' />

  <!-- phosphor layer (loaded last so it owns the cascade). Order matters:
       fonts -> tokens -> icons -> base -> app -> components. -->
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/fonts/space-grotesk/space-grotesk.css?ver=1' />
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/fonts/jetbrains-mono/jetbrains-mono.css?ver=1' />
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/tokens.css?ver=1' />
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/icons.css?ver=1' />
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/base.css?ver=1' />
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/app.css?ver=1' />
  <link rel='stylesheet' href='themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/components.css?ver=1' />

  <!-- Host branding, read from ISPConfig's sys_ini. Loaded last so it owns the
       cascade; an empty no-op when nothing is set. -->
  <link rel='stylesheet' href='/themes/<tmpl_var name='current_theme'>/brand.php'>
</head>

<body class='pz'>
  <a class='pz-skip' href='#content'>Skip to content</a>
  <!-- off-canvas drawer (mobile) — pushy.js contract: .pushy + .site-overlay +
       .menu-btn + #container must all exist at DOM-ready -->
  <nav class='pushy pushy-left' id='pz-drawer' aria-label='Mobile navigation'></nav>
  <div class='site-overlay'></div>

  <div id='container'>
    <!-- ============ brand rail ============ -->
    <aside class='pz-rail' aria-label='Sidebar'>
      <div class='pz-brand'>
        <div id='logo'><a href='#' aria-label='ISPConfig'><img src='themes/<tmpl_var name='current_theme'>/assets/images/wordmark-white.svg' alt='ISPConfig' width='96' height='26'></a></div>
      </div>
      <tmpl_if name='logged_in' value='y'><div id='topnav-container'>
      </div></tmpl_if>
      <!-- contextual panel: module tree, or news on the dashboard -->
      <tmpl_if name='logged_in' value='y'><div id='sidebar' class='pz-context'>
      </div></tmpl_if>
    </aside>

    <!-- ============ main column ============ -->
    <div class='pz-main'>
      <header class='pz-topbar'>
        <button type='button' class='menu-btn' aria-label='Menu' aria-expanded='false' aria-controls='pz-drawer'>&#9776;</button>
        <a class='pz-topbar-brand' href='#' aria-label='ISPConfig'><img src='themes/<tmpl_var name='current_theme'>/assets/images/wordmark-white.svg' alt='ISPConfig' width='67' height='18'></a>
        <div id='headerbar'>
          <tmpl_if name="cpuser">
			<tmpl_if name='usertype' op='==' value='normaluser'>
				<!-- global search -->
				<form action='#' method='get' id='searchform' role='form'>
				  <div>
					<div>
					  <div class='input-group'>
						<input id='globalsearch' type='text' class='form-control' placeholder='{tmpl_var name="globalsearch_searchfield_watermark_txt"}' />
						<span class='input-group-btn'>
              <button class='btn btn-default' title='{tmpl_var name="globalsearch_searchfield_watermark_txt"}'>
							<span class='icon icon-lens'></span>
						  </button>
						</span>
					  </div>
					</div>
				  </div>
				</form>
			</tmpl_if>
          </tmpl_if>
          <div class='pz-topbar-actions'>
            <!-- No theme toggle: this design has one mode. -->
            <button type="button" class="notification" data-toggle="modal" data-target="#datalogModal" style="display: none;" aria-live="polite">
	            <span class="pz-sr">Pending changes: </span><span class="notification_text">{tmpl_var name="datalog_changes_count"}</span>
            </button>
            <tmpl_if name="cpuser">
				<span class='pz-user'><span class='icon icon-client' aria-hidden='true'></span><tmpl_var name="cpuser"></span>
				<button type='button' id='logout-button' class='btn btn-sm btn-danger text-uppercase' data-load-content="login/logout.php"><tmpl_var name="logout_txt"> <tmpl_var name="cpuser"></button>
            </tmpl_if>
          </div>
        </div>
      </header>

		<!-- Datalogstatus Modal -->
		<div id="datalogModal" class="modal fade" role="dialog" tabindex="-1" aria-labelledby="datalogModalLabel">
		  <div class="modal-dialog">
		    <div class="modal-content">
		      <div class="modal-header">
		        <button type="button" class="close" data-dismiss="modal" aria-label="Close">&times;</button>
		        <h4 class="modal-title" id="datalogModalLabel">{tmpl_var name="datalog_changes_txt"}</h4>
		      </div>
		      <div class="modal-body">
		        <ul>
			    <tmpl_loop name="datalog_changes">
			        <li><strong>{tmpl_var name="text"}:</strong> {tmpl_var name="count"}</li>
			    </tmpl_loop>
			    </ul>
		      </div>
		      <div class="modal-footer">
		        <button type="button" class="btn btn-default" data-dismiss="modal">{tmpl_var name="datalog_changes_close_txt"}</button>
		      </div>
		    </div>
		  </div>
		</div>
		<!-- END Datalogstatus Modal -->

      <!-- content (a <main>; ispconfig.js only needs the #content id) -->
      <main id='content'>
		<noscript>This page needs JavaScript to be enabled.</noscript>
		<form method="post" action="" id="pageForm" name="pageForm" enctype="multipart/form-data" class='form-horizontal' role='form'>
			<div id="pageContent" data-startpage="{tmpl_var name="startpage"}"><!-- AJAX CONTENT --></div>
		</form>
      </main>

      <footer id='footer'>
        <span class='pz-credit-ispconfig'>powered by <a href="<tmpl_var name="app_link">" target="_blank" rel="noopener"><tmpl_var name="app_title"></a></span>
        <span class='pz-credit-theme'><span class='pz-credit-sep'> &middot; </span><a href="https://github.com/wadejbeckett/ispconfig-theme-customizer" target="_blank" rel="noopener">phosphor theme</a></span>
      </footer>
    </div>
  </div>

  <script type="text/javascript" src="js/jquery.min.js"></script>
  <script src='themes/default/assets/javascripts/bootstrap.min.js'></script>
  <script src='themes/default/assets/javascripts/bootstrap-datetimepicker.min.js'></script>
  <script src='themes/default/assets/javascripts/ispconfig.js'></script>
  <script src='themes/default/assets/javascripts/modernizr.custom.min.js'></script>
  <script src='themes/default/assets/javascripts/pushy.min.js'></script>
  <script src='themes/default/assets/javascripts/responsive.min.js'></script>
  <script src='js/select2/select2.min.js'></script>
  <script src='js/scrigo.js.php'></script>
  <script type="text/javascript" src="js/jquery.ispconfigsearch.js"></script>
  <script type="text/javascript" src="js/jquery.tipsy.js"></script>
  <script src="js/chartjs/chart.umd.js"></script>
  <script src='themes/<tmpl_var name='current_theme'>/assets/javascripts/pz-theme.js?ver=1'></script>
  <tmpl_loop name="js_d_includes">
	<script type="text/javascript" src="js/js.d/<tmpl_var name='file'>"></script>
  </tmpl_loop>
  <script>
  <!--
	ISPConfig.tabChangeDiscard = '<tmpl_var name="tabchange_discard_enabled">';
	ISPConfig.tabChangeWarning = '<tmpl_var name="tabchange_warning_enabled">';
	ISPConfig.tabChangeWarningTxt = '<tmpl_var name="global_tabchange_warning_txt">';
	ISPConfig.tabChangeDiscardTxt = '<tmpl_var name="global_tabchange_discard_txt">';

	<tmpl_if name="use_loadindicator" value="y">ISPConfig.setOption('useLoadIndicator', true);</tmpl_if>
	<tmpl_if name="use_combobox" value="y">ISPConfig.setOption('useComboBox', true);</tmpl_if>

	$(document).ready(function() {
		$('#globalsearch').ispconfigSearch({
			dataSrc: '/dashboard/ajax_get_json.php?type=globalsearch',
			resultsLimit: '$ <tmpl_var name="globalsearch_resultslimit_of_txt"> % <tmpl_var name="globalsearch_resultslimit_results_txt">',
			noResultsText: '<tmpl_var name="globalsearch_noresults_text_txt">',
			noResultsLimit: '<tmpl_var name="globalsearch_noresults_limit_txt">',
			searchFieldWatermark: '<tmpl_var name="globalsearch_searchfield_watermark_txt">',
			resultBoxPosition: ''
		});

    ISPConfig.loadInitContent();

	});
  //-->
  </script>
</body>

</html>
```

- [ ] **Step 3: Write `topnav.tpl.htm`**

```html
	<!-- phosphor module navigation (vertical rail).
	     Contract preserved from stock topnav.tpl.htm:
	       - <nav id='main-navigation'> wrapper (responsive.js reads it)
	       - one <a> per module carrying data-capp (except the active one),
	         data-icon-class, and the 'active' class — ispconfig.js delegates
	         its clicks on exactly these and the pushy mobile clone reads them. -->
	<nav id='main-navigation' class='pz-modnav' aria-label='Modules'>
    <tmpl_loop name="nav_top">
		<a href="#" class="pz-modnav-item <tmpl_if name="active">active"<tmpl_else>" data-capp="<tmpl_var name='module'>"</tmpl_if> data-icon-class="<tmpl_var name='icon'>">
		  <span class="{tmpl_var name='icon'}"></span>
		  <span class="title"><tmpl_var name="title"></span>
		</a>
    </tmpl_loop>
	</nav>
```

- [ ] **Step 4: Write `main_login.tpl.htm`**

```html
<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='utf-8' />

  <title><tmpl_var name="company_name"><tmpl_var name="app_title"></title>
  <!-- Same rule as main.tpl.htm — a set panel name replaces the product title.
       title.php covers the OTP page, which core never hands company_name. -->
  <script src='/themes/<tmpl_var name='current_theme'>/title.php'></script>

  <meta name='viewport' content='width=device-width, user-scalable=yes'>
  <meta name='description' lang='en' content='' />
  <meta name='keywords' lang='en' content='' />

 <!-- Same brandable tab icon as the app frame, and it matters most HERE: this
      is the page a customer sees before anything else, and it is
      pre-authentication — favicon.php answers with no session, exactly like
      brand.php and title.php beside it. -->
 <link rel='icon' href='/themes/<tmpl_var name='current_theme'>/favicon.php'>
 <link rel='apple-touch-icon' sizes='180x180' href='/themes/<tmpl_var name='current_theme'>/assets/favicon/apple-touch-icon.png'>
 <link rel='manifest' href='/themes/<tmpl_var name='current_theme'>/assets/favicon/site.webmanifest'>
 <link rel='mask-icon' href='/themes/<tmpl_var name='current_theme'>/assets/favicon/safari-pinned-tab.svg' color='#FFA301'>
 <meta name='msapplication-TileColor' content='#0B0E12'>
 <meta name='msapplication-config' content='/themes/<tmpl_var name='current_theme'>/assets/favicon/browserconfig.xml'>
 <meta name='theme-color' content='#06080A'>

  <!-- functional vendor CSS from the stock theme; the stock skin (ispconfig.css,
       theme.min.css, login.css) is deliberately not loaded -->
  <link rel='stylesheet' href='../themes/default/assets/stylesheets/bootstrap.min.css' />
  <link rel='stylesheet' href='../themes/default/assets/stylesheets/fonts.min.css' />
  <link rel='stylesheet' href='../themes/default/assets/stylesheets/bootstrap-datetimepicker.min.css' />
  <link rel='stylesheet' href='../themes/default/assets/stylesheets/select2.css' />
  <link rel='stylesheet' href='../themes/default/assets/stylesheets/select2-bootstrap.css' />
  <link rel='stylesheet' href='../themes/default/assets/stylesheets/font-awesome-4.7.0/css/font-awesome.min.css' />

  <!-- phosphor layer. The login scene loads tokens + icons + login only; it
       never loads app.css or components.css and they never load this. -->
  <link rel='stylesheet' href='../themes/<tmpl_var name='current_theme'>/assets/fonts/space-grotesk/space-grotesk.css?ver=1' />
  <link rel='stylesheet' href='../themes/<tmpl_var name='current_theme'>/assets/fonts/jetbrains-mono/jetbrains-mono.css?ver=1' />
  <link rel='stylesheet' href='../themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/tokens.css?ver=1' />
  <link rel='stylesheet' href='../themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/icons.css?ver=1' />
  <link rel='stylesheet' href='../themes/<tmpl_var name='current_theme'>/assets/stylesheets/phosphor/login.css?ver=1' />

  <!-- host branding. Absolute path so it resolves from /login/; an empty no-op
       when nothing is set. -->
  <link rel='stylesheet' href='/themes/<tmpl_var name='current_theme'>/brand.php'>
</head>

<body class='pz-login'>
  <div class='pzl-grid' aria-hidden='true'></div>
  <div class='pzl-scan' aria-hidden='true'></div>
  <div class='pzl-grain' aria-hidden='true'></div>

  <div class='pzl-scene'>
    <!-- The mark stands above the card with a mono block cursor after it.
         .pzl-brand img is brand.php's logo-override target and title.php's
         alt/failover target; the cursor is decorative and is hidden from
         assistive technology. -->
    <div class='pzl-brand'>
      <img src='../themes/<tmpl_var name='current_theme'>/assets/images/wordmark-white.svg' alt='ISPConfig' width='134' height='36'>
      <span class='pzl-cursor' aria-hidden='true'></span>
    </div>
    <main class='pzl-card' aria-label='Sign in'>
      <p class='pzl-hint'>Sign in to your control panel</p>
      <tmpl_dyninclude name="content_tpl">
      <div class='pzl-custom'><small><tmpl_var name="custom_login"></small></div>
    </main>
    <footer class='pzl-footer'>
      <span class='pz-credit-ispconfig'>powered by <a href="<tmpl_var name="app_link">" target="_blank" rel="noopener"><tmpl_var name="app_title"></a></span>
      <span class='pz-credit-theme'><span class='pz-credit-sep'> &middot; </span><a href="https://github.com/wadejbeckett/ispconfig-theme-customizer" target="_blank" rel="noopener">phosphor theme</a></span>
    </footer>
  </div>

  <script type="text/javascript" src="../js/jquery.min.js"></script>
  <script src='../themes/default/assets/javascripts/bootstrap.min.js'></script>
  <script src='../themes/default/assets/javascripts/bootstrap-datetimepicker.min.js'></script>
  <script src='../themes/default/assets/javascripts/ispconfig.js'></script>
  <script src='../themes/default/assets/javascripts/modernizr.custom.min.js'></script>
  <script src='../js/select2/select2.min.js'></script>
  <script src='../js/scrigo.js.php'></script>
  <tmpl_loop name="js_d_includes">
	<script type="text/javascript" src="../js/js.d/<tmpl_var name='file'>"></script>
  </tmpl_loop>
</body>

</html>
```

Note the `.pzl-brand` markup difference from the mockup: an `<img>` plus the cursor span, not a text span plus the cursor. Task 6's `.pzl-brand img, .pzl-brand .pz-wordmark-text` rule styles all three of its states — shipped mark, operator's mark, panel name as text — identically.

- [ ] **Step 5: Diff the shells against stock and record the departures**

```bash
diff .refs/ispconfig3/interface/web/themes/default/templates/main.tpl.htm       themes/phosphor/templates/main.tpl.htm       | head -60
diff .refs/ispconfig3/interface/web/themes/default/templates/main_login.tpl.htm themes/phosphor/templates/main_login.tpl.htm | head -40
diff .refs/ispconfig3/interface/web/themes/default/templates/topnav.tpl.htm     themes/phosphor/templates/topnav.tpl.htm     | head -20
```
Read each hunk and confirm every stock `tmpl_var`, `tmpl_if`, `tmpl_loop` and `tmpl_dyninclude` in the stock file still appears in phosphor's. A dropped variable is a string that silently vanishes from the panel; a dropped `tmpl_if name='logged_in'` renders the nav sinks to a logged-out visitor. Cross-check against `themes/clarity/templates/main.tpl.htm`, which resolves the same set and is the known-good reference:

```bash
for v in $(grep -oE "tmpl_var name=[\"'][a-z_]+[\"']" themes/clarity/templates/main.tpl.htm | sort -u); do
  grep -q "$v" themes/phosphor/templates/main.tpl.htm || echo "MISSING $v"
done
```
Expected: no `MISSING` lines.

- [ ] **Step 6: Create the shipped wordmark**

`themes/phosphor/assets/images/wordmark-white.svg` — neutral, no vendor name, the mark plus the product word, drawn to sit on the dark band. It is the file a white-labeller replaces if they would rather swap a file than use the Branding page.

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!-- Default neutral wordmark for the phosphor design. Plain "ISPConfig" text
     beside the square mark, so the design ships with neutral branding out of
     the box. Light artwork: every phosphor surface is dark. White-labellers
     replace this single file, or set a logo on the Branding page — a stored
     logo overrides this one on every surface.
     The colours are the shipped --pz-ink-bright and --pz-accent. An SVG is a
     separate document and cannot read the panel's custom properties, so these
     two are literals here by necessity; the rail-ink scanner scopes itself to
     CSS for exactly that reason. -->
<svg xmlns="http://www.w3.org/2000/svg" width="384" height="104" viewBox="0 0 384 104">
  <rect x="3" y="30" width="44" height="44" rx="7" fill="none" stroke="#FFA301" stroke-width="6"/>
  <rect x="15" y="45" width="20" height="7" fill="#FFA301"/>
  <text x="66" y="52" dominant-baseline="central"
        font-family="'Space Grotesk', 'Segoe UI', system-ui, sans-serif"
        font-weight="500" font-size="46" letter-spacing="-1" fill="#FFFFFF">ISPConfig</text>
</svg>
```

- [ ] **Step 7: Generate the favicon set**

One source SVG, then the raster derivatives. Both `convert` (ImageMagick) and `inkscape` are available on this machine; ImageMagick alone is enough here because the source is flat geometry.

```bash
mkdir -p themes/phosphor/assets/favicon
cat > themes/phosphor/assets/favicon/safari-pinned-tab.svg <<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<!-- Safari's pinned-tab mask: a single-colour silhouette, so the geometry is
     filled rather than stroked and no colour is declared — Safari tints it. -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <path d="M6 6h20v20H6V6zm3 3v14h14V9H9z"/>
  <rect x="12" y="14" width="8" height="4"/>
</svg>
SVG

cat > /tmp/phosphor-icon.svg <<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="512" height="512">
  <rect width="32" height="32" rx="6" fill="#06080A"/>
  <rect x="7.5" y="7.5" width="17" height="17" rx="2.5" fill="none" stroke="#FFA301" stroke-width="2.5"/>
  <rect x="12" y="13.5" width="8" height="3" fill="#FFA301"/>
</svg>
SVG

cd themes/phosphor/assets/favicon
for s in 16 32 150 180 192 512; do
  convert -background none /tmp/phosphor-icon.svg -resize ${s}x${s} png32:icon-$s.png
done
mv icon-16.png favicon-16x16.png
mv icon-32.png favicon-32x32.png
mv icon-150.png mstile-150x150.png
mv icon-180.png apple-touch-icon.png
mv icon-192.png android-chrome-192x192.png
mv icon-512.png android-chrome-512x512.png
convert favicon-16x16.png favicon-32x32.png favicon.ico
cd - >/dev/null
file themes/phosphor/assets/favicon/*
```
Expected: `favicon.ico` reports `MS Windows icon resource - 2 icons`; every PNG reports its size. The icon geometry is the same mark the mockup used as its inline tab icon, so the theme and the approved screenshots agree.

Then the two platform manifests — `site.webmanifest` and `browserconfig.xml`. Copy clarity's and change only the colours and the icon paths (the paths are relative to the manifest, so they need no change):

```bash
sed -e 's/#01243D/#0B0E12/g' -e 's/#17252B/#06080A/g' \
    themes/clarity/assets/favicon/site.webmanifest > themes/phosphor/assets/favicon/site.webmanifest
sed -e 's/#01243D/#0B0E12/g' \
    themes/clarity/assets/favicon/browserconfig.xml > themes/phosphor/assets/favicon/browserconfig.xml
grep -o '#[0-9A-Fa-f]\{6\}' themes/phosphor/assets/favicon/site.webmanifest themes/phosphor/assets/favicon/browserconfig.xml
```
Read the two files afterwards and confirm no `name` or `short_name` carries a vendor string; if clarity's does, set them to `"ISPConfig"` — this design ships neutral and the operator's name arrives through the Branding page.

- [ ] **Step 8: Write `BUILT-AGAINST.txt` (shell half)**

```
PHOSPHOR DESIGN FOR ISPCONFIG — build provenance
================================================

Built against ISPConfig 3.3.1p1 (the stock shell templates in
.refs/ispconfig3/interface/web/themes/default/templates/ at the commit this
design was written against; the same pin themes/clarity/BUILT-AGAINST.txt
records).

phosphor is DARK ONLY in v1. Its shells render no colour-mode control and its
stylesheets carry no light scope. That is a design decision, not an omission;
adding a light scope later is a remap block in tokens.css plus a toggle in
these two shells, and it changes no template contract below.

WHAT THIS DESIGN OVERRIDES (all upgrade-safe, no core modification):
  templates/main.tpl.htm        - complete app-frame rewrite (brand rail +
                                  blurred topbar + content column). Preserves
                                  every JS contract of the stock shell:
                                  #container/.pushy/.site-overlay/.menu-btn
                                  (pushy.js), #topnav-container + #sidebar
                                  (nav.php AJAX sinks), #content,
                                  form#pageForm[name] > #pageContent
                                  [data-startpage], .notification (inline
                                  display:none) + #datalogModal, the
                                  #globalsearch init block, and the full stock
                                  template-variable set.
  templates/topnav.tpl.htm      - module nav re-markup for the vertical rail.
                                  Keeps <nav id='main-navigation'> and per-item
                                  data-capp / data-icon-class / active class —
                                  the exact attributes ispconfig.js delegates
                                  and responsive.js's pushy clone reads.
  templates/main_login.tpl.htm  - the login scene (renders the stock
                                  login/templates/*.htm content unchanged via
                                  tmpl_dyninclude).
  sidenav.tpl.htm is NOT overridden - the stock tree markup is styled
                                  generically under #sidebar.

  Both shell templates link this design's THREE brand endpoints, and those
  links are part of the override contract: brand.php (CSS), title.php (JS) and
  favicon.php (the tab icon). The favicon one replaces stock's three icon
  <link>s with a single unsized, untyped link — what the endpoint returns
  depends on what the operator stored, and a browser skips a link whose
  declared type it cannot render. If a future stock shell gains further
  rel='icon' variants they must NOT be copied in: two icon links means the
  browser may pick either, and the panel would show the operator's mark on
  some tabs and this design's on others. The remaining icon references
  (apple-touch-icon, mask-icon, manifest, msapplication tile) stay pointed at
  assets/favicon/ deliberately — platform install artefacts, not the tab.

  The brand slots are <img> elements — #logo img, .pz-topbar-brand img and
  .pzl-brand img — and that is a contract, not a styling choice. brand.php
  overrides their `content:` with the operator's mark or with the panel name as
  text, and title.php sets their alt and replaces a failed one with a styled
  text wordmark. A span in any of those three places is a slot the branding
  cannot reach, and the panel would fly this design's mark on a white-labelled
  install.

VENDOR DEPENDENCIES (loaded from themes/default — theme assets have NO
fallback, so these explicit paths must exist):
  bootstrap.min.css, fonts.min.css (ispconfig icon font), pushy.min.css,
  bootstrap-datetimepicker.min.css, select2.css, select2-bootstrap.css,
  font-awesome-4.7.0/, bootstrap-icons.min.css, and all stock JS (bootstrap,
  ispconfig.js, modernizr, pushy, responsive, datetimepicker, Chart.js).
DELIBERATELY NOT LOADED (replaced by this design's CSS):
  ispconfig.css, themes/default/theme.min.css, responsive.min.css, login.css.
  Their functional subset is carried by assets/stylesheets/phosphor/base.css,
  which is a BYTE-IDENTICAL copy of themes/clarity's file — any divergence
  between the two is a bug, and .github/scripts/design_asset_parity.sh fails
  the build on one.

THE ispconfig_version FILE IS REQUIRED — and is install-specific.
ISPConfig gates themes by an EXACT match of ISPC_APP_VERSION under two
filenames (ispconfig_version gates login + user pickers; ISPC_VERSION gates
the admin default-settings picker). ../../install.sh stamps both; re-run it
after every ISPConfig upgrade, and re-diff ALL SEVEN overridden templates
against the new stock ones: the three shell templates above and the four
dashboard templates pinned below.
```

- [ ] **Step 9: Verify**

```bash
# the two generalised CI steps, run locally
for tpl in themes/*/templates/main.tpl.htm themes/*/templates/main_login.tpl.htm; do
  [ -f "$tpl" ] || continue
  vers=$(grep -oE '\?ver=[0-9]+' "$tpl" | sort -u); echo "$tpl: $vers"
  [ "$(printf '%s\n' "$vers" | grep -c .)" -eq 1 ] || echo "INCONSISTENT $tpl"
  grep -q "favicon.php" "$tpl" || echo "NO favicon.php LINK $tpl"
  grep -qE "rel='(shortcut )?icon'[^>]*assets/favicon/" "$tpl" && echo "HARDCODED ICON $tpl"
done
bash .github/scripts/no_external_requests.sh
php .github/scripts/rail_scan.php
php tests/brand/run.php | tail -3
```
Expected: each committed shell prints one `?ver=1`; no `INCONSISTENT`, `NO favicon.php LINK` or `HARDCODED ICON`; the three scripts green.

- [ ] **Step 10: Commit**

```bash
git add themes/phosphor/templates themes/phosphor/assets/images themes/phosphor/assets/favicon \
        themes/phosphor/BUILT-AGAINST.txt .github/workflows/ci.yml
git commit -m "feat(phosphor): the three shell templates, brand assets and provenance

main.tpl.htm, topnav.tpl.htm and main_login.tpl.htm, each preserving the stock
JS and template-variable contracts pinned in BUILT-AGAINST.txt. The brand slots
are <img> elements so brand.php and title.php can reach them. Ships a neutral
wordmark and a full favicon set. CI's cache-buster and favicon-link steps now
walk themes/*/ instead of naming one design."
```

---

### Task 9: The four dashboard template overrides

**Files:**
- Create: `themes/phosphor/templates/dashboard/dashboard.htm`
- Create: `themes/phosphor/templates/dashboard/modules.htm`
- Create: `themes/phosphor/templates/dashboard/metrics.htm`
- Create: `themes/phosphor/templates/dashboard/donate.htm`
- Modify: `themes/phosphor/BUILT-AGAINST.txt` (append the dashlet half)
- Modify: `.github/workflows/ci.yml` (generalise the dashlet-override step)
- Modify: `tests/brand/probe_phosphor.php` (the donate-override assertions)

**Interfaces:**
- Consumes: the component classes from Task 5 and the chart plugin from Task 7.
- Produces: `createChart(chartname, label, labels, data)` — defined inside `metrics.htm`, exactly as stock and clarity define it, because `monitor/show_sys_state.htm` ships its own with the same signature and the dashlet template is the only scope where core guarantees it.

- [ ] **Step 1: Generalise the CI step, then watch it fail**

Replace the **Dashlet override contracts are recorded** step's loop:

```yaml
      - name: Dashlet override contracts are recorded
        # every theme-side dashboard override must be pinned in that design's
        # BUILT-AGAINST.txt. The loop walks themes/*/ so a third design cannot
        # add an override without recording what it preserves — the pin is what
        # somebody re-checks after an ISPConfig upgrade, and an unrecorded
        # override is one nobody knows to re-check.
        run: |
          set -e
          for ov in themes/*/templates/dashboard/*.htm; do
            [ -f "$ov" ] || continue
            design=$(echo "$ov" | cut -d/ -f2)
            base=$(basename "$ov")
            if ! grep -q "$base" "themes/$design/BUILT-AGAINST.txt"; then
              echo "::error file=$ov::override $base is not documented in themes/$design/BUILT-AGAINST.txt"
              exit 1
            fi
          done
          echo "all dashlet overrides documented"
```

Run that body locally after creating the four templates but **before** appending to `BUILT-AGAINST.txt`.
Expected: it names each undocumented phosphor override and exits 1. That is this task's failing test.

- [ ] **Step 2: Write `dashboard.htm`**

Same contract as clarity's — `welcome_user`; the `error`/`warning`/`info` loops with their `*_msg` variables; the `leftcol`/`rightcol` content loops; and the three `message_ack.php` alert-close handlers kept verbatim, because those are core behaviour and dropping one leaves a dismissed alert that comes back on the next load. Take `themes/clarity/templates/dashboard/dashboard.htm`, rename `nz-`→`pz-` throughout (`nz-dash-head`, `nz-dash-head-row`, `nz-dash-date`, `nz-dash-chips` and the `#nz-dash-date` reference in its inline script), and change the header comment to name phosphor and this design's spec. Change nothing else: the alert markup is Bootstrap's and the close handlers are core's.

- [ ] **Step 3: Write `modules.htm`**

Take `themes/clarity/templates/dashboard/modules.htm` and rename `nz-launcher`/`nz-launcher-title`/`nz-launcher-label` to their `pz-` equivalents. The click contract — `<a data-capp='{tmpl_var name="modules_name"}'>` — and the four loop variables (`modules_icon`, `modules_title`, `modules_name`, `go_to_txt`) plus `available_modules_txt` are unchanged.

- [ ] **Step 4: Write `metrics.htm`**

The spec turns the four stacked strips into instrument tiles: a mono numeral at `--pz-fs-numeral` with tabular figures, an amber sparkline, and **the live last value** rather than decoration. clarity's override already does exactly this, so take it and change three things:

- rename `nz-`→`pz-` on `nz-statwrap`, `nz-statwrap-title`, `nz-statgrid`, `nz-statcard`, `nz-statcard-label`, `nz-statcard-value`, `nz-statcard-chart`, `#nz-stat-<canvas>`, `nz-chip`, `nz-chip-k`, `#nz-dash-chips`, and the helper `nzStatFmt`→`pzStatFmt`;
- keep the four canvas ids **exactly** `loadchart`, `memchart`, `rxchart`, `txchart` — `pz-theme.js`'s plugin and the stock dashlet JS both key on them;
- keep the chip key as the `label` argument and never a literal string. `label` is `{tmpl_var name="<n>chart_label"}`, which `dashlets/metrics.php` already resolved from `dashboard/lib/lang/<lang>_dashlet_metrics.lng` for the session language. A theme cannot add or shadow ISPConfig lang keys — the lang loader only reads a module's own `lib/lang` directories — so the strings core hands over in `tmpl_var`s are the only localised source available. Hardcoding English printed `Load` in the chip and `Systembelastung %` on the card directly beneath it on a German panel.

Leave the dataset colours **out** of this template. `pz-theme.js`'s `beforeInit` plugin applies them, which is what lets a rebrand move the chart line; a colour written here would out-rank the plugin and freeze the charts amber for ever.

- [ ] **Step 5: Write `donate.htm`**

Take `themes/clarity/templates/dashboard/donate.htm` and rename its classes to `pz-donate`, `pz-donate-cta`, `pz-donate-dismiss`. Everything the probe asserts about it must survive the rename, so re-read the list before editing:

- the five `tmpl_var`s (`donate_txt`, `more_btn_txt`, `donate2_txt`, `hide_btn_txt`, `donate_btn_txt`);
- core's exact Hide target, `data-load-content="dashboard/dashboard.php?hide=donate"` — that is what makes core write its `hide_donation_dashlet` row (`dashboard.php:37-47`), and a changed target is a dashlet nobody can dismiss;
- **no `<script>` at all** — stock's inline script binds `$("button").click()` with no scope, so every button on the dashboard toggles this dashlet. A `<details>`/`<summary>` disclosure replaces it and needs none;
- no inline `background-color`, no page-global `id=`, no `<h1>`–`<h6>` wrapper, `rel="noopener"` on the outbound link;
- **no hardcoded visible text** — a theme cannot ship lang keys, so every word the operator reads arrives through a `tmpl_var`.

- [ ] **Step 6: Carry the donate assertions into `probe_phosphor.php`**

Append the block from `tests/brand/probe_clarity.php` marked `/* ---- the donation dashlet override keeps core's contract ---- */`, with the path changed to `themes/phosphor/templates/dashboard/donate.htm`. Keep its two subtleties intact or the assertions go vacuous: strip HTML comments before matching (the file's own header quotes stock's `<h4>`, its id and its inline background while explaining why each is gone, and a whole-file scan reports the explanation as the defect), and strip tags **and** `{tmpl_var …}` before the "no hardcoded visible text" check.

- [ ] **Step 7: Append the dashlet half of `BUILT-AGAINST.txt`**

```
--- dashboard template overrides (templates/dashboard/) ---
dashboard.htm   <- interface/web/dashboard/templates/dashboard.htm (3.3.1p1)
                   vars: welcome_user; loops error|warning|info (error_msg|
                   warning_msg|info_msg); loops leftcol|rightcol (content);
                   keeps the three message_ack.php alert-close GET handlers
                   verbatim — they are core behaviour, and dropping one leaves
                   a dismissed alert that returns on the next load.
modules.htm     <- interface/web/dashboard/dashlets/templates/modules.htm (3.3.1p1)
                   vars: available_modules_txt; loop modules (modules_icon,
                   modules_title, modules_name, go_to_txt).
                   Click contract: <a data-capp='<name>'>.
metrics.htm     <- interface/web/dashboard/dashlets/templates/metrics.htm (3.3.1p1)
                   vars: label_chart_title, label, loadchart_label|_data,
                   memchart_label|_data, rxchart_label|_data, txchart_label|_data.
                   Canvas ids loadchart|memchart|rxchart|txchart preserved
                   (pz-theme.js's chart plugin and the stock dashlet JS both key
                   on them). The chip key is the `label` ARGUMENT and never a
                   literal: a theme cannot ship or shadow ISPConfig lang keys, so
                   the tmpl_vars core resolves are the only localised strings
                   available to it. Dataset colours are deliberately NOT set
                   here — pz-theme.js applies them, which is what lets a rebrand
                   move the chart line.
donate.htm      <- interface/web/dashboard/dashlets/templates/donate.htm (3.3.1p1)
                   vars: donate_txt, more_btn_txt, donate2_txt, hide_btn_txt,
                   donate_btn_txt.
                   Hide contract: <a data-load-content='dashboard/dashboard.php
                   ?hide=donate'>, which is what makes core write its
                   hide_donation_dashlet timeout into sys_config
                   (dashboard.php:37-47) — keep the target byte-identical.
                   Stock's inline <script> is deliberately NOT carried over: it
                   binds $("button").click() with no scope, so every button on
                   the dashboard toggles this dashlet. A <details>/<summary>
                   disclosure replaces it and needs no JS. Stock's inline
                   background-color, its <h4> wrapper, its unclosed <p> and its
                   page-global id='description' are dropped for the same class
                   of reason; all five are written up in
                   docs/UPSTREAM-PATCHES.md as candidates for core.
On ISPConfig upgrade: diff these four stock files against the pins above before
re-stamping.
```

- [ ] **Step 8: Verify**

```bash
for ov in themes/*/templates/dashboard/*.htm; do
  design=$(echo "$ov" | cut -d/ -f2); base=$(basename "$ov")
  grep -q "$base" "themes/$design/BUILT-AGAINST.txt" || echo "UNDOCUMENTED $ov"
done
php tests/brand/run.php | tail -3
grep -c "nz-" themes/phosphor/templates/dashboard/*.htm
```
Expected: no `UNDOCUMENTED` lines; `brand suite passed` with the new donate assertions among the `ok`s; every `nz-` count is `0`.

- [ ] **Step 9: Commit**

```bash
git add themes/phosphor/templates/dashboard themes/phosphor/BUILT-AGAINST.txt \
        .github/workflows/ci.yml tests/brand/probe_phosphor.php
git commit -m "feat(phosphor): the four dashboard template overrides

dashboard, modules, metrics and donate, each preserving the stock tmpl_var and
click contracts now pinned in BUILT-AGAINST.txt. Chart colours stay out of the
template so pz-theme.js can follow a rebrand. CI's dashlet-override step walks
themes/*/ instead of naming one design, and the probe carries the donate
contract assertions across."
```

---

### Task 10: `themes/phosphor/README.md`

**Files:**
- Create: `themes/phosphor/README.md`

**Interfaces:**
- Consumes: nothing at runtime.
- Produces: the per-design documentation the repository README links to, and the design-language reference for phosphor — `DESIGN.md` is clarity's and stays clarity's.

- [ ] **Step 1: Write it**

Mirror `themes/clarity/README.md`'s shape — what it is, what is inside, how to re-theme it — and add the sections phosphor needs that clarity does not.

Cover, each in its own short section:

- **What it is.** The third design: black glass with phosphor glow, dark only in v1, selectable in ISPConfig's Design picker beside `clarity` and `classic`. Install with `../../install.sh --design=phosphor`.
- **What is inside.** The seven template overrides by name; the stylesheet load order (`space-grotesk.css` → `jetbrains-mono.css` → `tokens.css` → `icons.css` → `base.css` → `app.css` → `components.css`, with login pages loading `tokens.css` + `icons.css` + `login.css` instead); `assets/fonts/`, `assets/images/`, `assets/favicon/`, `assets/javascripts/`; the three brand endpoints and what each answers with when nothing is stored.
- **The glow budget.** Four places, named: the login mark and its cursor, a primary button on hover or focus, the active rail marker, and the chart line's fill. Not a style note — a budget, because on a black ground a second glowing thing halves the first one's meaning. Adding a fifth means removing one.
- **Mono is a role.** It marks machine-authored values — numerals, table headers, rail group titles, code, counters — and prose is always the grotesk. Emphasis is mono italic because Space Grotesk ships no italic and a synthesised oblique is the tell.
- **Dark only, and what that means for the brand keys.** `rail_hex_light` is read and is a documented no-op here; every other key on the contract applies. Say plainly that a light `login_bg` is honoured exactly and the scene's overlays re-derive from it.
- **To re-theme.** Edit `tokens.css` only. Component rules never reference a raw colour, and `.github/scripts/rail_scan.php` fails the build if one appears.
- **What you may not do.** Do not write a colour literal into a rule painted with a rail token; do not edit `base.css` (it is byte-identical to clarity's by contract); do not add an icon font or an emoji (icons are CSS masks tinted by `currentColor`); do not put a `<span>` in a brand slot where the shells use an `<img>`.
- **BUILT-AGAINST.txt** is the upgrade-safety contract — read it before touching a template, and re-run `install.sh` after every ISPConfig upgrade including patch releases.

- [ ] **Step 2: Verify the links resolve**

```bash
grep -oE '\]\([^)]+\)' themes/phosphor/README.md | tr -d '])(' | while read -r p; do
  case "$p" in http*) continue;; esac
  [ -e "themes/phosphor/$p" ] || [ -e "$p" ] || echo "BROKEN LINK $p"
done
```
Expected: no `BROKEN LINK` lines.

- [ ] **Step 3: Commit**

```bash
git add themes/phosphor/README.md
git commit -m "docs(phosphor): the design's own README

What it is, what is inside, the glow budget, the mono role, what dark-only means
for the brand contract, and the rules a change to this design must not break."
```

---

### Task 11: Register the design with the installers

**Files:**
- Modify: `install.sh`
- Modify: `uninstall.sh`

**Interfaces:**
- Consumes: `themes/phosphor/` as laid out by Tasks 1–10.
- Produces: `--design=phosphor` and `--design=all` including it, and both version stamps written into the deployed directory.

The spec's *Out of scope* says "Changes to `install.sh` beyond registering a third design name." That is exactly what this is, and the honest answer to "does the installer discover it with no special casing?" is **no, and here is precisely what must change**: `ALL_DESIGNS` and the `--design=` case arm in each script, four lines in total. Everything else already generalises — `deploy()`, `check_stray()`, the stamping loop, the closing instructions' `case` (phosphor takes the `*)` arm, whose wording — "diff the seven overridden templates (three shell + four dashboard)" — is already correct for it), and `uninstall.sh`'s `remove_dest` arm (phosphor takes the `*)` arm, which removes the two stamps and nothing else, which is right because phosphor's templates are committed rather than generated).

- [ ] **Step 1: Write the failing check**

```bash
bash install.sh --design=phosphor --theme /nonexistent 2>&1 | head -3
```
Expected: `ERROR: unknown design: phosphor (known: clarity classic, all)` and exit 2. That is the failure this task fixes, and it is the right failure to have: a typo'd design name must never silently install the wrong one, because ISPConfig only lists a design in its picker once the directory exists.

- [ ] **Step 2: Register in `install.sh`**

```bash
# Every design this repository ships, in install order. `classic` last so the
# "Design picker" lists them in the order the docs introduce them.
ALL_DESIGNS="clarity phosphor classic"
```

and in the `--design=*` case:

```sh
        all)      for d in $ALL_DESIGNS; do want_design "$d"; done ;;
        clarity)  want_design clarity ;;
        phosphor) want_design phosphor ;;
        classic)  want_design classic ;;
```

Leave the default untouched — `if [ -z "$DESIGNS" ]; then DESIGNS="clarity"; fi`. A bare `./install.sh` must keep meaning exactly what it meant before a third design existed; adding a design to somebody's Design picker is something they ask for, not something an upgrade does to them.

Update the usage header block (lines 20–30, which `--help` prints verbatim) to introduce phosphor in one line beside the other two, and the `--design=<n>` line's list of valid names.

- [ ] **Step 3: Register in `uninstall.sh`**

The identical two edits: `ALL_DESIGNS="clarity phosphor classic"` and a `phosphor) want_design phosphor ;;` arm. `uninstall.sh` already defaults to *all* designs, which is correct and must stay — uninstalling has to clear whatever might be there. Update its usage header's `--design=<n>` line too.

- [ ] **Step 4: Verify**

```bash
bash -n install.sh && bash -n uninstall.sh && echo "syntax ok"
bash install.sh --definitely-not-a-flag >/dev/null 2>&1 && echo "BUG: accepted unknown flag" || echo "unknown flag rejected"
bash uninstall.sh --definitely-not-a-flag >/dev/null 2>&1 && echo "BUG: accepted unknown flag" || echo "unknown flag rejected"
bash install.sh --design=phosphor --theme /nonexistent 2>&1 | head -3
bash install.sh --design=nosuchdesign --theme /nonexistent 2>&1 | head -1
bash install.sh --help | sed -n '1,30p'
```
Expected: `syntax ok`; both `unknown flag rejected`; the `--design=phosphor` run now gets past flag parsing and fails on the missing target (`ERROR: /nonexistent/interface/web not found`), which proves the name was accepted; `--design=nosuchdesign` still errors with `known: clarity phosphor classic, all`; `--help` lists phosphor.

Then dry-run the deploy against a throwaway tree, so the stamping path is exercised without a panel:

```bash
T=$(mktemp -d)
mkdir -p "$T/interface/web/themes/default" "$T/interface/lib"
printf "<?php define('ISPC_APP_VERSION', '3.3.1p1');\n" > "$T/interface/lib/config.inc.php"
bash install.sh --theme --design=phosphor --copy --no-assign "$T" 2>&1 | tail -20
cat "$T/interface/web/themes/phosphor/ispconfig_version"; echo
cat "$T/interface/web/themes/phosphor/ISPC_VERSION"; echo
ls "$T/interface/web/themes/phosphor/"
bash uninstall.sh --theme --design=phosphor "$T" 2>&1 | tail -10
ls "$T/interface/web/themes/"
rm -rf "$T"
```
Expected: the install reports `stamped ispconfig_version + ISPC_VERSION = '3.3.1p1'`, both files contain `3.3.1p1`, the directory holds the endpoints, `templates/`, `assets/`, `README.md` and `BUILT-AGAINST.txt`; the uninstall removes the directory and `ls` shows only `default` left.

- [ ] **Step 5: Commit**

```bash
git add install.sh uninstall.sh
git commit -m "feat: register phosphor with the installers

ALL_DESIGNS and the --design case arm in each script — four lines. Everything
else already generalises: deploy, the stray check, the version stamping and the
closing instructions all take phosphor through their existing paths, and
uninstall's non-classic arm is already right for a design with committed
templates. A bare ./install.sh still means clarity."
```

---

### Task 12: Parameterise the mockup harness over the design

**Files:**
- Modify: `mockup/build.py`
- Create: `mockup/fragments/client-limits-form.html`
- Create (generated, gitignored or committed per the repo's existing habit for `mockup/webroot/`): the phosphor renders and screenshots

**Interfaces:**
- Consumes: `themes/phosphor/templates/`, `assets/` and `assets/javascripts/pz-theme.js` from Tasks 2–9.
- Produces: `python3 mockup/build.py [--design=clarity|phosphor] [--shoot]`. **clarity remains the default and its output must stay byte-identical**, because the harness's whole value is that before/after runs pixel-diff to zero.

The spec's *Mockup plan* also asks for a `customizer-branding.html` fragment rendered from the real `customizer_edit.htm`. That belongs to the sibling plan — the Branding page's markup is being rewritten there, and rendering the current template would prove nothing about the redesign. This task delivers the four theme screens.

- [ ] **Step 1: Capture the baseline**

```bash
python3 mockup/build.py --shoot
sha256sum mockup/shots/*.png > /tmp/mockup-baseline.txt
wc -l /tmp/mockup-baseline.txt
```
Expected: ten digests. Any parameterisation that changes one of these has changed clarity, which this task must not.

- [ ] **Step 2: Introduce the design axis**

Replace the module-level `DARK` constant and the values derived from it with a small record, and read `--design=` from `sys.argv`:

```python
# ---------------------------------------------------------------------------
# The design under test. The harness renders a design's REAL templates, so
# everything that differs between designs is a property of the design and not
# of the harness: its directory, the theme name the templates interpolate, the
# CSS class prefix its shell uses for the AJAX sinks, its runtime script, and
# the page-name prefix its output files take.
#
# clarity stays the default and its output must stay byte-identical: the point
# of this harness is that two runs pixel-diff to zero, and a refactor that
# moved a single byte would destroy the baseline it exists to protect.
# ---------------------------------------------------------------------------
DESIGNS = {
    "clarity": {
        "dir": REPO / "themes/clarity",
        "theme": "clarity",
        "prefix": "nz",
        "script": "themes/clarity/assets/javascripts/nz-theme.js",
        "out": "dark",          # historic page names: dark-dashboard, dark-login, …
        "login_body": "nz-login",
    },
    "phosphor": {
        "dir": REPO / "themes/phosphor",
        "theme": "phosphor",
        "prefix": "pz",
        "script": "themes/phosphor/assets/javascripts/pz-theme.js",
        "out": "phosphor",
        "login_body": "pz-login",
    },
}

DESIGN_NAME = next((a.split("=", 1)[1] for a in sys.argv if a.startswith("--design=")), "clarity")
if DESIGN_NAME not in DESIGNS:
    raise SystemExit(f"unknown design: {DESIGN_NAME} (known: {', '.join(DESIGNS)})")
DESIGN = DESIGNS[DESIGN_NAME]
THEME = DESIGN["dir"]
```

Then, mechanically:

- `BASE_VARS["current_theme"]` becomes `DESIGN["theme"]`.
- `build_dark_page()` and `build_dark_login()` read from `THEME` instead of `DARK`; rename them `build_page()` and `build_login()` and update their two call sites.
- The `#sidebar` fill regex becomes `rf"(<div id='sidebar' class='{DESIGN['prefix']}-context'>)\s*(</div>)"`.
- `CHART_BOOTSTRAP`'s `<script src='…nz-theme.js'>` becomes `DESIGN["script"]`, and the `#nz-dash-chips`/`#nz-stat-` ids inside it take the prefix. Keep the rest of that bootstrap verbatim — it re-injects the real Chart.js and the **shipped** runtime, which is what makes the screenshot evidence about the actual chart plugin rather than about a stub.
- `build()`'s symlink `(WEBROOT / "themes/clarity").symlink_to(DARK)` becomes `(WEBROOT / f"themes/{DESIGN['theme']}").symlink_to(THEME)`.
- Output page names become `f"{DESIGN['out']}-{page}"`, so clarity keeps `dark-dashboard.html` and phosphor writes `phosphor-dashboard.html`.
- The stock `default.html` baseline is design-independent: keep building it unconditionally.

- [ ] **Step 3: Prove clarity is unmoved**

```bash
python3 mockup/build.py --shoot
sha256sum -c /tmp/mockup-baseline.txt
```
Expected: every line `OK`. If any differs, the parameterisation changed clarity's render and must be corrected before going further — do not accept a "cosmetically identical" diff.

- [ ] **Step 4: Add the phosphor page set and the missing fragment**

The spec's screen list is login, dashboard, sites list, client edit → Limits, and the Branding page (sibling plan). `mockup/fragments/` has no limits fragment; `mockup/phosphor/fragments/client-limits.html` does. Promote it into the shared harness so both designs can render it:

```bash
cp mockup/phosphor/fragments/client-limits.html mockup/fragments/client-limits-form.html
```

Then add a per-design page table, keyed off the design so clarity's four historic pages are untouched:

```python
PAGES = {
    "clarity": {   # historic names — do not rename, the shot baseline uses them
        "dashboard":  ("dashboard", "news.html", "dashboard.html"),
        "sites":      ("sites", "sidenav-sites.html", "sites-list.html"),
        "form":       ("mail", "sidenav-mail.html", "mail-user-form.html"),
        "components": ("dashboard", "news.html", "components.html"),
    },
    "phosphor": {
        "dashboard":     ("dashboard", "news.html", "dashboard.html"),
        "sites":         ("sites", "sidenav-sites.html", "sites-list.html"),
        "client-limits": ("client", "sidenav-sites.html", "client-limits-form.html"),
    },
}
```

`build()`'s render loop then reads `PAGES[DESIGN_NAME].items()` instead of `DARK_PAGES.items()`, and writes each page as `WEBROOT / f"{DESIGN['out']}-{name}.html"` — which reproduces clarity's historic `dark-dashboard.html` exactly and gives phosphor `phosphor-dashboard.html`. `PAGE_SCRIPTS` is keyed by that same short page name (`"dashboard"`), not by the output filename, so one entry serves both designs; its lookup in `build_page()` therefore needs no change once the loop passes the short name.

The login page stays outside `PAGES` — it renders from a different shell — and is written as `WEBROOT / f"{DESIGN['out']}-login.html"`.

Then the shot matrix, from the spec's viewport column — 1440×900 and 390×844 for login and dashboard, 1440×900 for the sites list, 1440×900 and 1280×900 for the limits form:

```python
VIEWPORTS = {"desktop": (1440, 900), "mobile": (390, 844), "narrow": (1280, 900)}

SHOT_MATRIX = {
    # clarity's list is the existing module-level SHOT_MATRIX, moved under this
    # key with not one entry changed — the ten names it produces are the
    # baseline digests every future refactor is diffed against.
    "clarity": [
        ("dark-dashboard",  ("desktop", "mobile")),
        ("dark-sites",      ("desktop",)),
        ("dark-form",       ("desktop",)),
        ("dark-components", ("desktop",)),
        ("dark-login",      ("desktop", "mobile")),
        ("light-dashboard", ("desktop",)),
        ("light-login",     ("desktop",)),
        ("default",         ("desktop",)),
    ],
    "phosphor": [
        ("phosphor-login",         ("desktop", "mobile")),
        ("phosphor-dashboard",     ("desktop", "mobile")),
        ("phosphor-sites",         ("desktop",)),
        ("phosphor-client-limits", ("desktop", "narrow")),
        ("default",                ("desktop",)),
    ],
}
```

`shoot()` iterates `SHOT_MATRIX` directly, so change its one loop header:

```python
            for name, labels in SHOT_MATRIX[DESIGN_NAME]:
```

Transcribe clarity's list from the existing module-level constant rather than from the block above, and diff the two before deleting the original — the entry order and the exact viewport tuples decide which digests Step 3 compares.

- [ ] **Step 5: Render and shoot phosphor**

```bash
python3 mockup/build.py --design=phosphor --shoot
```
Expected: seven PNGs written, each line reporting `(0 failed requests)`. **A non-zero failed-request count is a real defect** — it means a stylesheet, font or image path in the shell does not resolve, which on a panel is a 404 for every visitor. Read the printed URLs and fix the template, not the harness.

- [ ] **Step 6: Look at the screenshots against the spec's "must prove" column**

Open each and check the specific claim the spec attaches to that screen:

| Shot | Must prove |
|---|---|
| `phosphor-login-desktop`, `-mobile` | the one bold moment; the glow budget holds (mark + cursor only); the 384px card and the grid still read at 375px |
| `phosphor-dashboard-desktop`, `-mobile` | glass over black at ONE depth (no pane inside a blurred pane); mono numerals against grotesk prose; amber chart theming with no light paper island |
| `phosphor-sites-desktop` | 13px table density; mono caps header band; row hover; demoted in-row actions; the table scrolls inside its own container and the page body does not |
| `phosphor-client-limits-desktop`, `-narrow` | the cluttered form survives: two-column grid at ≥1280px, section captions in the mono voice, glass not stacked on glass |

Fix what the screenshots show in the stylesheet, re-run, and re-check. Every fix must keep `php .github/scripts/rail_scan.php` green.

- [ ] **Step 7: Prove the script is optional**

The "progressive enhancement only" claim in the spec, in the README and in `pz-theme.js`'s own header is asserted everywhere and tested nowhere. The harness already strips every script from a rendered page and re-injects only the controlled bootstrap, so the script-free state is one flag away. Add `--no-script` to the harness, which skips that re-injection:

```python
# module level, beside the other argv reads
NO_SCRIPT = "--no-script" in sys.argv

# inside build_page(), replacing the existing `extra = PAGE_SCRIPTS.get(name, "")`
    extra = "" if NO_SCRIPT else PAGE_SCRIPTS.get(name, "")
```

Then:

```bash
python3 mockup/build.py --design=phosphor --no-script
python3 -m http.server 8899 -d mockup/webroot &
```

Open `phosphor-dashboard.html`, `phosphor-sites.html`, `phosphor-client-limits.html` and `phosphor-login.html` on `http://127.0.0.1:8899/` and confirm: the frame renders, the rail and topbar are correct, the tables and forms are readable, the login scene reveals and the cursor is present, and the only thing missing is the chart drawing. Anything else that breaks is a rule that belongs in CSS and is sitting in JS. Stop the server when done.

Do not commit these renders — `mockup/webroot/` is a scratch directory the harness rebuilds. Re-run without the flag before Step 8.

- [ ] **Step 8: Commit**

```bash
git add mockup/build.py mockup/fragments/client-limits-form.html mockup/phosphor/shots
git commit -m "feat(mockup): parameterise the harness over the design

--design=clarity|phosphor. clarity is still the default and its ten shot
digests are unchanged, which is the check that matters: the harness exists so
before/after runs pixel-diff to zero. Adds phosphor's four theme screens and
promotes the client-limits fragment into the shared fragment set."
```

---

### Task 13: Repository documentation

**Files:**
- Modify: `README.md`
- Modify: `DESIGN.md`
- Modify: `CONTRIBUTING.md`
- Modify: `UPGRADING.md`
- Modify: `SECURITY.md`

There is no `CHANGELOG` in this repository — verified with `find . -maxdepth 2 -iname 'CHANGELOG*'`, which returns nothing — so there is nothing to update there. Say so in the commit message rather than leaving the question open.

- [ ] **Step 1: `README.md`**

- The **What you get › The interface** lead-in says "Two designs, selected with `--design`". Change to three, and keep the framing: they are alternatives, not layers.
- Add a `#### phosphor — black glass, dark only` section between the clarity and classic subsections, matching their bullet shape: what it looks like; **dark only in v1, no switcher**; seven overridden templates; self-hosted Space Grotesk and JetBrains Mono with no external request; charts themed dark rather than floated on a light island; install with `--design=phosphor`.
- **Install**: `--design=<name>` "takes `clarity`, `classic` or `all`" becomes "`clarity`, `phosphor`, `classic` or `all`". The default stays clarity and the sentence saying so stays.
- **Uninstall**: the same list.
- **The brand-token contract**: "The two implementations are …" becomes three, adding `themes/phosphor/brand.php`, with one clause on what it does differently — it emits `--pz-*` custom properties like clarity, and reads `rail_hex_light` as a documented no-op because it has no light scope.
- **Repo layout**: three new rows — `themes/phosphor/`, `themes/phosphor/brand.php`, `themes/phosphor/favicon.php` — in the shape of the clarity rows beside them.
- The clarity bullet currently says "Six overridden templates" and then lists seven files; `UPGRADING.md` and `BUILT-AGAINST.txt` both say seven. Fix the count while you are in the file — it is a one-word correction and leaving a known-wrong number beside a new design's correct one is worse than the original error.

- [ ] **Step 2: `DESIGN.md`**

`DESIGN.md` is clarity's design language and stays that way. Amend only its scope paragraph, which currently says the extension ships two designs with opposite goals:

```markdown
**Scope: this document describes the `clarity` design only.** The ISPConfig
Theme Customizer extension ships three designs, and they have different goals.
`clarity` (`themes/clarity/`) is the one this document is about: a ground-up
dark and light interface with a design language of its own. `phosphor`
(`themes/phosphor/`) has its own, unrelated language — black glass with a
budgeted amber glow, dark only — written up in
`docs/superpowers/specs/2026-09-07-phosphor-design.md` and summarised in
`themes/phosphor/README.md`; none of the tokens, surfaces or component rules
below apply to it. `classic` (`themes/classic/`) deliberately has no design
language at all: it is the stock ISPConfig look, re-coloured from the same
Branding page. See `themes/classic/README.md`.
```

Do not add phosphor's tokens here. Two design languages in one document is how both become unreadable, and phosphor's lives with phosphor.

- [ ] **Step 3: `CONTRIBUTING.md`**

- The area list: `themes/` is "the design layer (`clarity`, `phosphor` and `classic`)".
- "Two designs read them today" becomes three.
- The **Two designs ship** table gains a `themes/phosphor/` row: "Black glass, dark only: seven overridden templates, its own CSS, self-hosted Space Grotesk and JetBrains Mono, every stock contract pinned in `BUILT-AGAINST.txt`."
- Add a `#### themes/phosphor/` subsection after clarity's, with the same file/role table, the stylesheet load order, and the two rules specific to it: no light-mode value is needed for a new token (there is no remap block), and `base.css`/`icons.css` are ports whose parity CI asserts.
- **Ground rules**: rule 3 currently reads "Every new token needs a light-mode value in the remap block — except `--nz-rail-accent`". Add: "This rule is clarity's. `phosphor` has no light scope and no remap block; a new `--pz-*` token needs a dark value and nothing else. What phosphor's tokens do need is to survive `.github/scripts/rail_scan.php`: no component rule may carry a colour literal, and no rule painted with a rail token may hardcode its ink."
- **Developing and testing a change**: add phosphor to the `--design` sentence, and add a line to the checking paragraph: "On phosphor there is no mode toggle either — check it with a non-default accent and rail colour, check the login screen separately, and check it with `prefers-reduced-motion: reduce` on, because that is where a brand slot has been lost before."
- **The mockup harness**: "It does not cover classic" stays true; add "`--design=phosphor` renders phosphor's screens; clarity is the default and its output is byte-stable, which is what makes the pixel-diff check meaningful."
- Add the four new CI steps to the CI description: the rail scanner, the ported-stylesheet parity check, the external-request check, and the fact that the cache-buster, favicon-link and dashlet-override steps now walk `themes/*/`.

- [ ] **Step 4: `UPGRADING.md`**

- The lead-in "Two designs ship here, `clarity` and `classic`" becomes three, and the following sentence — "Only clarity has template contracts you may have to check by hand" — becomes "clarity and phosphor each have template contracts you may have to check by hand; classic's shell is regenerated by that same run."
- The `### clarity: seven overrides, checked by hand` section: add a sibling `### phosphor: seven overrides, checked by hand` with the same `diff` block against the stock templates, pointing at `themes/phosphor/templates/` and `themes/phosphor/BUILT-AGAINST.txt`. Keep the same fallback note: deleting `themes/phosphor/templates/dashboard/` restores the stock dashlets while a shell issue is sorted out.
- The `for d in clarity classic; do` loop near line 282 becomes `for d in clarity phosphor classic; do`.
- The `--design` re-run guidance: `sudo ./install.sh --design=phosphor` alongside the classic example.

- [ ] **Step 5: `SECURITY.md`**

The **The pre-auth surface** section opens "There are **six** of these, not two". With phosphor there are **nine**. Rewrite the opening paragraph and correct the counts that follow it — and while you are there, fix the one that is already wrong:

```markdown
There are **nine** of these, not two: `brand.php` (CSS), `title.php` (JS) and
`favicon.php` (an image) under `themes/clarity/`, and the same three under
`themes/phosphor/` and `themes/classic/`. Each design links its own three from
**both** of its shell templates, including the login shell, and only one design
is active per request — but once all three are installed, all nine exist on
disk in the web root and all nine are reachable by URL. They run with no session
and must be safe for anonymous requests. All nine are written for that, and the
read half — query, unescape, normalise, cache — is deliberately the same code in
every design, so they cannot drift apart:
```

Then, in the bullets below it: "All six re-assert their MIME type" → "All nine"; "The other three endpoints read no request input whatsoever" → "The other **eight** endpoints read no request input whatsoever" (only `themes/classic/brand.php` takes `?scene=`); and "**No code execution surface.** … All four endpoints read and emit validated scalars" → "All nine" — that "four" is stale from before the favicon endpoint existed and is wrong today, independently of this change.

Add one paragraph to that section, because phosphor's reader does something the other two do not:

```markdown
`themes/phosphor/brand.php` performs one piece of arithmetic the other two do
not — an sRGB → OKLab → OKLCh round trip, to derive the dim accent from the
operator's `accent_hex`. It is pure floating-point maths over a value that has
already passed the `#rrggbb` regex, it allocates nothing, it branches on
nothing, and it emits a `#rrggbb` built with `sprintf('%02X')` from three
clamped integers — so it cannot widen the output's character set beyond what
the hex validator already permits. It is called at most once per request.
```

Also add a row to the **Known exposure: each design directory's version file** section noting that `themes/phosphor/ispconfig_version` and `ISPC_VERSION` are the same exposure as the other designs' and are covered by the same `contrib/webserver/` snippets, whose rules already match any design directory.

- [ ] **Step 6: Verify the docs agree with the code**

```bash
grep -rn "Two designs\|two designs\|six\b.*endpoint\|There are \*\*six\*\*" README.md CONTRIBUTING.md UPGRADING.md SECURITY.md DESIGN.md
grep -rn "clarity, classic or all\|clarity\`, \`classic\` or" README.md CONTRIBUTING.md
grep -c "phosphor" README.md CONTRIBUTING.md UPGRADING.md SECURITY.md DESIGN.md
```
Expected: the first two greps return nothing; every file's phosphor count is non-zero.

- [ ] **Step 7: Commit**

```bash
git add README.md DESIGN.md CONTRIBUTING.md UPGRADING.md SECURITY.md
git commit -m "docs: introduce phosphor across the repository documentation

README's design list, install and uninstall flags, brand-contract implementers
and repo layout; DESIGN.md's scope note (it stays clarity's language, phosphor's
lives with phosphor); CONTRIBUTING's area list, ground rules and CI description;
UPGRADING's per-design override check; SECURITY's pre-auth surface, now nine
endpoints, plus the OKLCh note. Corrects two counts that were already stale:
clarity's 'six overridden templates' and SECURITY's 'all four endpoints'.
No CHANGELOG exists in this repository, so there is none to update."
```

---

### Task 14: Full local verification, and the release note

**Files:**
- Modify: none unless a check fails.

- [ ] **Step 1: Run everything that runs locally**

```bash
set -e
find themes tests .github -name '*.php' -print0 | while IFS= read -r -d '' f; do php -l "$f"; done
php tests/brand/run.php
php .github/scripts/rail_scan.php
bash .github/scripts/design_asset_parity.sh
bash .github/scripts/no_external_requests.sh
bash -n install.sh; bash -n uninstall.sh
for f in install.sh uninstall.sh; do
  bash "$f" --definitely-not-a-flag >/dev/null 2>&1 && { echo "BUG: $f accepted an unknown flag"; exit 1; } || true
done
python3 mockup/build.py --shoot >/dev/null && sha256sum -c /tmp/mockup-baseline.txt
python3 mockup/build.py --design=phosphor --shoot
```
Expected: every `php -l` clean; `brand suite passed`; `rail scan passed`; both bash scripts green; the installers reject the bad flag; clarity's ten digests still `OK`; phosphor's seven shots render with zero failed requests.

- [ ] **Step 2: Run the CI steps that have no script**

Copy each remaining step body out of `.github/workflows/ci.yml` and run it: the brand-token contract grep, the favicon endpoint grep, the cache-buster loop, the favicon-link loop, the dashlet-override loop, and the `classic ships no assets` find.
Expected: all pass. In particular `classic ships no assets and no committed templates` must still pass — nothing in this plan touches `themes/classic/`, and a failure there means a stray file landed in the wrong directory.

- [ ] **Step 3: State what could not be proven here**

Write this into the PR description verbatim, because a reviewer must not read a green local run as a green panel:

```
Proven locally: PHP lint, the four-way brand suite (probe_phosphor, the rail ink
contract on 17 rail colours, the OKLCh drop, the token contrast floors, the
donate specificity guard, the resolver parity matrix), the rail-ink scanner, the
ported-stylesheet parity check, the external-request check, both installers'
syntax and flag handling, an install/uninstall dry run against a throwaway tree,
and the mockup render at four viewports with zero failed requests.

Proven only in CI: tests/svg/run.php and the language checks — both untouched by
this change, and both needing PHP extensions this machine does not have (dom,
xml, mbstring).

NOT proven anywhere yet, and needing a test panel before release:
  - brand.php, title.php and favicon.php against a real sys_ini row. The probes
    slice the endpoint body below the mysqli block by design, so nothing here
    executes the database half.
  - the version gate: ISPConfig matches ispconfig_version EXACTLY against
    ISPC_APP_VERSION, and a wrong stamp silently resets every user to the
    default theme at login.
  - the pushy drawer, select2, the datetimepicker and the tipsy tooltips against
    the real vendor JS. The mockup strips scripts.
  - the AJAX content lifecycle: #pageContent replacement, the datalog modal, and
    pz-theme.js's MutationObserver re-application on loaded markup.
  - a real logo upload through the Branding page, on all three surfaces, with
    one variant stored and with both.
```

- [ ] **Step 4: Record the release dependency**

phosphor ships in the next major, which is also the release that carries the GPLv3 relicence. **The relicence is not part of this work** and must not be done here: no `LICENSE` change, no per-file header change, no `CONTRIBUTING.md` licence sentence change. Add one line to the PR description:

```
Ships in the next major. That release also carries the GPLv3 relicence, which is
a separate task and is deliberately absent from this branch — every file added
here carries the repository's current licence, and the relicence will move them
along with everything else in one change.
```

Confirm nothing licence-related crept in:

```bash
git diff --stat $(git merge-base HEAD main 2>/dev/null || echo HEAD~14) HEAD -- LICENSE
grep -rn "GPL" themes/phosphor/ | grep -v "icons.css" || echo "no GPL claim in the new design"
```
Expected: no `LICENSE` change; no GPL claim in phosphor's files.

- [ ] **Step 5: Commit (only if a check needed a fix)**

If Steps 1–2 were all green, there is nothing to commit and the branch is ready. If a fix was needed:

```bash
git add -A
git commit -m "fix(phosphor): <what the verification run caught>"
```
