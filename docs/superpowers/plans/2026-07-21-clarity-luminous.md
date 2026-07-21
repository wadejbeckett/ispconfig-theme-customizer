# Clarity v2.2 "Luminous" Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the signature "luminous depth" redesign — horizon-beam login, restructured dashboard via theme-side dashlet overrides, accent-derived light system — as theme v2.2.0, mockup-approved before deploy.

**Architecture:** All changes live inside `themes/clarity/` (CSS tokens + login.css + three template overrides under `templates/dashboard/` + nz-theme.js chart theming). Glow colors derive from the existing `--nz-blue-*` ramp via `color-mix()`, so brand.php re-huing keeps working untouched. The mockup harness (`mockup/build.py --shoot`) is the test rig: every task ends with rendered screenshots inspected in dark AND light.

**Tech Stack:** Plain CSS (custom properties, `color-mix()`, `backdrop-filter` with fallback), vlibTemplate `.htm` templates, vanilla JS in `nz-theme.js`, Python/Playwright mockup harness.

## Global Constraints

- NO ISPConfig core file is created or modified; only `themes/clarity/**` and `mockup/**` change.
- brand.php contract byte-identical (glow tokens must reference `--nz-blue-*` ramp vars so re-huing propagates).
- Glow allowed ONLY on: login horizon, primary-action hover/focus-visible, active rail marker, chart area fills. Never on text.
- Motion 150-200ms; every animation inside `@media (prefers-reduced-motion: no-preference)`.
- No new fonts, images, or HTTP requests.
- Every dashlet override's template-variable contract is recorded in `themes/clarity/BUILT-AGAINST.txt`.
- Deploy ONLY after the operator approves the mockup screenshots (Task 8 gate).

---

### Task 1: Light-system tokens

**Files:**
- Modify: `themes/clarity/assets/stylesheets/clarity/tokens.css` (append a "luminous" section)

**Interfaces:**
- Produces CSS custom properties consumed by Tasks 2-6: `--nz-glow-soft`, `--nz-glow-ring`, `--nz-glass-fill`, `--nz-glass-edge`, `--nz-chart-fill-top`, `--nz-chart-fill-bottom`.

- [ ] **Step 1: Append the token block** (dark scope `:root`, light overrides in the existing `:root[data-nz-theme='light']` section):

```css
/* ---------- v2.2 luminous light system ---------- */
:root {
  --nz-glow-soft:        color-mix(in srgb, var(--nz-blue-500) 22%, transparent);
  --nz-glow-ring:        color-mix(in srgb, var(--nz-blue-400) 45%, transparent);
  --nz-glass-fill:       color-mix(in srgb, var(--nz-card) 72%, transparent);
  --nz-glass-edge:       color-mix(in srgb, #ffffff 9%, transparent);
  --nz-chart-fill-top:   color-mix(in srgb, var(--nz-blue-400) 28%, transparent);
  --nz-chart-fill-bottom:color-mix(in srgb, var(--nz-blue-400) 2%, transparent);
}
:root[data-nz-theme='light'] {
  --nz-glow-soft:        color-mix(in srgb, var(--nz-blue-500) 14%, transparent);
  --nz-glow-ring:        color-mix(in srgb, var(--nz-blue-600) 30%, transparent);
  --nz-glass-fill:       color-mix(in srgb, var(--nz-card) 86%, transparent);
  --nz-glass-edge:       color-mix(in srgb, #0b2b45 10%, transparent);
  --nz-chart-fill-top:   color-mix(in srgb, var(--nz-blue-500) 20%, transparent);
  --nz-chart-fill-bottom:color-mix(in srgb, var(--nz-blue-500) 2%, transparent);
}
```

- [ ] **Step 2: Verify no visual regression** — `python3 mockup/build.py --shoot`, read `mockup/shots/dark-dashboard-desktop.png` + `dark-login-desktop.png`: identical to baseline (tokens are inert until consumed).
- [ ] **Step 3: Commit** `git add themes/clarity/assets/stylesheets/clarity/tokens.css && git commit -m "tokens: luminous light system (accent-derived via color-mix)"`

### Task 2: Login — horizon beam

**Files:**
- Modify: `themes/clarity/assets/stylesheets/clarity/login.css` (background field + beam + glass card)
- Modify (only if wordmark must move above the card): `themes/clarity/templates/main_login.tpl.htm`

