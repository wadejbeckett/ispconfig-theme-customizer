# Community announcement — draft

Drop-in post for **forum.howtoforge.com** (ISPConfig → Tips/Tutorials or
Contributions). It is written in Markdown; the forum uses BBCode, so convert
headings to `[b]…[/b]`, links to `[url=…]…[/url]`, and images to `[img]…[/img]`
when posting. Images to attach are listed at the end.

**Framing note — the thing this draft gets right and earlier ones got wrong:**
this is ONE extension, not a theme and a module that happen to ship together. It
is a brandable front-end for ISPConfig. Two designs read the same contract —
`clarity` and `classic` — and the Branding page is where you make either one
yours. Don't reintroduce "two parts" structure — no `X + Y` title, no parallel
"what X gives you" / "what Y gives you" sections. That the pieces can be
installed separately is a deployment detail, not the product's identity.
"extension" is ISPConfig's own word for a third-party add-on (System →
Extension Installer); use it, not "plugin" or "module", for the product noun.

Keep the symbiosis framing in the opening intact — it is what positions this as
*building on* ISPConfig, not competing with it.

---

## A modern, brandable front-end for ISPConfig (open source, no core changes)

Hi everyone,

I've been running ISPConfig in production and wanted two things from it: for the
panel to look and feel like a first-class modern control panel, and to be able to
hand a client a panel with *their* name on it — **without ever patching the
core.** This is the result.

It replaces the panel's front-end with a design of your choosing — either
`clarity`, a ground-up dark and light interface, or `classic`, the stock
ISPConfig look made brandable — and adds an admin page where you set your logo,
panel name and colours. Those are not two products bolted together: the design reads its colours, logo and panel name from
the settings that page writes, which is why they ship as one thing with one
version number.

