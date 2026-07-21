# Upgrading (and surviving ISPConfig updates)

The module is designed to survive ISPConfig updates with no action from you.

- **Your settings survive.** Everything the module manages is stored in
  ISPConfig's own `sys_ini` row (panel name, colours, login text, the toggles)
  or its native `custom_logo` column. ISPConfig updates do not touch that data,
  so your branding persists across panel upgrades and theme changes.
- **The module directory usually survives too.** ISPConfig updates copy the core
  web root but do not delete unknown module directories, so
  `interface/web/customizer/` normally stays in place. If an upgrade ever removes
  it (or you want the latest module code), just re-run the installer:

  ```bash
  cd /root/ispconfig-customizer      # your clone
  git pull                           # or: git fetch --tags && git checkout <tag>
  sudo ./install.sh --copy
  ```

- **No version stamp, no core patch.** Unlike a theme, the module isn't gated by
  an ISPConfig version match and changes nothing in core, so there is nothing to
  re-stamp after an upgrade.
- **Module assignment.** The install assigns the `Branding` module to admin
  accounts. If a new admin is created after install and doesn't see it, either
  re-run `./install.sh` or tick the module for that user under *System → CP
  Users*.

## Compatibility notes

- Developed and verified against **ISPConfig 3.3.1p1** (3.2 / 3.3 supported).
- The module writes only existing `sys_ini`/`sys_user` fields, so a future
  ISPConfig that renames or removes one of those core keys is the only thing
  that could affect it — none are deprecated as of 3.3.

## Uninstalling

```bash
sudo ./uninstall.sh                    # remove module + unassign from all users
sudo ./uninstall.sh --purge-branding   # ...and wipe every stored branding value
```

By default your branding values are **preserved** (reinstall-friendly). ISPConfig
core is never modified in either direction.
