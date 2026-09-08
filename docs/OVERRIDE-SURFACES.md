# Override surfaces

What a theme directory and a module directory can override in ISPConfig, and
what they cannot. Verified against 3.3.1p1 source. This is the reference behind
the extension's rule that no ISPConfig core file is modified: anything not
listed here as an override mechanism is either left stock or handled by an
upstream patch (see [UPSTREAM-PATCHES.md](UPSTREAM-PATCHES.md)).

## The envelope

Three places, and nothing else:

- theme directories, `themes/<name>/`;
- module directories, `interface/web/<module>/`;
- writes to existing database rows and columns — the `sys_ini` row 1,
  `sys_user.modules`, `sys_message`, `sys_config`.

The single documented exception is `$conf['theme']` in the two
`config.inc.php` files. That is a manual edit in both directions, never made by
`install.sh` or `uninstall.sh`.

## Verified override mechanisms

**Frame templates** — `main.tpl.htm`, `main_login.tpl.htm`, `topnav.tpl.htm`,
`error.tpl.htm`. Theme-flat override: a file of that name in
`themes/<name>/templates/` wins over the stock one. `clarity` owns the first
three as committed files. `classic` owns the first two as install-time
generated copies of the panel's own stock markup — deliberately not committed,
so they cannot drift from the ISPConfig version actually installed — and
inherits `topnav.tpl.htm` from stock unchanged. `error.tpl.htm` is stock in
both.

**Module content templates** — dashlets, login pages, help pages, tools. These
override at `themes/<name>/templates/<module>/<basename>.htm`, the module
subdirectory rule; flat placement never wins for them. `clarity` uses it for
four dashboard templates (`dashboard/dashboard.htm`, `dashboard/modules.htm`,
`dashboard/metrics.htm`, `dashboard/donate.htm`). With the three frame
templates that is **seven overridden templates in total**, each pinned in
[`themes/clarity/BUILT-AGAINST.txt`](../themes/clarity/BUILT-AGAINST.txt).
Re-diff them, and re-run `install.sh`, after any ISPConfig upgrade — patch
releases included.

**`sys_ini` config keys** — the Branding page's native channel. `[branding]`
belongs to this extension; core's only `[branding]` reference is dead commented
code. The `[misc]` and `[mail]` keys are stock and core-consumed. CI checks
that every key the page writes is read on the render side, so a design that
reads the same keys inherits the branding unchanged.

**`tmpl_phpinclude`** — enabled in core. A theme directory already runs PHP
(`brand.php`), so this is available for server-side branding inside theme
templates.

**Language strings cannot be shadowed.** The lang loader has no theme fallback,
so a string can only be changed by editing core. Lang-fed surfaces are
therefore CSS/JS-hide only, or they need an upstream patch.

**Custom dashlets are not possible** inside the envelope. Dashlet code loads
exclusively from core's `web/dashboard/dashlets/` directory. The dashboard
layout keys can only arrange what core ships.

## Role visibility

Only admins can see the Branding page. Module access is the `sys_user.modules`
CSV, checked at login and on every request; `install.sh` grants `customizer` to
`typ='admin'` users only.

Client and reseller creation builds module lists from
`$conf['interface_modules_enabled']`, which never contains `customizer`. The
remote API filters module grants against that same list, and the self-service
settings form cannot add modules. The only interface that could hand the module
to a non-admin is the admin-gated CP Users editor.

Each of the module's own endpoints is additionally guarded three times:
`check_module_permissions`, then `admin_allow_system_config` (shipped default
*superadmin*), then `is_admin`. Resellers and clients only ever see the result
of what an admin set.

One core behaviour worth knowing when auditing this: editing a client or
reseller whose `limit_client` has changed silently rewrites their module CSV
wholesale from the core default list.
