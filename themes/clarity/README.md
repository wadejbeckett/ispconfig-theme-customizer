# clarity — the Clarity design for ISPConfig

One of the two designs this extension ships, and the default one: a ground-up
dark and light interface. The other is `classic`, the stock ISPConfig look made
brandable — same brand contract, different skin. Install Clarity with
`../../install.sh`, which also sets up the in-panel **Branding** page unless you
pass `--theme` for the design alone; `--design=classic` or `--design=all` picks
the other one, or both (see the repo README for the two-minute guide).

The logo, panel name and colours are not baked in: `brand.php` reads them at
render time from the `sys_ini` keys the Branding page writes, and falls back to
the neutral defaults shipped here when nothing is set. `classic` reads the same
keys through its own `brand.php`, and every option on the Branding page applies
to both designs. That shared contract is why there is a single version number.

What's inside:

- `templates/` — the only six templates overridden. Three shell templates:
  `main.tpl.htm` (app frame), `topnav.tpl.htm` (rail module nav),
  `main_login.tpl.htm` (login scene). Three dashboard dashlet templates:
  `dashboard/dashboard.htm`, `dashboard/modules.htm`, `dashboard/metrics.htm`.
  Everything else renders from the stock `default` theme's templates and is
  styled by CSS alone.
- `assets/stylesheets/clarity/` — load order matters:
  `tokens.css` (all colors/sizes) → `icons.css` → `base.css` (functional rules
  ported from stock, no skin) → `app.css` (frame) → `components.css` (content).
  Login pages load only `tokens.css` + `icons.css` + `login.css`.
- `assets/fonts/inter/`, `assets/images/`, `assets/favicon/`,
  `assets/javascripts/` — self-hosted Inter, the neutral default brand marks,
  and the one script the frame adds. Everything else, including all vendor
  JavaScript, is served from `themes/default/assets/`.
- `BUILT-AGAINST.txt` — the upgrade-safety contract: which stock behaviors
  the templates preserve and what to re-check after an ISPConfig upgrade. That
  means every upgrade, patch releases included — the version stamp is an exact
  match, so `../../install.sh` has to be re-run each time.

To re-theme: edit `tokens.css` only — both the dark and the light mode live
there, as one set of aliases and a remap. Components never reference raw colors.
