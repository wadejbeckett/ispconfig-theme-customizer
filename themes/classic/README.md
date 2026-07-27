# classic — the stock ISPConfig look, made brandable

The second base design in this repository. It is the panel you already know:
stock layout, stock stylesheets, stock everything — except that the logo, panel
name and colours from the in-panel **Branding** page actually apply to it.

Install it with `../../install.sh --design=classic` (add `--design=all` to get
Clarity as well; see the repo README).

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
- `title.php` — the tab title, and the alt text core never sets on the login
  logo. Both endpoints are pre-authentication: the login screen links them.
- `templates/` — **generated at install time. Not in this repository.**

Nothing else. No stylesheets, no images, no fonts: every asset is served from
`themes/default/assets/`.

## templates/ is generated — do not hand-edit, do not commit

`install.sh` reads the **target panel's own**
`themes/default/templates/main.tpl.htm` and `main_login.tpl.htm`, applies two
mechanical changes, and writes the result here:

1. every `themes/<tmpl_var name='current_theme'>/assets/` path is pinned to
   `themes/default/assets/`;
2. `brand.php` and `title.php` are linked immediately before `</head>`.

Everything else is byte-for-byte stock. The installer verifies that: it aborts
rather than deploy a template it cannot account for.

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

`.gitignore` in this directory keeps the generated templates, and the version
stamps the installer writes, out of the repository. If you find yourself
editing a file under `templates/`, stop: the next `install.sh` run overwrites
it. Change `brand.php` instead, or the generator in `install.sh`.

## After an ISPConfig upgrade

Re-run `install.sh`. It re-stamps the version gate (without it ISPConfig
silently resets every user back to the default theme at their next login) and
regenerates these templates from the new stock ones in the same pass.
