/* the earlier name — dark/light switcher.
 * The boot snippet in <head> applies the stored preference before first
 * paint; this file only handles the toggle click. Tokens do the rest:
 * light mode is a pure alias remap on :root[data-nz-theme='light']. */
(function () {
  'use strict';
  var KEY = 'nz-theme';
  var root = document.documentElement;

  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('.nz-theme-toggle') : null;
    if (!btn) return;
    var next = root.getAttribute('data-nz-theme') === 'light' ? 'dark' : 'light';
    root.setAttribute('data-nz-theme', next);
    try { localStorage.setItem(KEY, next); } catch (err) { /* private mode */ }
  });
})();
