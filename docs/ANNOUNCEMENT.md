# Community announcement — draft

Drop-in post for **forum.howtoforge.com** (ISPConfig → Tips/Tutorials or
Contributions). It is written in Markdown; the forum uses BBCode, so convert
headings to `[b]…[/b]`, links to `[url=…]…[/url]`, and images to `[img]…[/img]`
when posting. Images to attach are listed at the end.

Keep the framing in the first two paragraphs intact — it is what positions this
as *building on* ISPConfig, not competing with it.

---

## Clarity + Branding — a modern dark theme and a white-label module for ISPConfig (open source, no core changes)

Hi everyone,

I've been running ISPConfig in production and wanted it to look and feel like a
first-class modern control panel — and to let me hand a cleanly branded panel to
clients — **without ever patching the core.** That turned into an open-source
project I'd like to share with the community. It has two parts, and you can
install either one or both:

- **Clarity** — a complete dark (and light) theme: a navy sidebar rail, a
  redesigned login screen, a restructured dashboard with proper stat cards and
  themed charts, and Clarity/CDS-style iconography.
- **Branding** — an admin-only white-label module: set your logo, panel name,
  accent colours, login screen and a few visibility toggles from a page inside
  ISPConfig. Some of it works on **stock ISPConfig**; the colour settings need a
  brand-aware theme (like Clarity) to apply them.

**Both are built to be good citizens of the ISPConfig ecosystem.** Everything
lives in a theme directory and a module directory only — **no core file is ever
modified**, and no database schema is added — so an `ispconfig_update.sh` run
never overwrites or deletes them. (The theme's version stamp does have to be
re-applied after every panel update — see the compatibility section below.)
Every setting is stored in ISPConfig's own `sys_ini` table, so it is also
reachable across a fleet via the remote API — with one upstream caveat, noted
further down. **Out of the box nothing is hidden:** the "powered by ISPConfig"
credit, the admin update notice, the version line and the donate dashlet are all
left exactly as core ships them. The module *offers* toggles to hide the footer
credits, the news feed and the version line. Every attribution toggle ships
**on**: nothing is hidden until an administrator turns it off. The goal is to
make ISPConfig an easier "yes" for people who'd otherwise reach for
Plesk/cPanel — and send them to ISPConfig's own support and paid modules, not
away from them.

### Screenshots

