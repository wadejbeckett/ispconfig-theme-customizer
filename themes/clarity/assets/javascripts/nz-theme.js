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
    /* the two labels are read off the button itself so they live in
       templates/main.tpl.htm, the one place a translator or a re-brander can
       reach them — this file cannot be localised any other way, since a theme
       has no lang directory ISPConfig will load and core exposes no language
       code to JS. English only if the template did not supply them. */
    b.setAttribute('aria-label',
      b.getAttribute(light ? 'data-nz-to-dark' : 'data-nz-to-light') ||
      (light ? 'Switch to dark theme' : 'Switch to light theme'));
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
  var STOCK_FILL = 'rgba(75, 192, 192, 0.2)';

  /* Which datasets the theme has taken over, remembered per dataset object.
     Needed because themeChartConfig() runs twice over the same config: once at
     beforeInit and again on every mode toggle. After the first pass
     ds.borderColor holds a RESOLVED hex (getComputedStyle substitutes the
     custom property), so a colour test alone would never match again and the
     stroke would keep the accent of the mode it was born in — #2EA9FF stranded
     on the white light-mode card is 2.55:1, and #0065AB stranded on the dark
     card is 2.15:1, both under the 3:1 non-text floor of WCAG 2.1 SC 1.4.11 —
     while the scriptable gradient fill beside it repainted correctly.
     A WeakSet rather than a marker property: Chart.js treats the dataset object
     itself as an option scope, so anything written onto it becomes resolvable
     as an option, and the entries die with the dataset when a dashlet rebuilds
     its chart. */
  var nzLine = new WeakSet();   /* stroke/point colour is ours */
  var nzFill = new WeakSet();   /* flat backgroundColor is ours too */

  /* v2.2 luminous: area fills are a vertical accent gradient mirroring the
     --nz-chart-fill-* tokens exactly — same ratios (28%→2% dark, 18%→2% light)
     AND same ramp steps (--nz-blue-400 dark, --nz-blue-500 light; NOT
     --nz-accent, which aliases a darker step in light mode). Canvas gradients
     can't consume color-mix() vars, so the color is re-derived from the ramp
     hex here; brand.php re-hues the ramp vars, so re-branding still propagates.
     Scriptable, so each chart rebuilds it for its own area. */
  function nzAreaGradient(ctx) {
    var chart = ctx.chart;
    var area = chart.chartArea;
    if (!area) return 'transparent';
    var light = mode() === 'light';
    var cs = getComputedStyle(document.body);
    var base = cs.getPropertyValue(light ? '--nz-blue-500' : '--nz-blue-400').trim()
            || chartPalette().accent;
    var g = chart.ctx.createLinearGradient(0, area.top, 0, area.bottom);
    g.addColorStop(0, hexAlpha(base, light ? 0.18 : 0.28));
    g.addColorStop(1, hexAlpha(base, 0.02));
    return g;
  }

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
      var isLine = (config.type === 'line') || ds.type === 'line';
      /* stock teal AND colorless datasets (the v2.2 metrics cards ship no
         colors on purpose) both take the accent voice — and keep taking it on
         every later pass, so a mode toggle repaints the stroke instead of
         leaving it on the accent of the previous mode (see nzLine above).
         A dataset that arrived with its own colour is never adopted and is
         still left alone. */
      if (nzLine.has(ds) || ds.borderColor === STOCK_LINE || ds.borderColor == null) {
        nzLine.add(ds);
        ds.borderColor = p.accent;
        ds.pointBackgroundColor = p.accent;
        if (ds.fill) {
          /* scriptable, so this one already re-derives itself per draw */
          ds.backgroundColor = nzAreaGradient;
        } else if (nzFill.has(ds) || ds.backgroundColor == null ||
                   ds.backgroundColor === STOCK_FILL) {
          nzFill.add(ds);
          /* flat fill is a plain rgba string, so it has to be re-derived here
             for the new mode exactly like the stroke */
          ds.backgroundColor = ds.fill == null ? p.fill : nzAreaGradient;
        }
      }
      if (isLine) {
        if (ds.borderWidth == null || ds.borderWidth === 1) ds.borderWidth = 1.5;
        if (ds.pointRadius == null) { ds.pointRadius = 0; ds.pointHoverRadius = 3; }
        /* invisible points need a generous hit area or tooltips become
           practically unreachable */
        if (ds.pointHitRadius == null) ds.pointHitRadius = 12;
        if (ds.tension == null) ds.tension = 0.35;
      }
    });
    /* hover anywhere on the x-axis shows the tooltip — with 0-radius points,
       the Chart.js default (nearest + intersect:true) demands pixel-perfect
       aim on an invisible dot */
    if (o.interaction == null) o.interaction = { mode: 'index', intersect: false };
    /* the canvas carries an inline white background in stock markup — strip it
       so the card behind shows through. Since v2.2 charts deliberately render
       FLAT on their card (gradient fills fading to transparent, no inset well);
       the pre-v2.2 well/border styling is intentionally not restored. */
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

  /* Localisation of strings this file injects is structurally limited, so the
     rule below is: if stock markup already carries a translated string for the
     thing we are naming, use that; only fall back to English where core offers
     none. A theme genuinely cannot do better than that — ISPConfig's lang
     loader only ever reads a module's own lib/lang/<lang>_*.lng (there is no
     theme fallback in it), so the theme cannot ship or shadow lang keys, and
     core hands JavaScript no language code either: main.tpl.htm exposes only a
     handful of pre-translated strings on ISPConfig.*, and even core's own
     datepicker init in ispconfig.js hardcodes 'language': 'en'.
     What that leaves in English on a non-English panel: the ICON_NAMES
     fallbacks below and the "Show all (n)"/"Show less" table cap, because core
     ships no translated equivalent anywhere in the DOM for either. */
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
      /* the stock list filter button is icon-only but keeps its caption in
         value= ({tmpl_var name="filter_txt"}), i.e. already in the session
         language — always prefer that over the English table */
      var name = b.tagName === 'BUTTON' ? (b.value || '').trim() : '';
      if (!name) {
        var cls = Array.prototype.find.call(i.classList, function (c) { return ICON_NAMES[c]; });
        name = cls ? ICON_NAMES[cls] : '';
      }
      if (name) {
        b.setAttribute('aria-label', name);
        if (!b.title) b.title = name;
      }
    });

    /* keyboard-reachable column sorting + sort state.
       tabindex only — deliberately NO role="button" here. role=button would
       replace the <th>'s implicit columnheader role, and aria-sort is not a
       supported state of role=button (ARIA 1.2), so the sort direction we set
       two lines down would be dropped from the accessibility tree; the browser
       would also stop mapping the header to its data cells, costing screen
       reader users the column name when they arrow through the ~80 stock list
       views. Enter/Space are handled by the document keydown listener further
       down, which is what actually supplies the button-like behaviour. */
    scope.querySelectorAll('th[data-column]').forEach(function (th) {
      if (!th.hasAttribute('tabindex')) {
        th.setAttribute('tabindex', '0');
      }
      var o = th.getAttribute('data-ordered');
      th.setAttribute('aria-sort', o ? (o === 'desc' ? 'descending' : 'ascending') : 'none');
    });

    /* collapsible tree groups: a caret per #sidebar section header */
    if (scope.id === 'sidebar') {
      scope.querySelectorAll('header').forEach(function (h) {
        var ul = h.nextElementSibling;
        if (!ul || ul.tagName !== 'UL' || h.querySelector('.nz-caret')) return;
        /* a header with no text is an empty section shell (e.g. the news
           panel with the feed switched off) — hide it, never caret it */
        if (!h.textContent.trim()) { h.style.display = 'none'; return; }
        var title = h.textContent.trim();
        var key = 'nz-tree:' + title;
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nz-caret';
        /* name the caret after its own section rather than with a generic
           "Toggle section": the header text comes from core's already
           translated menu, so this is announced in the panel's language
           ("Websites, collapsed, button"), and it identifies WHICH section
           the control belongs to on a sidebar full of identical carets */
        btn.setAttribute('aria-label', title);
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
      var expanded = false;
      var labelAll = 'Show all (' + rows.length + ')';
      b.textContent = labelAll;
      b.addEventListener('click', function () {
        expanded = !expanded;
        rows.slice(10).forEach(function (r) { r.style.display = expanded ? '' : 'none'; });
        b.textContent = expanded ? 'Show less' : labelAll;
        if (!expanded) tbl.scrollIntoView({ block: 'nearest' });
      });
      td.appendChild(b);
      tr.appendChild(td);
      tbl.tBodies[0].appendChild(tr);
    });

    /* filter inputs inherit their column header's name. The word for "filter"
       is lifted off the stock filter button sitting in the same header row —
       core fills its value= from {tmpl_var name="filter_txt"}, so it is in the
       session language, whereas a literal "Filter by " here would put English
       into the accessible name of every search box on a localised panel.
       Walking one <thead> at a time also keeps the header cell and the filter
       cell paired: a flat document-wide index pairs the second table's filter
       cells against the first table's headers on pages with two lists. */
    scope.querySelectorAll('thead').forEach(function (thead) {
      if (thead.rows.length < 2) return;
      var heads = thead.rows[0].cells;
      var fbtn = thead.querySelector('[name="Filter"]');
      var word = fbtn ? (fbtn.value || '').trim() : '';
      Array.prototype.forEach.call(thead.rows[1].cells, function (td, i) {
        var c = td.querySelector('input, select');
        var h = heads[i];
        if (!c || !h || c.getAttribute('aria-label') || !h.textContent.trim()) return;
        /* the filter button itself lives in one of these cells; it is named by
           the icon-button pass above, not here */
        if (c.tagName === 'INPUT' && /^(button|submit|reset|image)$/i.test(c.type)) return;
        var col = h.textContent.trim();
        c.setAttribute('aria-label', word ? word + ': ' + col : col);
      });
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