**Interfaces:**
- Consumes Task 1 tokens. Must NOT change: form field markup/ids, `.nzl-brand img` (brand.php logo override target), customizer footnote + `.nz-credit-*` spans.

- [ ] **Step 1: Build the composition** — body background becomes a layered field (deep vertical gradient + two radial blooms); insert the beam as `body.nzl::before` (1px accent line, `--nz-blue-500`, full width at ~38vh) and `body.nzl::after` (bloom: radial gradient of `--nz-glow-soft` rising from the line). Card: `background: var(--nz-glass-fill); backdrop-filter: blur(14px); border: 1px solid var(--nz-glass-edge);` plus `@supports not (backdrop-filter: blur(1px))` solid fallback. Wordmark block moves visually above the card (order via flex column on the existing wrapper — template edit only if the DOM order forbids it).
- [ ] **Step 2: Render + iterate** — `python3 mockup/build.py --shoot --only login`; inspect dark + light + mobile shots; refine within the grammar (beam intensity, bloom radius, card blur) until it reads "cinematic, calm". Mobile ≤480px: bloom off, card full-width.
- [ ] **Step 3: Commit** `git commit -m "login: horizon-beam composition (v2.2 luminous)"`

### Task 3: Dashboard dashlet template overrides

**Files:**
- Create: `themes/clarity/templates/dashboard/dashboard.htm`
- Create: `themes/clarity/templates/dashboard/modules.htm`
- Create: `themes/clarity/templates/dashboard/metrics.htm`
- Modify: `themes/clarity/BUILT-AGAINST.txt` (append the three contracts)
- Modify: `mockup/fragments/dashboard.html` (regenerate to the new markup so the harness renders it)

**Interfaces:**
- Template variable contracts (pinned from stock 3.3.1p1):
  - dashboard.htm: `welcome_user`; loops `error|warning|info` (`error_msg|warning_msg|info_msg`); loops `leftcol|rightcol` (`content`); keep the three `message_ack.php` alert-close handlers verbatim.
  - modules.htm: `available_modules_txt`; loop `modules` → `modules_icon`, `modules_title`, `modules_name`, `go_to_txt`; launcher click contract = `data-capp='<modules_name>'` on an `<a>`.
  - metrics.htm: `label_chart_title`, `label`, `loadchart_label|_data`, `memchart_label|_data`, `rxchart_label|_data`, `txchart_label|_data`; canvas ids `loadchart|memchart|rxchart|txchart` (nz-theme.js re-theming keys on Chart instances, ids stay).
- Produces class hooks consumed by Task 4 CSS: `.nz-dash-head`, `.nz-dash-chips`, `.nz-launcher`, `.nz-statgrid`, `.nz-statcard`, `.nz-statcard-value`.

- [ ] **Step 1: dashboard.htm** — stock alert/loop structure retained verbatim; the page-header becomes:

```html
<div class='page-header nz-dash-head'>
  <h1>{tmpl_var name='welcome_user'}</h1>
  <div class='nz-dash-chips' id='nz-dash-chips'></div>
</div>
```

