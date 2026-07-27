# Clean-lifecycle proof

The central promise of this extension is that it does not touch ISPConfig core —
that installing it, using it, and removing it leaves the panel exactly as it was.
That is easy to claim and worth proving, so this records how it was tested and
what the test actually returned.

Run on a **live production ISPConfig 3.3.1p1 panel** (a working mail server, not
a lab), 27 July 2026.

## Method

Rather than trust the extension's own account of itself, a pristine
`ISPConfig-3.3.1p1.tar.gz` was downloaded onto the panel and every core file
compared byte for byte against the installed tree. Our own two directories were
excluded from the comparison — they are supposed to be new; everything else is
supposed to be untouched.

The database was snapshotted first (`sys_ini.config`, `sys_ini.custom_logo`, and
every `sys_user` row) so the panel's exact state could be restored afterwards and
verified byte-identical.

## Results

### Installed

| Check | Result |
|---|---|
| Core interface files vs pristine 3.3.1p1 | **6968 compared, 0 modified, 0 missing** |
| Server-side PHP vs pristine | **0 modified** |
| Files added outside our own directories | **none** |

The only paths the extension occupies are `interface/web/customizer/` and the
design directory it installed. This cycle installed clarity only; run with
`--design=all` and the same test additionally accounts for
`interface/web/themes/classic/`, its two generated shell templates and its two
version stamps.

### Uninstalled (default flags)

| Check | Result |
|---|---|
| Core interface files vs pristine | **6968 compared, 0 modified, 0 missing** |
| Our directories | both removed |
| Files of ours left anywhere under the panel | **none** |
| `customizer` in `sys_user.modules` | removed |
| `sys_user.startmodule` | unchanged |
| Branding values in `sys_ini` | **preserved**, as documented |
| `sys_user.app_theme` | unchanged, as documented |

The last two are deliberate. Uninstalling does not wipe your branding and does
not reset users' theme choice unless you ask for it — see UPGRADING.md for why,
and for the flags that do.

### Purged (`--purge-branding`)

| Check | Result |
|---|---|
| `[branding]` section | dropped |
| `company_name`, `custom_logo` | blanked |
| **Keys the extension does not own that were altered** | **0** |

Every line that changed was one of ours. This is the check that matters most,
because the config blob is shared with ISPConfig's own settings: a read-modify-
write that is careless about escaping or ordering silently corrupts values it
was never asked to touch. (Core has that exact defect — see
`UPSTREAM-PATCHES.md` §4 — which is why this extension parses the raw column
rather than going through `getconf`.)

The purge also reported, rather than guessed at, the three per-role news-feed
URLs it could not restore, giving the operator the stock URL to paste back.

### Reinstalled

| Check | Result |
|---|---|
| `sys_ini.config` restored | byte-identical to snapshot (2095 bytes) |
| `sys_ini.custom_logo` restored | byte-identical to snapshot (7459 bytes) |
| `sys_user` fields drifted | 1 — see below |
| Core files vs pristine, after the whole cycle | **6968 compared, 0 modified** |
| Panel | login 200, `brand.php` 200, `title.php` serving the panel name |

The single drift: `customizer` moved position within `sys_user.modules`, because
uninstall removed it from the middle of the CSV and reinstall appended it. The
set is identical (11 modules before and after) and ISPConfig treats the column as
a set, so this is ordering only — but it is recorded here rather than rounded
down to "no change".

## One honest note

The uninstall sweep found two files carrying our name outside our directories:

```
interface/lib/config.inc.php.bak-clarity
server/lib/config.inc.php.bak-clarity
```

They are **not created by this extension**. They are hand-made backups from
before the project was renamed — both contain `$conf['theme'] = 'noiz-dark'`, a
name that no longer exists — and `git log -S` across the full history confirms no
version of this code has ever written such a file.

They are mentioned because a sweep that only reported convenient results would be
worthless. If you back up `config.inc.php` by hand before editing the theme line,
clean the copy up afterwards; nothing here will do it for you, because nothing
here touches that file at all.

## Reproducing it

Nothing in the method is specific to this panel:

1. Snapshot `sys_ini` and `sys_user`.
2. Download the tarball matching your `ISPC_APP_VERSION` and diff every file
   under `interface/web` against your install, excluding `customizer/` and
   `themes/<design>/`.
3. Run `./uninstall.sh`, diff again, and inspect the database.
4. Restore the snapshot and reinstall.

If step 2 or 3 reports a single modified core file, that is a bug in this
extension and worth reporting.