![Login](https://raw.githubusercontent.com/wadejbeckett/ispconfig-theme-customizer/main/mockup/shots/dark-login-desktop.png)

![Dashboard](https://raw.githubusercontent.com/wadejbeckett/ispconfig-theme-customizer/main/mockup/shots/dark-dashboard-desktop.png)

The Branding page — set the logo, name, colours and visibility toggles from
inside the panel:

![Branding page](https://raw.githubusercontent.com/wadejbeckett/ispconfig-theme-customizer/main/docs/screenshots/branding-page-dark.png)

*(Light mode of each is attached below.)*

### One repo, one version number

The theme and the module used to be two separate repos with independent version
numbers. That was a mistake, and it is worth saying why, because it is the whole
reason for this release.

The module writes a small set of keys into `sys_ini`; the theme's `brand.php`
reads exactly those keys. That is one contract — and nothing enforced that the
two halves of it were in step. Running module v1.0.12 against theme v2.1.0 meant
the accent colour silently did not apply. No error, no warning, nothing in a log
to grep for: you set a colour, saved, and the panel stayed blue.

So they are now **one repository, one version number, one tag**:
**[ispconfig-theme-customizer](https://github.com/wadejbeckett/ispconfig-theme-customizer)**,
starting at **v3.0.0** (above both old lines — the theme was v2.2.4, the module
v1.0.13 — and a major bump because the repo layout changed). There are no git
submodules and nothing to `--recurse-submodules`. The old
`clarity-theme-ispconfig`, `ispconfig-customizer` and `ispconfig-toolkit` repos
are **archived read-only**; please don't clone them.

### What Clarity (the theme) gives you

- Dark canon + a light mode, toggled in the top bar and remembered per browser.
- A redesigned login screen and a dashboard that leads with system status
  (live load / memory / network) instead of a wall of launcher tiles.
- Charts themed to match (gradient fills, hover tooltips), in both modes.
- Clarity-style icons, keyboard search (Ctrl/⌘-K, or `/`), an off-canvas mobile
  drawer, and a11y fixes for the stock markup.
- Self-contained: it only borrows the stock theme's vendor CSS/JS; it never
  edits them. It overrides **six** stock templates — three shell templates
  (`main.tpl.htm`, `main_login.tpl.htm`, `topnav.tpl.htm`) and three dashboard
  dashlets (`dashboard.htm`, `modules.htm`, `metrics.htm`). Each one is pinned
  with the exact stock template variables it consumes in
  `themes/clarity/BUILT-AGAINST.txt`, so you can re-check them after an upgrade.

### What Branding (the module) gives you

- **Logo** — upload SVG/PNG/JPEG/GIF/WebP (validated, under 45 KB), or reference
  a file by root-relative path or `https://` URL. Shows on the sidebar, mobile
  header and login.
- **Panel name, accent / sidebar / login-background colours, login footnote.**
- **Visibility toggles** — optionally hide the footer credits, the dashboard
  news feed, and the Help version line (handy for clean client demos). The
  open-source **licence notices are always kept** — the toggles only hide
  optional courtesy text, never a licence file.
- Stored in `sys_ini`, so it survives updates and is readable/writable through
  the remote API's `system_config_set` — with one upstream caveat: that call
  rewrites the whole config blob through core's `getconf`, which strips a
  backslash level from every value it isn't editing (the module itself parses
  the raw column precisely to avoid this). If you plan to push branding across a
  fleet that way, read `SECURITY.md` and `docs/UPSTREAM-PATCHES.md` §4 first.

**How much works without the theme** (verified against the ISPConfig 3.3
source — worth being exact about):

| Setting | Read by |
|---|---|
| `custom_logo`, `company_name`, the per-role dashboard Atom feed URLs | ISPConfig **core** — these work on the stock theme |
| `accent_hex`, `rail_hex`, `login_bg`, `logo_url`, `show_version` | only a **brand-aware theme's** `brand.php` — these need Clarity |

The nav entry is labelled **Branding** (not "Customizer" — that's the internal
directory name).

### Install

```bash
cd /opt                              # somewhere the web server can read — NOT /root
git clone https://github.com/wadejbeckett/ispconfig-theme-customizer.git
cd ispconfig-theme-customizer
sudo ./install.sh                    # both components; --theme or --module for one
```

Flags: `--theme` / `--module` / `--all` (default is both), `--copy` to copy real
files instead of symlinking, `--no-assign` to skip assigning the module to admin
users. An `ISPCONFIG_ROOT` path can be passed as the last argument; it defaults
to `/usr/local/ispconfig`.

Then two manual steps the installer deliberately does **not** do for you:

1. To make Clarity the login screen and system default, set
   `$conf['theme'] = 'clarity';` in **both** `interface/lib/config.inc.php` and
   `server/lib/config.inc.php`. The installer never edits ISPConfig config.
   (The server one matters: panel updates regenerate both files and carry the
   value forward from the *server* config.)
2. Pick Clarity for your own account: *Tools → User Settings → Design →
   `clarity` → Save*. Core updates the session and force-reloads the page, so
   the change applies immediately. If the frame still looks stock, hard-refresh
   (`Ctrl+Shift+R`) so the browser drops the old CSS.

Uninstall is `./uninstall.sh` with the same component flags, plus
`--reset-users`, `--purge-branding` and `--keep-assignment`. See below for what
it does and does not undo.

### Compatibility, and the two things that will bite you

- ISPConfig **3.3** — developed and verified against **3.3.1p1**. 3.2 is *not*
  tested; it may work, but I won't claim it does.
- PHP 8.x (tested on 8.2).

**Re-run `install.sh` after every ISPConfig update, including patch releases.**
ISPConfig gates third-party themes on an *exact* string match against
`ISPC_APP_VERSION`. That string changes on a p-release too, so 3.3.1p1 → 3.3.1p2
is enough to make core reset every affected user to the default theme at their
next login. Re-running the installer just re-stamps the version files. After a
*major* upgrade (3.3 → 3.4) also re-check the six template contracts in
`BUILT-AGAINST.txt` against your own panel's stock theme, or against the
ISPConfig source for your version.

**Uninstall does not "restore stock" by default, and that is on purpose.**
Concretely:

- The theme uninstaller removes the theme directory. It only resets
  `sys_user.app_theme` back to `default` if you pass `--reset-users` — do pass
  it unless you're reinstalling, because core never heals that column and
  affected users otherwise get a "theme not compatible" banner at every login.
- Reverting `$conf['theme']` is always yours to do, in both config files. The
  uninstaller checks and warns you before it removes anything, but it will not
  edit ISPConfig config.
- The module uninstaller **keeps your branding values** unless you pass
  `--purge-branding`. Logo, panel name and login text are stock ISPConfig fields
  that keep working and stay editable under System → Interface Config; the rest
  sits inert in `sys_ini` for any brand-aware theme. That makes a reinstall
  painless, but it does mean an uninstalled panel is still branded.

### One security note, up front

Core's theme gate requires a file at `themes/<theme>/ispconfig_version` inside
the panel's web root, so the web server serves it as a static file. An
unauthenticated `GET /themes/clarity/ispconfig_version` returns your exact
ISPConfig version *and patch level* — no session, no unusual log entry.

This is not specific to my theme: stock ISPConfig's `default` theme ships no
such file and returns 404, so the exposure arrives with **any** third-party
theme, this one included. I tested both. It also undercuts the module's own
"hide the version" toggle, which only hides the display on the Help page while
the same value stays readable one URL away.

There is nothing I can do about it from inside the theme — core reads those
exact filenames and there is no alternative location — so `contrib/webserver/`
ships nginx and Apache deny snippets, in the same idiom ISPConfig already uses
for `.lng` files in its own panel vhost, with a full explanation in that
directory's README. If you install any third-party theme, it is worth applying
regardless of whose theme it is.

### Licence & attribution

**MIT** (© Wade John Beckett). Not affiliated with or endorsed by the ISPConfig
project (BSD-3-Clause) or VMware (the Clarity design system); disclaimers are in
the README.

The icon set is not just tokens: 29 official `@cds/core` Clarity icon shapes are
bundled verbatim as data-URI SVG in
`themes/clarity/assets/stylesheets/clarity/icons.css`, so Clarity's own
iconography costs no extra font download (the stock theme's Font Awesome and
Bootstrap Icons are still loaded as vendor CSS, for core's own markup). That is
redistribution of a substantial portion of the upstream MIT
software, so VMware's copyright and permission notice travels in that file's own
header, where a downstream packager who vendors only `themes/` still gets it.

The module's UI ships in **seven** languages — English, German, French, Spanish,
Italian, Dutch and Portuguese — with key parity enforced in CI. More
translations very welcome.

### Feedback wanted

This is offered back to the community free and open. I'd genuinely value
review, bug reports, and translation help — and I'm keeping a short list of
small **core** patches I found along the way (a couple of long-standing panel
papercuts) that I'd like to offer upstream as merge requests if the maintainers
are open to them.

Repo: https://github.com/wadejbeckett/ispconfig-theme-customizer

Thanks — and thanks to Till and the team for ISPConfig.

---

### Images to attach

All paths are relative to the repo root.

| File | Caption |
|---|---|
| `mockup/shots/dark-login-desktop.png` | Login — dark |
| `mockup/shots/light-login-desktop.png` | Login — light |
| `mockup/shots/dark-dashboard-desktop.png` | Dashboard — dark |
| `mockup/shots/light-dashboard-desktop.png` | Dashboard — light |
| `mockup/shots/dark-sites-desktop.png` | Websites list — dark |
| `mockup/shots/dark-dashboard-mobile.png` | Dashboard — mobile |
| `docs/screenshots/branding-page-dark.png` | Branding page — dark |
| `docs/screenshots/branding-page-light.png` | Branding page — light |

(There is no light-mode capture of the Websites list or of mobile — either
capture them before posting or leave those two dark-only.)

### Pre-post checklist

- [x] Branding-page screenshot captured and committed (dark + light).
- [ ] v3.0.0 tagged and pushed before the post goes up — every link and the
      install block assume it exists.
- [ ] Old repos (`clarity-theme-ispconfig`, `ispconfig-customizer`,
      `ispconfig-toolkit`) archived read-only, each with a README line pointing
      at the merged repo, so anyone arriving from an old link lands somewhere
      useful.
- [ ] Confirm the raw.githubusercontent image URLs render in a forum preview
      (use `raw.githubusercontent.com/.../main/...`, not `github.com/.../raw/...`).
      Re-check these **after** the GitHub rename — verify the new repo name in
      the URL, don't rely on the redirect.
- [ ] Run `./install.sh --help` and `./uninstall.sh --help` once and confirm the
      flags in this post match what they print.
- [ ] Post from the project account; link the repo, not the live panel.
- [ ] Optional: open a separate, humble thread offering the upstream core
      patches, rather than bundling them into the announcement.
