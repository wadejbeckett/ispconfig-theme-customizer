/* Clarity Theme for ISPConfig — theme runtime.
 *
 * Progressive enhancement only: everything here layers on top of stock
 * ISPConfig behavior and touches no core file. Sections:
 *   1. dark/light switcher (data-nz-theme on <html>, persisted)
 *   2. Chart.js theming from the design tokens
 *   3. mobile drawer: close on navigate / Escape, aria-expanded
 *   4. global search: Ctrl/Cmd+K and '/' focus, Escape dismiss
 *   5. AJAX activity bar + motion preferences
 *   6. a11y + orientation enhancement of AJAX-loaded stock markup
 *      (icon-button names, keyboard sorting, filter labels, active
 *      tree item) — re-applied on every content load via observers.
 */
(function () {
  'use strict';
  var KEY = 'nz-theme';
  var root = document.documentElement;

  /* ---------- 1. dark/light switcher ---------- */

  function mode() {
    return root.getAttribute('data-nz-theme') === 'light' ? 'light' : 'dark';
  }

  function syncToggle() {
    var b = document.querySelector('.nz-theme-toggle');
    if (!b) return;
    var light = mode() === 'light';
    b.setAttribute('aria-pressed', light ? 'true' : 'false');
    b.setAttribute('aria-label', light ? 'Switch to dark theme' : 'Switch to light theme');
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.nz-theme-toggle');
    if (!btn) return;
    var next = mode() === 'light' ? 'dark' : 'light';
    root.setAttribute('data-nz-theme', next);
    try { localStorage.setItem(KEY, next); } catch (err) { /* private mode */ }
    syncToggle();
    themeCharts();
  });

  /* ---------- 2. Chart.js follows the tokens ---------- */

  function hexAlpha(hex, a) {
    var m = /^#?([0-9a-f]{6})$/i.exec(hex.trim());
    if (!m) return 'rgba(46, 169, 255, ' + a + ')';
    var n = parseInt(m[1], 16);
    return 'rgba(' + (n >> 16) + ', ' + ((n >> 8) & 255) + ', ' + (n & 255) + ', ' + a + ')';
  }

  function chartPalette() {
    var cs = getComputedStyle(document.body);
    var accent = cs.getPropertyValue('--nz-accent').trim() || '#2EA9FF';
    return {
      text: cs.getPropertyValue('--nz-text-secondary').trim() || '#CBD4D8',
      grid: mode() === 'light'
        ? 'rgba(79, 97, 105, 0.15)' : 'rgba(133, 147, 153, 0.15)',
      accent: accent,
      fill: hexAlpha(accent, 0.16),
      font: cs.getPropertyValue('--nz-font').trim() || 'Inter, sans-serif'
    };
  }

  /* Stock dashlet charts hardcode their colors inline (metrics.htm), so
     global Chart.defaults can't reach them: a teal line, and a legend swatch
     painted WHITE to hide it against stock's white canvas — on our dark
     canvas that hack becomes a floating white square. Retheme each chart's
     config as it is created (plugin), and again on mode toggle. */
  var STOCK_LINE = 'rgb(75, 192, 192)';

  function themeChartConfig(config, canvas) {
    var p = chartPalette();
    var o = config.options = config.options || {};
    o.color = p.text;
    Object.keys(o.scales || {}).forEach(function (k) {
      var s = o.scales[k] || (o.scales[k] = {});
      s.ticks = Object.assign({}, s.ticks, { color: p.text });
      s.grid = Object.assign({}, s.grid, { color: p.grid });
      s.border = Object.assign({}, s.border, { color: p.grid });
    });
    var labels = o.plugins && o.plugins.legend && o.plugins.legend.labels;
    if (labels && labels.generateLabels) {   /* the white-swatch hack */
      delete labels.generateLabels;
      labels.boxWidth = 0;
      labels.boxHeight = 0;
    }
    if (labels) labels.color = p.text;
    ((config.data && config.data.datasets) || []).forEach(function (ds) {
      if (ds.borderColor === STOCK_LINE) {
        ds.borderColor = p.accent;
        ds.backgroundColor = p.fill;
        ds.pointBackgroundColor = p.accent;
      }
    });
    /* the canvas carries an inline white background in stock markup; the
       stylesheet well wins anyway, but don't leave it lying around */
    if (canvas && canvas.style.backgroundColor) canvas.style.backgroundColor = '';
  }

  function themeCharts() {
    if (!window.Chart || !Chart.defaults) return;
    var p = chartPalette();
    /* stock dashlets ship 30px-tall canvases; the theme gives each chart
       wrapper a real height and lets the chart fill it */
    Chart.defaults.maintainAspectRatio = false;
    Chart.defaults.color = p.text;
    Chart.defaults.borderColor = p.grid;
    if (Chart.defaults.font) {
      Chart.defaults.font.family = p.font;
      Chart.defaults.font.size = 11;
    }
    if (!themeCharts.plugged && Chart.register) {
      Chart.register({
        id: 'nzTheme',
        beforeInit: function (chart) { themeChartConfig(chart.config, chart.canvas); }
      });
      themeCharts.plugged = true;
    }
    /* charts already on screen follow a mode toggle immediately */
    if (Chart.instances) {
      Object.keys(Chart.instances).forEach(function (k) {
        var c = Chart.instances[k];
        if (!c || !c.config) return;
        try {
          themeChartConfig(c.config, c.canvas);
          c.update('none');
        } catch (e) { /* a chart mid-teardown is not ours to update */ }
      });
    }
  }

  /* ---------- 3. mobile drawer ---------- */

  function drawerOpen() {
    return document.body.classList.contains('pushy-active');
  }

  function closeDrawer() {
    /* reuse pushy's own overlay handler (bound at DOM-ready, element is
       static) so its internal toggling stays consistent */
    var o = document.querySelector('.site-overlay');
    if (o && window.jQuery) { jQuery(o).trigger('click'); }
  }

  document.addEventListener('click', function (e) {
    if (drawerOpen() && e.target.closest && e.target.closest('.pushy a')) {
      setTimeout(closeDrawer, 0);
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && drawerOpen()) closeDrawer();
  });

  new MutationObserver(function () {
    var b = document.querySelector('.menu-btn');
    if (b) b.setAttribute('aria-expanded', drawerOpen() ? 'true' : 'false');
  }).observe(document.body, { attributes: true, attributeFilter: ['class'] });

  /* ---------- 4. global search shortcuts ---------- */

  document.addEventListener('keydown', function (e) {
    var s = document.getElementById('globalsearch');
    if (!s) return;
    var el = document.activeElement;
    var typing = el && (/^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName) || el.isContentEditable);
    if (((e.ctrlKey || e.metaKey) && !e.shiftKey && !e.altKey && e.key.toLowerCase() === 'k') ||
        (!typing && !e.ctrlKey && !e.metaKey && !e.altKey && e.key === '/')) {
      e.preventDefault();
      s.focus();
      s.select();
    }
    if (e.key === 'Escape' && el === s) {
      s.blur();
      var r = document.getElementById('globalsearch-resultbox');
      if (r) r.style.display = 'none';
    }
  });

  /* ---------- 5. activity bar + motion preferences ---------- */

  if (window.jQuery) {
    jQuery(document)
      .ajaxStart(function () { root.classList.add('nz-loading'); })
      .ajaxStop(function () { root.classList.remove('nz-loading'); });

    if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) {
      jQuery.fx.off = true;        /* instant scrolls/fades for reduced-motion users */
    } else if (jQuery.fx && jQuery.fx.speeds) {
      jQuery.fx.speeds._default = 150;  /* snappier default animations */
    }
  }

  /* ---------- 6. content enhancement (stock markup, AJAX-loaded) ---------- */

  var ICON_NAMES = {
    'icon-delete': 'Delete',
    'icon-edit': 'Edit',
    'icon-filter': 'Apply filter',
    'icon-loginas': 'Log in as this user',
    'icon-link': 'Open website',
    'icon-lens': 'Search',
    'icon-calendar': 'Pick a date',
    'icon-dbadmin': 'Open database admin',
    'glyphicon-signal': 'Statistics',
    'glyphicon-remove-circle': 'Remove',
    'fa-clone': 'Copy'
  };

  function enhance(scope) {
    /* names for icon-only controls */
    scope.querySelectorAll('a.btn, button.btn').forEach(function (b) {
      if (b.getAttribute('aria-label') || b.textContent.trim()) return;
      var i = b.querySelector('[class*="icon-"], [class*="glyphicon-"], [class*="fa-"]');
      if (!i) return;
      var cls = Array.prototype.find.call(i.classList, function (c) { return ICON_NAMES[c]; });
      if (cls) {
        b.setAttribute('aria-label', ICON_NAMES[cls]);
        if (!b.title) b.title = ICON_NAMES[cls];
      }
    });

    /* keyboard-reachable column sorting + sort state */
    scope.querySelectorAll('th[data-column]').forEach(function (th) {
      if (!th.hasAttribute('tabindex')) {
        th.setAttribute('tabindex', '0');
        th.setAttribute('role', 'button');
      }
      var o = th.getAttribute('data-ordered');
      th.setAttribute('aria-sort', o ? (o === 'desc' ? 'descending' : 'ascending') : 'none');
    });

    /* collapsible tree groups: a caret per #sidebar section header */
    if (scope.id === 'sidebar') {
      scope.querySelectorAll('header').forEach(function (h) {
        var ul = h.nextElementSibling;
        if (!ul || ul.tagName !== 'UL' || h.querySelector('.nz-caret')) return;
        var key = 'nz-tree:' + h.textContent.trim();
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nz-caret';
        btn.setAttribute('aria-label', 'Toggle section');
        var collapsed = false;
        try { collapsed = localStorage.getItem(key) === '1'; } catch (e) {}
        ul.classList.toggle('nz-collapsed', collapsed);
        btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        btn.addEventListener('click', function () {
          var now = !ul.classList.contains('nz-collapsed');
          ul.classList.toggle('nz-collapsed', now);
          btn.setAttribute('aria-expanded', now ? 'false' : 'true');
          try { localStorage.setItem(key, now ? '1' : '0'); } catch (e) {}
        });
        h.appendChild(btn);
      });
    }

    /* long dashboard dashlet tables (no sortable header = not a list view)
       collapse to 10 rows behind a "show all" toggle */
    scope.querySelectorAll('table.table').forEach(function (tbl) {
      if (tbl.dataset.nzCapped || tbl.querySelector('th[data-column]')) return;
      var rows = tbl.tBodies[0] ? Array.prototype.slice.call(tbl.tBodies[0].rows) : [];
      if (rows.length <= 12) return;
      tbl.dataset.nzCapped = '1';
      rows.slice(10).forEach(function (r) { r.style.display = 'none'; });
      var tr = document.createElement('tr');
      var td = document.createElement('td');
      td.colSpan = rows[0].cells.length;
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'btn btn-default';
      b.textContent = 'Show all (' + rows.length + ')';
      b.addEventListener('click', function () {
        rows.forEach(function (r) { r.style.display = ''; });
        tr.remove();
      });
      td.appendChild(b);
      tr.appendChild(td);
      tbl.tBodies[0].appendChild(tr);
    });

    /* filter inputs inherit their column header's name */
    var heads = scope.querySelectorAll('thead tr:first-child th');
    scope.querySelectorAll('thead tr:nth-child(2) td').forEach(function (td, i) {
      var c = td.querySelector('input, select');
      var h = heads[i];
      if (c && h && !c.getAttribute('aria-label') && h.textContent.trim()) {
        c.setAttribute('aria-label', 'Filter by ' + h.textContent.trim());
      }
    });
  }

  document.addEventListener('keydown', function (e) {
    if ((e.key === 'Enter' || e.key === ' ') &&
        e.target.matches && e.target.matches('th[data-column]')) {
      e.preventDefault();
      e.target.click();
    }
  });

  /* copy-to-clipboard cells: flash the icon as confirmation (the copying
     itself is stock behavior, bound to clicks on the cell outside its link) */
  document.addEventListener('click', function (e) {
    var td = e.target.closest && e.target.closest('.copy-to-clipboard');
    if (!td || (e.target.closest && e.target.closest('a'))) return;
    td.classList.add('nz-copied');
    setTimeout(function () { td.classList.remove('nz-copied'); }, 1200);
  });

  /* mark the current page in the sidebar tree */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('#sidebar a[data-load-content]');
    if (!a) return;
    document.querySelectorAll('#sidebar a.nz-active').forEach(function (x) {
      x.classList.remove('nz-active');
    });
    a.classList.add('nz-active');
  });

  /* the stock drawer builder nests the tree/news under the active module,
     pushing the other modules below — reorder to match desktop */
  if (window.ISPConfig && ISPConfig.loadPushyMenu) {
    var origPushy = ISPConfig.loadPushyMenu;
    ISPConfig.loadPushyMenu = function () {
      origPushy.apply(this, arguments);
      var nav = document.querySelector('nav.pushy');
      var sub = nav && nav.querySelector('ul.subnavi');
      if (nav && sub) nav.appendChild(sub);
    };
  }

  function watch(id) {
    var el = document.getElementById(id);
    if (!el) return;
    enhance(el);
    new MutationObserver(function () { enhance(el); })
      .observe(el, { childList: true });
  }

  function boot() {
    watch('pageContent');
    watch('sidebar');
    themeCharts();
    syncToggle();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
