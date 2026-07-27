# Upgrading

Two different upgrades are covered here, and they have almost nothing to do with
each other:

- **ISPConfig upgrades.** The panel moves; this project has to be re-stamped
  against it. This is the section that matters, and it applies to *every*
  ISPConfig release including patch releases.
- **Upgrades of this project.** Pull a new tag and re-run the installer.
  Includes moving off the old split installation (two separate repositories),
  which merged into this one at v3.0.0.

Neither component modifies an ISPConfig core file, so neither is undone by an
ISPConfig upgrade in the way a patched core file would be. What *is* affected is
one small piece of metadata the panel uses to decide whether a third-party theme
is allowed to load.

## What survives an ISPConfig upgrade

**Your branding data survives.** Everything the Branding module manages lives in
ISPConfig's own storage: the `[branding]` section it adds to the `sys_ini.config`
blob, the stock `[misc]` keys of *System > Interface Config*, and the native
`sys_ini.custom_logo` column. It adds no tables and no columns. An ISPConfig
upgrade migrates that row, it does not reset it, so your logo, panel name,
accent colour and login text come back on the other side.

**The deployed directories normally survive.** The ISPConfig updater installs the
interface by overlaying its own tree onto yours — `cp -rf ../interface
$install_dir` in `install/lib/installer_base.lib.php`. It is a copy, not a sync:
paths that exist only in your install are absent from the source tree, so they
are not touched. The updater's separate cleanup routine deletes an explicit,
hardcoded list of obsolete *core* paths (`interface/web/domain`,
`interface/web/designer`, `themes/default-304`, the old `language_*` files) and
nothing else. Neither `interface/web/themes/clarity` nor
`interface/web/customizer` is on that list.

**`$conf['theme']` survives, if you set it in both files.** The updater reads the
old configuration from `server/lib/config.inc.php` (`install/update.php` line
111) and carries `$conf['theme']` forward from there into the configs it
regenerates. Set it only in `interface/lib/config.inc.php` and the value is lost
at the next upgrade, silently — the login screen reverts to the stock theme with
no error. Setting it in both files is a documented manual step; the installers
never edit ISPConfig configuration.

## What you must redo after an ISPConfig upgrade

Re-run the installer. After **any** ISPConfig upgrade, including a patch release
such as 3.3.1p1 to 3.3.1p2:

```bash
cd /opt/ispconfig-theme-customizer      # your clone
git fetch --tags && git checkout <tag>  # only if you also want newer code
sudo ./install.sh                       # re-stamps the version gate
```

If your original install used `--copy`, re-run it **with `--copy` again**. The
installer removes whatever is at the destination and redeploys in the mode you
ask for, so a bare re-run would convert a copied install into a symlink pointing
at the clone.

### Why, exactly

ISPConfig gates third-party themes on an **exact string match** against
`ISPC_APP_VERSION`, which changes on every release — patch releases included.
Eight places in core read it, under two different filenames:

| Core file | Reads | What a mismatch does there |
|---|---|---|
| `interface/web/login/index.php` | `ispconfig_version` | at login, forces the session theme to `default` and raises `theme_not_compatible` |
| `interface/web/tools/user_settings.php` | `ispconfig_version` | the save handler behind *Design*: silently rewrites the submitted theme to `default` |
| `interface/web/tools/form/user_settings.tform.php` | `ispconfig_version` | drops the theme from *Tools > User Settings > Design* |
| `interface/web/admin/form/users.tform.php` | `ispconfig_version` | drops it from the theme picker on a CP user |
| `interface/web/client/form/client.tform.php` | `ispconfig_version` | drops it from the client theme picker |
| `interface/web/client/form/reseller.tform.php` | `ispconfig_version` | drops it from the reseller theme picker |
| `interface/web/tools/form/tpl_default.tform.php` | `ISPC_VERSION` | drops it from *Tools > Default user settings* |
| `interface/web/client/form/client_circle.tform.php` | `ISPC_VERSION` | drops it from the client-circle theme picker |

The two filenames do not behave the same way when the file is *absent*.
`login/index.php` requires `ispconfig_version` to exist AND match, so a theme
without it is rejected at login. The other five `ispconfig_version` sites treat a
missing file as "not gated" and leave the theme listed. Both `ISPC_VERSION` sites
require a present, matching file, so a theme with no `ISPC_VERSION` never appears
in those two pickers at all.

