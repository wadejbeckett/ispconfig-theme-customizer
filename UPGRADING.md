# Upgrading (and surviving ISPConfig updates)

The theme is designed to ride through ISPConfig panel updates without touching
core. There are two things to know: a **version stamp** you re-apply after a
major panel upgrade, and the **template-override contracts** to re-check after a
major one.

## After an ISPConfig panel update

ISPConfig gates themes by an **exact version match**. On login it resets a
user's theme to `default` unless `themes/clarity/ispconfig_version` exists and
equals the running `ISPC_APP_VERSION`; the Design picker hides the theme the
same way. `install.sh` stamps that value for you, so after any panel update:

```bash
cd /root/clarity-theme        # your clone
git pull                      # or: git fetch --tags && git checkout <tag>
sudo ./install.sh --copy      # re-stamps ispconfig_version / ISPC_VERSION
```

If you set `$conf['theme'] = 'clarity';` at install time, that line is carried
forward by panel updates **only if it is set in `server/lib/config.inc.php`**
(not just `interface/lib/config.inc.php`) — set it in both, as the install notes
say, or the login screen silently reverts at the next update.

## After a *major* ISPConfig upgrade (e.g. 3.3 → 3.4)

The theme overrides a few stock templates — most importantly three dashboard
dashlets (`templates/dashboard/dashboard.htm`, `modules.htm`, `metrics.htm`).
Each override is pinned in `themes/clarity/BUILT-AGAINST.txt` against the exact
stock template variables it consumes (loop names, `data-capp` click contract,
chart canvas ids). A **minor/patch** ISPConfig release almost never changes
these. A **major** release can, and a changed stock dashlet variable would make
an override render stale or empty.

So after a major upgrade, before trusting the dashboard overrides:

```bash
# compare the stock dashlets you upgraded to against the contracts you built on
diff <your new ISPConfig>/interface/web/dashboard/templates/dashboard.htm \
     .refs/ispconfig3/interface/web/dashboard/templates/dashboard.htm
# ...and dashlets/templates/modules.htm, metrics.htm
```

If a contract changed, update the override and the `BUILT-AGAINST.txt` pin. CI
enforces that every override is *documented*; it cannot know a future stock
template changed, so this check is manual and only needed on major upgrades. If
in doubt, simply delete `themes/clarity/templates/dashboard/` — the dashboard
falls back to the (now themed by CSS) stock dashlets with no override.

## Uninstalling / reverting

`sudo ./uninstall.sh --reset-users` removes the theme directory and resets
`sys_user.app_theme` for anyone on Clarity (core does **not** heal that column —
without the reset those users get a "theme not compatible" banner every login).
Revert `$conf['theme']` to `'default'` in both config files yourself; the
uninstaller never edits ISPConfig config and reminds you if the line is still
set.
