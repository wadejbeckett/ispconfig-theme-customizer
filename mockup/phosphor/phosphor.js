/* phosphor — mockup behaviour.
 *
 * Progressive enhancement only: every page below renders and reads correctly
 * with this file absent. In the shipping theme this is pz-theme.js (the chart
 * palette, drawer, search, a11y) plus the Branding page's own inline script;
 * the mockup keeps them in one file because it ships one file.
 */
(function () {
  'use strict';

  var css = getComputedStyle(document.documentElement);
  function tok(name, fallback) {
    var v = css.getPropertyValue(name);
    return (v && v.trim()) || fallback;
  }

  /* ------------------------------------------------------------------
     Charts. Themed DARK — no light "paper" island, which is the one place
     phosphor deliberately parts company with clarity's component voice.
     GLOW 4 of 4 lives here: the amber-to-transparent fill under the line.
     ------------------------------------------------------------------ */

  function accentRGB() {
    var hex = tok('--pz-accent', '#FFA301').replace('#', '');
    return [parseInt(hex.slice(0, 2), 16), parseInt(hex.slice(2, 4), 16), parseInt(hex.slice(4, 6), 16)];
  }

  function fmt(n) {
    n = Number(n);
    if (!isFinite(n)) return '—';
    return Math.abs(n) >= 100 ? String(Math.round(n)) : String(Math.round(n * 10) / 10);
  }

  var CHIP_NAMES = { loadchart: 'Load', memchart: 'Memory', rxchart: 'Net in', txchart: 'Net out' };

  window.createChart = function (chartname, label, labels, data) {
    var el = document.getElementById(chartname);
    if (!el || typeof Chart === 'undefined') return;

    var last = data.length ? data[data.length - 1] : null;
    var v = document.getElementById('pz-stat-' + chartname);
    if (v && last !== null) v.textContent = fmt(last);

    var chips = document.getElementById('pz-dash-chips');
    if (chips && last !== null && CHIP_NAMES[chartname]) {
      var chip = document.createElement('span');
      chip.className = 'pz-chip';
      var k = document.createElement('span');
      k.className = 'pz-chip-k';
      k.textContent = CHIP_NAMES[chartname];
      chip.appendChild(k);
      chip.appendChild(document.createTextNode(fmt(last)));
      chips.appendChild(chip);
    }

    var ctx = el.getContext('2d');
    var rgb = accentRGB();
    var grad = ctx.createLinearGradient(0, 0, 0, 64);
    grad.addColorStop(0, 'rgba(' + rgb.join(',') + ',0.34)');
    grad.addColorStop(1, 'rgba(' + rgb.join(',') + ',0)');

    var grid = 'rgba(255,255,255,0.06)';
    var tick = tok('--pz-ink-muted', '#9AA0A0');
    var mono = tok('--pz-mono', 'monospace').split(',')[0].replace(/['"]/g, '').trim();

    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: label,
          data: data,
          borderColor: tok('--pz-accent', '#FFA301'),
          backgroundColor: grad,
          borderWidth: 1.5,
          tension: 0.35,
          pointRadius: 0,
          pointHoverRadius: 3,
          fill: true
        }]
      },
      options: {
        maintainAspectRatio: false,
        animation: false,
        scales: {
          x: { display: false },
          y: {
            beginAtZero: true,
            border: { display: false },
            grid: { color: grid, drawTicks: false },
            ticks: {
              maxTicksLimit: 3,
              color: tick,
              font: { family: mono, size: 10 },
              padding: 6
            }
          }
        },
        plugins: { legend: { display: false }, tooltip: { enabled: false } }
      }
    });
  };

  /* ------------------------------------------------------------------
     Branding page.
     ------------------------------------------------------------------ */

  /* WCAG relative luminance and contrast — the same measured-contrast rule
     the PHP reader uses. Nothing here GUESSES a logo variant: variant
     resolution stays in PHP (three copies CI proves agree) and the shipping
     page asks customizer/preview.php for that fragment instead. */
  function srgb(c) { c /= 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); }
  function lum(hex) {
    hex = hex.replace('#', '');
    return 0.2126 * srgb(parseInt(hex.slice(0, 2), 16)) +
           0.7152 * srgb(parseInt(hex.slice(2, 4), 16)) +
           0.0722 * srgb(parseInt(hex.slice(4, 6), 16));
  }
  function ratio(a, b) {
    var la = lum(a), lb = lum(b);
    return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
  }
  /* Compare the white and black ratios rather than pivoting on lightness —
     the algorithm themes/clarity/brand.php:859 already ships. */
  function inkFor(bg) {
    return ratio(bg, '#FFFFFF') >= ratio(bg, '#000000') ? '#FFFFFF' : '#06080A';
  }

  var HEX = /^#[0-9A-Fa-f]{6}$/;

  function syncHex(txtId, pickId, onChange) {
    var t = document.getElementById(txtId), p = document.getElementById(pickId);
    if (!t || !p) return;
    if (HEX.test(t.value)) p.value = t.value;
    /* 'input' alone is not enough: Firefox's native colour dialog fires only
       'change' when it closes, which is how picker-chosen colours used to
       never reach the named text field. */
    function fromPick() { t.value = p.value.toUpperCase(); if (onChange) onChange(); }
    p.addEventListener('input', fromPick);
    p.addEventListener('change', fromPick);
    t.addEventListener('input', function () {
      if (HEX.test(t.value)) p.value = t.value;
      if (onChange) onChange();
    });
    t.addEventListener('blur', function () {
      var v = t.value.trim();
      if (/^[0-9A-Fa-f]{6}$/.test(v)) v = '#' + v;
      if (HEX.test(v)) { v = v.toUpperCase(); t.value = v; p.value = v; }
      if (onChange) onChange();
    });
  }

  function val(id, fallback) {
    var el = document.getElementById(id);
    if (!el) return fallback;
    var v = (el.value || '').trim();
    return HEX.test(v) ? v : fallback;
  }

  function paintPreview() {
    var rail = val('rail_hex', tok('--pz-band', '#0B0E12'));
    var accent = val('accent_hex', tok('--pz-accent', '#FFA301'));
    var login = val('login_bg', tok('--pz-ground', '#06080A'));
    var nameEl = document.getElementById('company_name');
    var name = nameEl && nameEl.value.trim() ? nameEl.value.trim() : 'ISPConfig';

    var ink = inkFor(rail);
    var railBox = document.querySelector('.pz-prevnav-rail');
    if (railBox) {
      railBox.style.background = rail;
      railBox.style.color = ink;
    }
    document.querySelectorAll('.pz-prev-accent').forEach(function (el) {
      el.style.background = accent;
      el.style.color = inkFor(accent);
    });
    document.querySelectorAll('.pz-prev-accent-rule').forEach(function (el) {
      el.style.setProperty('--pz-accent', accent);
    });
    var loginBox = document.querySelector('.pz-prevlogin');
    if (loginBox) {
      loginBox.style.background = login;
      loginBox.style.color = inkFor(login);
    }
    document.querySelectorAll('.pz-prev-name').forEach(function (el) { el.textContent = name; });
    var ratioOut = document.getElementById('pz-rail-ratio');
    if (ratioOut) ratioOut.textContent = ratio(rail, ink).toFixed(2) + ':1';
  }

  function initBranding() {
    if (!document.querySelector('.pz-brandgrid')) return;
    syncHex('accent_hex', 'accent_hex_pick', paintPreview);
    syncHex('rail_hex', 'rail_hex_pick', paintPreview);
    syncHex('rail_hex_light', 'rail_hex_light_pick', paintPreview);
    syncHex('login_bg', 'login_bg_pick', paintPreview);
    var n = document.getElementById('company_name');
    if (n) n.addEventListener('input', paintPreview);
    paintPreview();
  }

  /* ------------------------------------------------------------------
     Drawer + accordion. Both keep aria state current; both are optional.
     ------------------------------------------------------------------ */

  function initDrawer() {
    var btn = document.querySelector('.menu-btn');
    var drawer = document.getElementById('pz-drawer');
    if (!btn || !drawer) return;
    btn.addEventListener('click', function () {
      var open = drawer.classList.toggle('is-open');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && drawer.classList.contains('is-open')) {
        drawer.classList.remove('is-open');
        btn.setAttribute('aria-expanded', 'false');
        btn.focus();
      }
    });
  }

  function initAccordion() {
    document.querySelectorAll('.panel-title > a[data-toggle="collapse"]').forEach(function (a) {
      a.addEventListener('click', function (e) {
        e.preventDefault();
        var target = document.querySelector(a.getAttribute('href'));
        if (!target) return;
        var open = target.classList.toggle('in');
        a.classList.toggle('collapsed', !open);
        a.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initBranding();
    initDrawer();
    initAccordion();
  });
})();