The practical consequence is the same either way: run `install.sh`, which stamps
both files, rather than reasoning about which screen tolerates what.

`install.sh` writes both filenames into `interface/web/themes/clarity/`, using
the value it reads out of your `interface/lib/config.inc.php`.

Skip the re-stamp and the failure is quiet. At login, core compares the stamp to
`ISPC_APP_VERSION`; on a mismatch it sets the session theme to `default` and
raises the `theme_not_compatible` message. Every user lands on a stock-looking
panel, and the theme disappears from the Design dropdown so it cannot simply be
re-selected. Nothing in the panel says "your theme is out of date by one patch
level".

The recovery is cheap, which is worth knowing before you go looking for damage:
core changes only the **session**, never the stored `sys_user.app_theme` column.
Re-run `install.sh` to write the new stamp, then log out and back in. The stored
preference is still `clarity`, so the theme comes straight back — there is no
database repair to do.

If the installer cannot find `ISPC_APP_VERSION` in your config it removes any
stale stamp rather than leaving a wrong one in place, prints a warning, and gives
you the two `printf` commands to write the files by hand. A stamp from a previous
panel version is worse than no stamp at all: it makes the theme look selectable
and then resets everyone at login.

### The Branding module needs no re-stamp

The module is not version-gated and changes nothing in core, so an ISPConfig
upgrade needs no action for it. Two things are still worth checking:

- If an upgrade ever did remove `interface/web/customizer`, re-run
  `./install.sh --module`.
- Module assignment is per user. Admin accounts created *after* the install do
  not have it. Re-run `./install.sh --module` (it re-runs the assignment) or tick
  `customizer` under *System > CP Users > edit the user > Modules*. In the
  navigation the module is labelled **Branding**; `customizer` is only the
  directory name.

## Checking the overridden templates

The theme overrides **six** stock templates. Three are the application shell,
three are dashboard dashlets:

| Override | Replaces |
|---|---|
| `templates/main.tpl.htm` | the app frame |
| `templates/main_login.tpl.htm` | the login scene |
| `templates/topnav.tpl.htm` | the module navigation |
| `templates/dashboard/dashboard.htm` | `interface/web/dashboard/templates/dashboard.htm` |
| `templates/dashboard/modules.htm` | `interface/web/dashboard/dashlets/templates/modules.htm` |
| `templates/dashboard/metrics.htm` | `interface/web/dashboard/dashlets/templates/metrics.htm` |

Overriding works because ISPConfig's template engine searches
`themes/<theme>/templates` before falling back to `themes/default/templates`
(`interface/lib/classes/tpl_ini.inc.php`: `INCLUDE_PATHS`, then `TEMPLATE_DIR`),
with a module-scoped lookup at `themes/<theme>/templates/<module>/<file>` taking
priority over both (`interface/lib/classes/tpl.inc.php`, `_fileSearch`). Nothing
in core is patched, and any template the theme does not provide falls through to
stock.

`themes/clarity/BUILT-AGAINST.txt` pins all six against the exact stock contract
each one consumes: template variables, loop names, the `data-capp` click contract
on the module tiles, the four canvas ids the metrics charts key on, and the JS
hooks the stock shell scripts (`pushy.js`, `ispconfig.js`, `responsive.js`)
expect to find in the frame. It records that these were verified against
ISPConfig 3.3.1p1.

A changed stock template does not raise an error — it renders an override whose
variables no longer resolve, so a dashlet comes up empty or stale. Patch releases
have not changed these in practice; a major release can. After a major upgrade,
diff the new stock templates against the overrides:

```bash
cd /usr/local/ispconfig/interface/web
diff themes/default/templates/main.tpl.htm       themes/clarity/templates/main.tpl.htm
diff themes/default/templates/main_login.tpl.htm themes/clarity/templates/main_login.tpl.htm
diff themes/default/templates/topnav.tpl.htm     themes/clarity/templates/topnav.tpl.htm
diff dashboard/templates/dashboard.htm           themes/clarity/templates/dashboard/dashboard.htm
diff dashboard/dashlets/templates/modules.htm    themes/clarity/templates/dashboard/modules.htm
diff dashboard/dashlets/templates/metrics.htm    themes/clarity/templates/dashboard/metrics.htm
```

