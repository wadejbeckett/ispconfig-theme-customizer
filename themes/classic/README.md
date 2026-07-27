# classic — the stock ISPConfig look, made brandable

One of the two designs this extension ships; `clarity` is the other. It is the
panel you already know: stock layout, stock stylesheets, stock everything —
except that the logo, panel name and colours from the in-panel **Branding**
page actually apply to it.

Install it with `../../install.sh --design=classic` (add `--design=all` to get
Clarity as well; see the repo README). `../../uninstall.sh --design=classic`
removes just this design; a bare uninstall removes every design it finds.

## Why it exists

Core reads only two of the brand keys itself — `custom_logo` and
`company_name` — so on the stock `default` theme those two apply and the rest
do nothing. Accent colour, navigation band, login background and version-hiding
are read by a *design*, in its own `brand.php`. Until now the only design that
did that was Clarity, so branding your colours meant accepting Clarity's look.
`classic` closes that gap: same brand-token contract, stock skin.

## What's inside

- `brand.php` — the brand reader. Emits a small stylesheet that overrides
  **stock's** selectors (`theme.min.css`, `ispconfig.css`, Bootstrap 3.3.0);
  every colour keeps the operator's hue and takes stock's own lightness for
  that role, so stock's contrast relationships survive the re-brand.
  The login shell links it as `brand.php?scene=login`, which scopes the handful
  of rules that must apply to the login screen alone; anything else, including
  `?scene[]=`, falls through to the app scene. The value is compared, never
  emitted.
- `title.php` — the tab title, and the alt text core never sets on the login
  logo.
- `favicon.php` — the tab icon. It answers with the operator's own favicon
  (`[branding] favicon_url` by reference, else the uploaded `[branding]
  favicon`) and, when nothing is set, streams **stock's** own icon from
  `themes/default/assets/favicon/` — the very file the shell linked before this
  endpoint existed, so an unbranded panel is unchanged. It exists because a
  favicon is a `<link>`, not a style, so `brand.php` cannot carry it; and it
  never 404s, because a missing icon shows on every tab.
- `templates/` — **generated at install time. Not in this repository, and never
  to be committed or hand-edited.**

All three endpoints are pre-authentication: the login screen links them.

Nothing else. No stylesheets, no images, no fonts, and no icons of its own:
every asset is served from `themes/default/assets/`.

## templates/ is generated — do not hand-edit, do not commit

`install.sh` reads the **target panel's own**
`themes/default/templates/main.tpl.htm` and `main_login.tpl.htm`, applies four
mechanical changes, and writes the result here:

1. every `themes/<tmpl_var name='current_theme'>/assets/` path is pinned to
   `themes/default/assets/`;
2. `brand.php`, `title.php` and `favicon.php` are linked immediately before
   `</head>`;
3. stock's `<link rel='icon'>` / `rel='shortcut icon'` tags — three of them on
   3.3.x — are replaced by the single `favicon.php` link from change 2.
   Without this, change 1 would leave the tab showing the ISPConfig icon on a
   panel that is otherwise fully white-labelled;
4. in the app frame only, stock's single footer credit line is wrapped in its
   own span and a second span is appended for this design's credit.

Everything else is byte-for-byte stock. The installer verifies that: it counts
the lines it added *and the icon links it removed* and aborts rather than deploy
a template it cannot account for. It also proves the result carries exactly one
tab-icon link and that it is `favicon.php`, so an icon `<link>` written in a
shape the transform did not recognise fails the install instead of quietly
leaving the stock icon in place beside ours.

The third change is what makes the two footer-credit toggles on the Branding
page work here as well as on Clarity — stock renders the whole credit as bare
text, which CSS can only hide entirely or not at all, so each credit needs a
target of its own. Both toggles ship on, and neither touches a licence notice.
If a future ISPConfig moves that line the installer warns and carries on: the
toggles do nothing, everything else still installs.

This is generated rather than committed on purpose. ISPConfig's template
engine gives a design two lookup paths — `themes/<active>/templates` first,
`themes/default/templates` as the fallback (`lib/classes/tpl_ini.inc.php`) — so
shipping only these two files makes classic inherit every other template from
whatever ISPConfig version is installed. A *committed* copy of the two would
inherit nothing: it would freeze one version's markup and quietly diverge from
the panel it runs on. Generating them at install time makes classic
stock-by-construction, and re-running the installer after an upgrade — which is
already mandatory for the version stamp — heals them for free.

The asset paths have to be pinned because the fallback covers templates only,
not assets. A stock template asks for
`themes/<current_theme>/assets/…`, which under classic resolves to
`themes/classic/assets/` — a directory that does not exist and never will.
Every stylesheet, script and icon on the page would 404.

The repository's root `.gitignore` keeps the generated templates, and the
version stamps the installer writes, out of git. The rules live there rather
than in a `.gitignore` inside this directory because this directory is deployed
verbatim into the panel's web root, and a dotfile naming `install.sh` and
`templates/` would fingerprint the extension on a panel whose whole point is to
carry someone else's brand. For the same reason the generated templates carry
no "do not edit" banner: it would ship in the HTML of a pre-authentication page.

So the warning lives here instead. If you find yourself editing a file under
`templates/`, stop: the next `install.sh` run overwrites it, and git will not
keep it either. Change `brand.php` instead, or the generator in `install.sh`.

## After an ISPConfig upgrade

Re-run `install.sh`. It re-stamps the version gate (without it ISPConfig
silently resets every user back to the default theme at their next login) and
regenerates these templates from the new stock ones in the same pass.

That applies to every upgrade, patch releases included: the stamp is an exact
match against the panel's `ISPC_APP_VERSION`. Unlike a design with committed
templates, classic needs no manual diff against the new stock markup — the same
run that re-stamps it rebuilds its shell.
