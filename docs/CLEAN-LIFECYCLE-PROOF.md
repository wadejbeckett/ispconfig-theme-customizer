# Clean-lifecycle proof

The extension does not touch ISPConfig core: installing it, using it and removing it leaves the panel as it was. This records how that was tested and what the test returned.

Run against **v3.0.0** of this extension on a live production ISPConfig 3.3.1p1 panel, 27 July 2026. It has not been re-run since; the install and uninstall paths it exercises have not changed, but the result below is a v3.0.0 result.

## Method

A pristine `ISPConfig-3.3.1p1.tar.gz` was downloaded onto the panel and every core file compared byte for byte against the installed tree, excluding the extension's own two directories. The database was snapshotted first (`sys_ini.config`, `sys_ini.custom_logo`, and every `sys_user` row) so the panel's state could be restored afterwards and verified byte-identical.

## Results

### Installed

| Check | Result |
|---|---|
| Core interface files vs pristine 3.3.1p1 | **6968 compared, 0 modified, 0 missing** |
| Server-side PHP vs pristine | **0 modified** |
| Files added outside the extension's own directories | **none** |

This cycle installed clarity only. With `--design=all` the same test additionally accounts for `interface/web/themes/classic/`, its two generated shell templates and its two version stamps.

### Uninstalled (default flags)

| Check | Result |
|---|---|
| Core interface files vs pristine | **6968 compared, 0 modified, 0 missing** |
| The extension's two directories | both removed |
| Files of its own left anywhere under the panel | **none** |
| `customizer` in `sys_user.modules` | removed |
| `sys_user.startmodule` | unchanged |
| Branding values in `sys_ini` | **preserved**, as documented |
| `sys_user.app_theme` | unchanged, as documented |

The last two are deliberate; [UPGRADING.md](../UPGRADING.md) has the flags that change them.

### Purged (`--purge-branding`)

| Check | Result |
|---|---|
| `[branding]` section | dropped |
| `company_name`, `custom_logo` | blanked |
| **Keys the extension does not own that were altered** | **0** |

That last row is the check that matters most: the config blob is shared with ISPConfig's own settings, and a read-modify-write careless about escaping or ordering silently corrupts values it was never asked to touch. Core has that defect — see [UPSTREAM-PATCHES.md](UPSTREAM-PATCHES.md) §4 — which is why this extension parses the raw column rather than going through `getconf`. The purge also printed the three per-role news-feed URLs it could not restore, with the stock URL to paste back.

### Reinstalled

| Check | Result |
|---|---|
| `sys_ini.config` restored | byte-identical to snapshot (2095 bytes) |
| `sys_ini.custom_logo` restored | byte-identical to snapshot (7459 bytes) |
| `sys_user` fields drifted | 1 — see below |
| Core files vs pristine, after the whole cycle | **6968 compared, 0 modified** |
| Panel | login 200, `brand.php` 200, `title.php` serving the panel name |

The drift: `customizer` moved position within `sys_user.modules`, because uninstall removed it from the middle of the CSV and reinstall appended it. The set is identical, 11 modules before and after, and ISPConfig treats the column as a set.

## Reproducing it

1. Snapshot `sys_ini` and `sys_user`.
2. Download the tarball matching your `ISPC_APP_VERSION` and diff every file under `interface/web` against the install, excluding `customizer/` and `themes/<design>/`.
3. Run `./uninstall.sh`, diff again, and inspect the database.
4. Restore the snapshot and reinstall.

If step 2 or 3 reports a single modified core file, that is a bug in this extension and worth reporting.