These overrides are full rewrites, so the diffs are large — a clean diff is not
the goal. What you are looking for is any `{tmpl_var name='...'}` or
`{tmpl_loop name='...'}` that is present in the new stock file and missing from
the override, and any change to the ids and `data-` attributes listed in
`BUILT-AGAINST.txt`.

For a cleaner signal, diff stock against stock: unpack the ISPConfig source
tarball for the version you upgraded *from* and diff its copy of each template
against the one now installed. That shows exactly what changed upstream.
(Earlier versions of this document pointed at a `.refs/ispconfig3/` directory.
That is a local development checkout — gitignored and never shipped — so it will
not exist in your clone.)

If a contract did change, fix the override and update the pin in
`BUILT-AGAINST.txt`. CI checks that every override is *documented*; it has no way
to know a future stock template changed, so this check is manual.

As a fallback, deleting `themes/clarity/templates/dashboard/` restores the stock
dashlets, which still pick up the theme's CSS. The three shell templates cannot
be dropped this way — the theme's layout is built on them.

## Re-applying the web-server snippet

The version-gate files described above sit inside the panel's web root, so the
web server serves them as ordinary static files:

```bash
curl -sk -o /dev/null -w '%{http_code}\n' https://panel.example.com:8080/themes/clarity/ispconfig_version
```

An unauthenticated `200` there returns your exact ISPConfig version and patch
level. This is not stock behaviour — the stock `default` theme ships no version
file and returns 404 (tested) — so the exposure arrives with any third-party
theme, this one included. It also undercuts the module's own "hide the ISPConfig
version" toggle, which only hides the version on the Help page.
`contrib/webserver/` carries nginx and Apache snippets and the full explanation.

The ISPConfig updater can regenerate the panel vhost when you let it reconfigure
services, which drops the snippet. Re-check it after an upgrade, at the same time
you re-stamp:

- `403` or `404` — the rule is live.
- `200` — the rule is gone. Re-apply the snippet from `contrib/webserver/` and
  reload the web server.

The deny rule only affects HTTP requests. ISPConfig reads those files from disk,
so the theme keeps working. If the theme *does* revert after you apply the
snippet, the stamp is missing or stale — that is the re-stamp above, not the
snippet.

## Upgrading from the old split installation

Before v3.0.0 this shipped as two repositories — `clarity-theme-ispconfig` and
`ispconfig-customizer` — with independent version numbers. That is the reason for
the merge: `themes/clarity/brand.php` reads exactly the `sys_ini` keys
`customizer_edit.php` writes, one contract split across two release cycles with
nothing enforcing that they matched. Module v1.0.12 against theme v2.1.0 left the
accent colour silently unapplied, with no error anywhere. Both components now
share one version number and one tag.

There is no migration to perform. Your branding data is untouched — it was always
in ISPConfig's own `sys_ini` row, never in either repository — and the new
installer deploys to the same two destinations the old ones did, so installing
from here replaces what they deployed.

```bash
cd /opt
git clone https://github.com/wadejbeckett/ispconfig-theme-customizer.git
cd ispconfig-theme-customizer
sudo ./install.sh                       # both components; add --copy if that is how you had it
```

Then verify: log out and back in, confirm the theme renders, and confirm
*Branding* still shows your logo, colours and panel name.

Once you have verified it, **remove the old clones.** They were typically at
`/root/clarity-theme` and `/root/ispconfig-customizer`. `install.sh` looks for
those two paths (plus `/opt/clarity-theme-ispconfig` and
`/opt/ispconfig-customizer`) and prints a note if it finds one, but it will not
delete anything outside its own destinations — that is your call, not the
installer's. The risk is concrete: those clones still contain working installers
that deploy older code to the same destinations. Anyone who runs one afterwards —
or any cron job or configuration-management run still referencing them — silently
overwrites this install with pre-merge code.

```bash
rm -rf /root/clarity-theme /root/ispconfig-customizer
```

Two related notes:

- **There are no submodules.** The old `ispconfig-toolkit` umbrella repository
  pinned both projects as git submodules. It is archived read-only alongside the
  two component repositories. Do not clone with `--recurse-submodules`; there is
  nothing to recurse into.