(chips filled by the metrics template's script when that dashlet is present; absent = empty div, harmless.)
- [ ] **Step 2: modules.htm** — same loop/vars, chip row markup: `<ul class='modules nz-launcher clear'>` with `<li><a href='#' data-capp='{tmpl_var name="modules_name"}'><span class='{tmpl_var name="modules_icon"}'></span><span class='nz-launcher-label'>{tmpl_var name='modules_title'}</span></a></li>` — one anchor per module (keeps the stock `data-capp` delegate contract), no per-tile button.
- [ ] **Step 3: metrics.htm** — 2×2 `.nz-statgrid`; each `.nz-statcard`: `<header><span class='nz-statcard-label'>{label}</span><span class='nz-statcard-value' id='nz-stat-loadchart'>—</span></header><canvas id='loadchart'></canvas>` (×4). Template script keeps `createChart(name,label,labels,data)` signature but with modern options (no legend hack, thin line, gradient fill left to nz-theme.js) AND sets each headline: `document.getElementById('nz-stat-'+chartname).textContent = fmt(data[data.length-1])` + pushes the same values into `#nz-dash-chips`.
- [ ] **Step 4: contracts** — append all three variable lists to BUILT-AGAINST.txt with the stock file paths + this date.
- [ ] **Step 5: regenerate `mockup/fragments/dashboard.html`** to the new dashlet markup (same synthetic data series as today's fragment).
- [ ] **Step 6: Render** `python3 mockup/build.py --shoot --only dashboard`; inspect; structural iteration only (styling lands in Task 4).
- [ ] **Step 7: Commit** `git commit -m "dashboard: dashlet template overrides (launcher row, stat grid, header band)"`

### Task 4: Dashboard styling

**Files:**
- Modify: `themes/clarity/assets/stylesheets/clarity/app.css` (dashboard section) and `components.css` (cards)

**Interfaces:** consumes Task 3 class hooks + Task 1 tokens.

- [ ] **Step 1: Style** — `.nz-launcher` horizontal wrap of pill chips (icon + label + glass edge, hover lifts with `--nz-glow-ring` border); `.nz-statgrid` CSS grid `repeat(auto-fit,minmax(280px,1fr))` gap 16; `.nz-statcard` glass card, value in 28px/600 tabular-ish numerals; `.nz-dash-head` band with date (JS-set) right-aligned chips.
- [ ] **Step 2: Render + iterate** dark/light/mobile until it sings; grammar rules apply.
- [ ] **Step 3: Commit** `git commit -m "dashboard: luminous styling for launcher, stat grid, header band"`

### Task 5: Chart theming upgrade (nz-theme.js)

**Files:**
- Modify: `themes/clarity/assets/javascripts/nz-theme.js` (the existing Chart.js `beforeInit` plugin + mode-toggle re-theme)

**Interfaces:** consumes Task 1 chart tokens (read via `getComputedStyle`); themes BOTH stock inline chart configs (other pages) and the Task 3 template.

- [ ] **Step 1: Upgrade the plugin** — line width 1.5, `tension .35`, points radius 0 / hoverRadius 3, area fill = canvas-height linear gradient from `--nz-chart-fill-top` to `--nz-chart-fill-bottom`, axis/grid colors from existing text tokens, legend off where the label is redundant (metrics cards carry their own labels).
- [ ] **Step 2: Render both modes; toggle theme in the harness shot page to confirm live re-theme.**
- [ ] **Step 3: Commit** `git commit -m "charts: gradient fills + instrument-grade axes from luminous tokens"`

### Task 6: Consistency pass

**Files:**
- Modify: `themes/clarity/assets/stylesheets/clarity/components.css`

- [ ] **Step 1:** primary buttons (`.formbutton-success`, `[data-submit-form]` primaries): `box-shadow: 0 0 0 1px var(--nz-glow-ring), 0 4px 18px var(--nz-glow-soft)` on hover/focus-visible only; active rail item marker gains the same ring; focus rings switch to `--nz-glow-ring`; modals + select2 dropdowns get `--nz-glass-edge` border. All transitions 150-200ms inside `prefers-reduced-motion: no-preference`.
- [ ] **Step 2:** Render `dark-form`, `dark-components`, `dark-sites` pages both modes — no layout shifts, effects within grammar.
- [ ] **Step 3: Commit** `git commit -m "components: glow-hover primaries, glass edges, luminous focus rings"`

### Task 7: Light-mode + responsive audit

- [ ] **Step 1:** Full `python3 mockup/build.py --shoot`; read every light-* shot + mobile variants; fix contrast escapes (WCAG AA on all new text: stat values, chip labels).
- [ ] **Step 2: Commit fixes** `git commit -m "luminous: light-mode + responsive audit fixes"`

### Task 8: Operator approval gate

- [ ] **Step 1:** Assemble before/after screenshots (login + dashboard, dark + light) into an artifact page; present to the operator.
- [ ] **Step 2:** STOP. Deploy only on explicit approval; iterate on feedback otherwise.

### Task 9: Release + live verification

- [ ] **Step 1:** Tag `v2.2.0`, push, deploy (`git fetch --tags && git checkout v2.2.0 && ./install.sh --copy` on the panel).
- [ ] **Step 2:** Live playwright screenshots (login pre-auth; dashboard via minted session, theme=clarity) both modes; compare against approved mockups.
- [ ] **Step 3:** Bump toolkit pin; update memory.
