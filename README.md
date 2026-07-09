# the earlier name — a dark theme for ISPConfig

A complete, modern interface for the [ISPConfig](https://www.ispconfig.org/)
control panel, built on **VMware Clarity** dark design tokens: navy
navigation rail, card-based content, Clarity icons throughout, a
**dark/light switcher**, and a redesigned login screen. Installed as a
normal ISPConfig theme — **no core file is ever modified**, so it survives
panel updates.

![Dashboard](mockup/shots/dark-dashboard-desktop.png)

| | |
|---|---|
| ![Websites list](mockup/shots/dark-sites-desktop.png) | ![Login](mockup/shots/dark-login-desktop.png) |

## Requirements

- ISPConfig **3.3** (built and verified against 3.3.1p1; 3.2 may work but is
  untested)
- Root shell access to the panel server
- The stock `default` theme still present (it always is — the earlier name loads
  its vendor CSS/JS from there)

## Install

```bash
git clone https://github.com/wadejbeckett/earlier-console.git
cd earlier-console
./install.sh /usr/local/ispconfig        # your ISPConfig root, if different
```

That's it. The installer symlinks the theme into
`interface/web/themes/earlier-dark` and stamps the version-gate files ISPConfig
requires. Use `./install.sh --copy` instead if you prefer real files over a
symlink (e.g. the clone won't stay on the server).

**Then switch your user to it:** log into the panel → *Tools → User
Settings → Design → `earlier-dark` → Save* → **log out and back in** (ISPConfig
applies the theme at login), and hard-refresh the browser (`Ctrl+Shift+R`).

**Login screen + system-wide default (optional):** edit
`interface/lib/config.inc.php` and set

```php
$conf['theme'] = 'earlier-dark';
```

This one line is update-safe and controls the login page and the default for
new users. Nothing else needs changing.

## After an ISPConfig upgrade

ISPConfig silently reverts users to the `default` theme unless the theme's
`ispconfig_version` file exactly matches the new panel version. So after any
panel upgrade:

```bash
cd earlier-console && ./install.sh /usr/local/ispconfig
```

(re-stamps the version files; on a **major** upgrade also check
`themes/earlier-dark/BUILT-AGAINST.txt` — it lists the three templates to re-diff
against stock.)

## Uninstall

```bash
rm -rf /usr/local/ispconfig/interface/web/themes/earlier-dark
```

Users who had it selected are automatically reset to the default theme at
their next login.

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| Theme not in the Design dropdown | Version stamp missing or stale — re-run `./install.sh`. |
| Selected it, but panel still looks stock | Log out and back in; then hard-refresh (`Ctrl+Shift+R`). |
| Reverted to default after a panel upgrade | Expected — re-run `./install.sh` (see above). |
| Unstyled/white page | `themes/default` missing or theme dir unreadable by the web server — the installer chowns to `ispconfig:ispconfig`, which the web server must be able to read (stock setups are). |

## White-labeling

Replace `themes/earlier-dark/assets/images/wordmark-white.svg` with your own
logo (white/light artwork — it sits on the navy brand band in the sidebar,
mobile header and login card). Any aspect ratio works. Favicons live in
`themes/earlier-dark/assets/favicon/`.

## Repo layout

| Path | What |
|---|---|
| `themes/earlier-dark/` | The theme: 3 templates + 5 stylesheets + fonts/brand assets. |
| `themes/earlier-dark/BUILT-AGAINST.txt` | Exactly what is overridden and why it's upgrade-safe. |
| `install.sh` | Installer (symlink or copy + version stamping). |
| `DESIGN.md` | The design language — tokens, surfaces, component rules. |
| `mockup/` | Offline dev harness: renders the real templates with sample content and screenshots them (`python3 build.py --shoot`, needs Playwright **and** a local ISPConfig source checkout at `.refs/ispconfig3/` for the stock vendor assets). Not needed to install. |

## Licensing

- Theme: MIT.
- Surface/status values derived from VMware **Clarity** (`@cds/core`, MIT);
  frame anatomy informed by DirectAdmin Evolution (reference only, nothing copied).
- **Inter** font — SIL OFL 1.1, self-hosted.
- ISPConfig is BSD-licensed; this theme ships no ISPConfig code and modifies none.