**It is built to be a good citizen of the ISPConfig ecosystem.** Everything lives
in a theme directory and a module directory — **no core file is ever modified**,
and no database schema is added — so an `ispconfig_update.sh` run never
overwrites or deletes it. (The version stamp does have to be re-applied after
every panel update — see the compatibility section; it's one command.) Almost every
setting is stored in ISPConfig's own `sys_ini` table; the one exception is the
donation-dashlet switch, which writes the `sys_config` row ISPConfig itself uses
for that dashlet.

**Out of the box nothing is hidden.** The "powered by ISPConfig" credit, the
admin update notice, the version line and the donate dashlet are all left exactly
as core ships them. There *are* toggles to hide the footer credits, the news feed,
the version line and the (admin-only) donation dashlet — handy when you're demoing to a client — and every one of
them ships **on**. Nothing disappears until an administrator deliberately turns
it off, and the open-source licence notices are never touchable. The goal is to
make ISPConfig an easier "yes" for people who'd otherwise reach for Plesk or
cPanel, and to send them toward ISPConfig's own support and paid modules, not
away from them.

### Screenshots

![Login](https://raw.githubusercontent.com/wadejbeckett/ispconfig-theme-customizer/main/mockup/shots/dark-login-desktop.png)

![Dashboard](https://raw.githubusercontent.com/wadejbeckett/ispconfig-theme-customizer/main/mockup/shots/dark-dashboard-desktop.png)

The Branding page, where the panel's identity is set:

![Branding page](https://raw.githubusercontent.com/wadejbeckett/ispconfig-theme-customizer/main/docs/screenshots/branding-page-dark.png)

*(Light mode of each is attached below.)*

### What you get

**The panel, redesigned**

- A dark canon and a light mode, toggled in the top bar and remembered per
  browser.
- A redesigned login screen, and a dashboard that leads with system status
  (live load / memory / network) instead of a wall of launcher tiles.
- Charts themed to match — gradient fills, hover tooltips — in both modes.
- Clarity-style icons, keyboard search (Ctrl/⌘-K, or `/`), an off-canvas mobile
  drawer, and a11y fixes for the stock markup.

**The panel, made yours**

- **Logo** — upload SVG/PNG/JPEG/GIF/WebP (validated, under 45 KB), or reference
  a file by root-relative path or `https://` URL. Shows on the sidebar, mobile
  header and login screen.
- **Panel name, accent / sidebar / login-background colours, login footnote.**
- **Visibility toggles** for the footer credits, dashboard news feed and Help
  version line, as described above.

Both halves are configured from one page inside the panel, labelled **Branding**
in the navigation.

### How it holds together (and why that matters to you)

The design reads a small, fixed set of keys out of `sys_ini`; the Branding page
writes exactly those keys. That contract is the whole architecture, and it has
two consequences worth knowing:

**Some of your branding works even on the stock ISPConfig theme**, because core
reads a few of those keys itself. Verified against the 3.3 source:

| Setting | Read by |
|---|---|
| `custom_logo`, `company_name`, the per-role dashboard Atom feed URLs | ISPConfig **core** — these apply on the stock theme too |
| `accent_hex`, `rail_hex`, `login_bg`, `logo_url`, `show_version` | either design's `brand.php` — these need a design installed |

So `./install.sh --module` on a stock panel is a legitimate thing to do if you
only want your logo and panel name. `--theme` alone is equally fine. The default
installs both, which is what most people want.

**And the design is replaceable.** Anything that reads those same keys inherits
the whole branding page for free. That is not hypothetical: **two** designs ship
and read the same contract — `clarity`, a ground-up dark and light interface, and
`classic`, the stock ISPConfig look made brandable. CI walks every
`themes/*/brand.php`, so a third cannot quietly opt out of the contract either.

### Install

```bash
cd /opt                              # somewhere the web server can read — NOT /root
git clone https://github.com/wadejbeckett/ispconfig-theme-customizer.git
cd ispconfig-theme-customizer
sudo ./install.sh                    # --theme or --module to install one half
```

Flags: `--theme` / `--module` / `--all` (default is both), `--design=<name>` to
choose the design — `clarity` (default), `classic`, or `all` to offer both in the
Design picker — `--copy` to copy real files instead of symlinking, and
`--no-assign` to skip assigning the Branding page to admin users. An `ISPCONFIG_ROOT` path can be passed as the last argument; it
defaults to `/usr/local/ispconfig`.

Then two manual steps the installer deliberately does **not** do for you:

1. To make it the login screen and system default, set
   `$conf['theme'] = 'clarity';` in **both** `interface/lib/config.inc.php` and
   `server/lib/config.inc.php`. The installer never edits ISPConfig config.
   (The server one matters: panel updates regenerate both files and carry the
   value forward from the *server* config.)
2. Select it for your own account: *Tools → User Settings → Design →
   `clarity` (or `classic`) → Save*. Core updates the session and force-reloads the page, so it applies
   immediately. If the frame still looks stock, hard-refresh (`Ctrl+Shift+R`) so
   the browser drops the old CSS.

Uninstall is `./uninstall.sh` with the same component and `--design` flags, plus
`--reset-users`, `--purge-branding` and `--keep-assignment`. `--design` defaults
to *all* designs there, deliberately: installing picks what you want, removing
has to clear whatever might be present. See below for what
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
`themes/clarity/BUILT-AGAINST.txt` against your own panel's stock theme.

**Uninstall does not "restore stock" by default, and that is on purpose.**

- The theme half removes its directory. It only resets `sys_user.app_theme` back
  to `default` if you pass `--reset-users` — do pass it unless you're
  reinstalling, because core never heals that column and affected users
  otherwise get a "theme not compatible" banner at every login.
- Reverting `$conf['theme']` is always yours to do, in both config files. The
  uninstaller checks and warns you before it removes anything, but it will not
  edit ISPConfig config.
- Your branding values are **kept** unless you pass `--purge-branding`. Logo,
  panel name and login text are stock ISPConfig fields that keep working and
  stay editable under System → Interface Config; the rest sits inert in
  `sys_ini`. That makes reinstalling painless, but it does mean an uninstalled
  panel is still branded.

### If you used the older repos

This used to be three repositories — a theme, a module, and an umbrella that
carried both as git submodules. That was a mistake: the design and the branding
page are one contract, and independent version numbers meant nothing enforced
that the two halves were in step. Running the module at v1.0.12 against the theme
at v2.1.0 meant the accent colour silently did not apply — no error, no warning,
nothing in a log to grep for.

They are now one repository, one version number, one tag, starting at **v3.0.0**.
No submodules, nothing to `--recurse-submodules`. The old
`clarity-theme-ispconfig`, `ispconfig-customizer` and `ispconfig-toolkit` repos
are **archived read-only** and each points here; please don't clone them. Your
stored branding is untouched by the move — it lives in `sys_ini`, not in the
clone. The installer detects a stale old clone and tells you to remove it.

### Licence & attribution

**MIT** (© Wade John Beckett). Not affiliated with or endorsed by the ISPConfig
project (BSD-3-Clause) or VMware (the Clarity design system); disclaimers are in
the README.

The icon set is not just tokens: 29 official `@cds/core` Clarity icon shapes are
bundled verbatim as data-URI SVG in
`themes/clarity/assets/stylesheets/clarity/icons.css`, so the iconography costs
no extra font download (the stock theme's Font Awesome and Bootstrap Icons are
still loaded as vendor CSS, for core's own markup). That is redistribution of a
substantial portion of the upstream MIT software, so VMware's copyright and
permission notice travels in that file's own header, where a downstream packager
who vendors only `themes/` still gets it.

The UI ships in **seven** languages — English, German, French, Spanish, Italian,
Dutch and Portuguese — with key parity enforced in CI. More translations very
welcome.

### Feedback wanted

This is offered back to the community free and open. I'd genuinely value review,
bug reports, and translation help — and I'm keeping a short list of small **core**
patches I found along the way (a couple of long-standing panel papercuts) that
I'd like to offer upstream as merge requests if the maintainers are open to them.

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
- [x] v3.0.0 tagged and pushed. Release is live at
      `github.com/wadejbeckett/ispconfig-theme-customizer/releases/tag/v3.0.0`.
- [x] Old repos archived read-only, each with a README pointing at the merged
      repo, pushed *before* archiving — archiving makes a repo read-only, so the
      order matters.
- [x] raw.githubusercontent image URLs verified against the NEW repo name (not
      relying on the rename redirect): all three return HTTP 200, and all eight
      files in the attach table exist in the repo.
- [x] `./install.sh --help` and `./uninstall.sh --help` run, and the flags in
      this post match what they print.
- [x] Reads as ONE product, not two that share a repo. No `X + Y` title, no
      parallel per-component sections.
- [ ] Decide whether the version-disclosure note belongs in the post at all. It
      is documented honestly in `SECURITY.md` and mitigated in
      `contrib/webserver/`; leading a launch post with it may be more caveat
      than a first impression wants. Currently omitted here — that is a change
      from an earlier draft, and it is your call.
- [ ] Post from the project account; link the repo, not the live panel.
- [ ] Later, separately: offer the upstream core patches in their own humble
      thread. Deliberately NOT part of this launch.
