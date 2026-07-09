/* the earlier name — theme runtime.
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

  function themeCharts() {
    if (!window.Chart || !Chart.defaults) return;
    var cs = getComputedStyle(document.body);
    Chart.defaults.color = cs.getPropertyValue('--nz-text-secondary').trim() || '#CBD4D8';
    Chart.defaults.borderColor = mode() === 'light'
      ? 'rgba(79, 97, 105, 0.15)' : 'rgba(133, 147, 153, 0.15)';
    if (Chart.defaults.font) {
      Chart.defaults.font.family = cs.getPropertyValue('--nz-font').trim() || 'Inter, sans-serif';
      Chart.defaults.font.size = 11;
    }
    /* charts already on screen keep their colors until the next page load —
       acceptable: dashboards re-render on every navigation */
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
      if (cls) b.setAttribute('aria-label', ICON_NAMES[cls]);
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

  /* mark the current page in the sidebar tree */
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('#sidebar a[data-load-content]');
    if (!a) return;
    document.querySelectorAll('#sidebar a.nz-active').forEach(function (x) {
      x.classList.remove('nz-active');
    });
    a.classList.add('nz-active');
  });

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
