# Clarity v2.2 "Luminous" — signature visual redesign

Approved 2026-07-21. Direction chosen by the operator: **luminous depth**
aesthetic, **restructured dashboard** via theme-side dashlet overrides,
**horizon-beam login**. Professional audience (Plesk/cPanel-replacement);
distinctive but enterprise-calm.

## 1. The light system

A small grammar of new tokens in `tokens.css` defines where light may appear:

- `--nz-glow-*`: accent-derived glows built with `color-mix()` **from the
  existing `--nz-blue-*` ramp vars** — brand.php re-hues the ramp, so every
  glow follows a white-labeled accent automatically; the brand-token contract
  is untouched.
- `--nz-glass-*`: translucent surface fill + 1px inner light edge for elevated
  cards (backdrop-filter blur where supported, solid fallback).
- Gradient primitives for the login field and chart fills.

Rules (enforced, not vibes): glow appears ONLY on (a) the login horizon,
(b) primary-action hover, (c) the active rail marker, (d) chart area fills.
Never on text; never stacked. Elevation = translucency + light edge, not heavy
shadow. Motion: 150-200ms eases; `prefers-reduced-motion` collapses all of it.
Light mode expresses the same grammar with inverted luminosity (glow → tint,
edges → soft shadow).

## 2. Login — horizon beam

Pure CSS in `login.css` + the theme-owned `main_login.tpl.htm`; no images, no
new requests. A thin accent horizon line crosses the full-bleed dark field
behind a floating glass card; a soft bloom rises from the line; the wordmark
sits above the card. Form markup, field order, customizer footnote, credit
spans, and brand.php's logo-override selector (`.nzl-brand img`) are all
unchanged. All four auth pages (login, OTP, password reset, forced change)
inherit the composition through the shared frame. Responsive: beam and card
recompose at ≤480px (beam dims, card goes full-width flat).

## 3. Dashboard — restructured dashlets

Theme-side overrides under `themes/clarity/templates/dashboard/` (the verified
module-subdir override rule). Each override pins its stock dashlet's template
variable contract in `BUILT-AGAINST.txt`:

- **modules.htm** — the oversized tile grid becomes one compact launcher row
  of chips (icon + label + arrow), same loop variables.
- **metrics.htm** — the four stacked chart strips become a 2×2 grid of stat
  cards: label + headline numeral + unit on top, chart beneath with gradient
  area fill. Headline values are derived CLIENT-SIDE in `nz-theme.js` from
  each chart's latest data point — no server change, no invented data.
- **dashboard.htm** — the frame: proper header band (greeting, date, live
  stat chips fed from the same chart values) replacing the bare h1;
  info/warning/error loops and the atom sidebar contract unchanged.

Quota/limits/donate dashlets: CSS-level consistency only (no overrides).

## 4. Consistency pass

- nz-theme.js chart plugin: gradient area fills (accent-derived), thinner
  lines, unified axis type ramp, points on hover only, both modes.
- Primary buttons: glow on hover/focus-visible only.
- Cards, select2, modals, focus rings pick up the glass edge token.
- No layout changes outside the dashboard; module pages inherit via tokens.

## 5. Constraints

- NO core-file changes; stock theme unaffected; customizer/brand.php contract
  byte-identical; no new fonts, images, or HTTP requests.
- Every dashlet override documents its BUILT-AGAINST contract for upgrade
  checks.
- Process gate: mockup-harness renders (dark+light, 3 viewports) are reviewed
  and approved by the operator BEFORE deploy. Then v2.2.0 → live panel →
  live screenshot verification. Harness screenshot diffing guards regressions.

## 6. Testing

- Harness: deterministic screenshots of login, dashboard, form, components
  pages in both modes at 1440/1024/390 widths.
- Live: playwright against the production panel (minted-session cookie) after
  deploy.
- Final gate available: /code-review ultra on the release PR.