- **Symlink installs stamped into the clone.** If the old theme install was a
  symlink, `ispconfig_version` and `ISPC_VERSION` were written *through* the link
  into the old clone. Deleting the clone removes them along with everything else.
  The new install writes its own.

## Upgrading this project

```bash
cd /opt/ispconfig-theme-customizer
git fetch --tags
git checkout v3.0.0                     # or whichever tag you are moving to
sudo ./install.sh                       # add --copy if that is how you installed
```

With a symlink install the panel reads the theme from the clone, so a tag
checkout is live immediately — re-run `install.sh` anyway. It re-stamps the gate
and re-runs module assignment, and for a `--copy` install it is the step that
deploys the new files at all. Hard-refresh the browser (`Ctrl+Shift+R`) so the
new CSS is picked up.

You can upgrade one component at a time with `--theme` or `--module`. With no
component flag, `install.sh` does both.

## Uninstalling

`uninstall.sh` does not "restore stock" by default, and it is worth being exact
about what each flag does. Nothing here edits an ISPConfig core file or
configuration.

```bash
sudo ./uninstall.sh                     # both components, conservative defaults
```

| What | Default | Flag |
|---|---|---|
| Remove `themes/clarity` | yes | — |
| Reset `sys_user.app_theme` from `clarity` to `default` | **no** | `--reset-users` |
| Revert `$conf['theme']` | **never** — manual, in both files | — |
| Remove `interface/web/customizer` | yes | — |
| Strip `customizer` from users' module lists, repointing any `startmodule` that pointed at it | yes | `--keep-assignment` to skip |
| Wipe stored branding values | **no** | `--purge-branding` |

The two defaults that catch people out:

**`sys_user.app_theme` is left alone unless you pass `--reset-users`.** Core
falls back to the default theme at session level only; it never heals the stored
column. Leave rows set to `clarity` with the theme directory gone and every
affected user gets a "theme not compatible" banner at *every* login. The default
exists so that a reinstall keeps everyone's selection — if you are actually
removing the theme, pass the flag:

```bash
sudo ./uninstall.sh --theme --reset-users
```

**Branding values are preserved unless you pass `--purge-branding`.** They are
ISPConfig's own fields: the panel name, login text and logo keep rendering on the
stock theme because core reads `sys_ini.custom_logo` and the `[misc]` keys
itself, and they stay editable under *System > Interface Config*. The
`[branding]` section sits inert in `sys_ini` for any brand-aware theme. To clear
all of it:

```bash
sudo ./uninstall.sh --module --purge-branding
```

`bin/purge_branding.php` restores the three core-owned `dashboard_atom_url_*`
keys before dropping `[branding]`, but only where the module's own stash recorded
a URL *and* the live key is still empty — a key holding a URL again has been set
by hand since, and is never overwritten. So a purge does not silently discard a
feed the module turned off. Where nothing was stashed — a panel branded before
the stash existed, module v1.0.12 or earlier — it does not guess a URL back in:
it reports which roles are left with the feed off, and the stock URL to paste
under *System > Interface Config*. It blanks — rather than deletes — the three
stock `[misc]` keys, because those are real fields of *System > Interface
Config*.

**Reverting `$conf['theme']` is always yours to do.** If you set
`$conf['theme'] = 'clarity'`, set it back to `'default'` in **both**
`interface/lib/config.inc.php` and `server/lib/config.inc.php`. The uninstaller
checks for the line and warns you *before* it removes anything, because the
failure mode is ugly: core falls back to the stock login template, but that
template loads its stylesheets and scripts from the theme directory now being
removed, so every one of them 404s. Login still works; the page is unstyled and
no error is shown.

Both `bin/reset_app_theme.php` and `bin/purge_branding.php` are idempotent and
can be run directly, which is what you want if the database was unreachable
during the uninstall:

```bash
php bin/reset_app_theme.php /usr/local/ispconfig/interface/lib/config.inc.php
php bin/purge_branding.php  /usr/local/ispconfig/interface/lib/config.inc.php
```

## Version support

Developed and verified against **ISPConfig 3.3.1p1**. That is the version the six
template overrides are pinned to in `BUILT-AGAINST.txt`, and the version the core
behaviour described above was read from. ISPConfig 3.2 has not been tested — it
is neither claimed as supported nor known to be broken.
