# clarity — Clarity Theme for ISPConfig

The deployable theme directory. Install with `../../install.sh` (see the
repo README for the two-minute guide).

What's inside:

- `templates/` — the only three templates overridden:
  `main.tpl.htm` (app frame), `topnav.tpl.htm` (rail module nav),
  `main_login.tpl.htm` (login scene). Everything else renders from the stock
  `default` theme's templates and is styled by CSS alone.
- `assets/stylesheets/clarity/` — load order matters:
  `tokens.css` (all colors/sizes) → `base.css` (functional rules ported from
  stock, no skin) → `app.css` (frame) → `components.css` (content).
  Login pages load only `tokens.css` + `login.css`.
- `assets/fonts/inter/`, `assets/images/`, `assets/favicon/` — self-hosted
  Inter and the neutral default brand marks.
- `BUILT-AGAINST.txt` — the upgrade-safety contract: which stock behaviors
  the templates preserve and what to re-check after a major ISPConfig upgrade.

To re-theme (or build a light variant): edit `tokens.css` only. Components
never reference raw colors.
