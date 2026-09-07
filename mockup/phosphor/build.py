#!/usr/bin/env python3
"""Build the phosphor static mockup, then screenshot it.

Same idea as mockup/build.py one level up: the pages are assembled from a
single shell plus the harness's captured ISPConfig content fragments, so the
markup under the skin is the markup the panel serves. The difference is that
phosphor has no theme directory yet — this spec is unimplemented — so the
shell here is written to the spec rather than rendered from templates, and
the output is committed HTML rather than a throwaway webroot/.

    python3 build.py            # write the five pages + index.html
    python3 build.py --shoot    # build, then screenshot with playwright
"""
import re
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
HARNESS_FRAG = HERE.parent / "fragments"
FRAG = HERE / "fragments"
SHOTS = HERE / "shots"

MODULES = [  # (title, module, icon) — the stock module list
    ("Home", "dashboard", "icon icon-dashboard"),
    ("Help", "help", "icon icon-help"),
    ("Client", "client", "icon icon-client"),
    ("Sites", "sites", "icon icon-sites"),
    ("Email", "mail", "icon icon-mail"),
    ("DNS", "dns", "icon icon-dns"),
    ("Monitor", "monitor", "icon icon-monitor"),
    ("Tools", "tools", "icon icon-tools"),
    ("System", "admin", "icon icon-admin"),
]

# A tab icon that needs no file: the same square-with-a-bar mark the rail uses.
FAVICON = ("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E"
           "%3Crect width='32' height='32' rx='6' fill='%2306080A'/%3E"
           "%3Crect x='7.5' y='7.5' width='17' height='17' rx='2.5' fill='none' stroke='%23FFA301' stroke-width='2.5'/%3E"
           "%3Crect x='12' y='13.5' width='8' height='3' fill='%23FFA301'/%3E%3C/svg%3E")

HEAD = """<!DOCTYPE html>
<html lang='en'>
<head>
  <meta charset='utf-8' />
  <title>{title}</title>
  <meta name='viewport' content='width=device-width, initial-scale=1, user-scalable=yes'>
  <meta name='robots' content='noindex, nofollow' />
  <meta name='theme-color' content='#06080A'>
  <link rel='icon' href="{favicon}">

  <!-- phosphor layer. In the shipping theme this is fonts + tokens + icons +
       base + app + components (+ login), loaded in that order; the mockup
       carries one stylesheet plus the verbatim icon file. -->
  <link rel='stylesheet' href='fonts.css'>
  <link rel='stylesheet' href='phosphor.css'>
  <link rel='stylesheet' href='icons.css'>
</head>
"""


def topnav(active: str) -> str:
    rows = []
    for title, module, icon in MODULES:
        cls = "pz-modnav-item active" if module == active else "pz-modnav-item"
        capp = "" if module == active else f" data-capp='{module}'"
        cur = " aria-current='page'" if module == active else ""
        rows.append(
            f"      <a href='#' class='{cls}'{capp} data-icon-class='{icon}'{cur}>\n"
            f"        <span class='{icon}'></span>\n"
            f"        <span class='title'>{title}</span>\n"
            f"      </a>")
    return ("    <nav id='main-navigation' class='pz-modnav' aria-label='Modules'>\n"
            + "\n".join(rows) + "\n    </nav>")


def frag(name: str, harness: bool = False) -> str:
    """Load a content fragment. Harness fragments are captured ISPConfig output
    and carry the clarity-era nz- class prefix; phosphor's stylesheet uses pz-,
    so the prefix is renamed and nothing else is touched."""
    src = (HARNESS_FRAG if harness else FRAG) / name
    text = src.read_text(encoding="utf-8")
    if harness:
        text = text.replace("nz-", "pz-").replace('id="pz-stat-', 'id="pz-stat-')
    return text


def shell(title: str, active: str, sidebar: str, content: str,
          scripts: str = "", panel_name: str = "Aperture Hosting") -> str:
    return HEAD.format(title=title, favicon=FAVICON) + f"""
<body class='pz'>
  <a class='pz-skip' href='#content'>Skip to content</a>
  <nav class='pushy pushy-left' id='pz-drawer' aria-label='Mobile navigation'></nav>
  <div class='site-overlay'></div>

  <div id='container'>
    <aside class='pz-rail' aria-label='Sidebar'>
      <div class='pz-brand'>
        <div id='logo'><a href='#' aria-label='{panel_name}'><span class='pz-mark' aria-hidden='true'></span><span class='pz-wordmark'>{panel_name}</span></a></div>
      </div>
      <div id='topnav-container'>
{topnav(active)}
      </div>
      <div id='sidebar' class='pz-context'>
{sidebar}
      </div>
    </aside>

    <div class='pz-main'>
      <header class='pz-topbar'>
        <button type='button' class='menu-btn' aria-label='Menu' aria-expanded='false' aria-controls='pz-drawer'>&#9776;</button>
        <a class='pz-topbar-brand' href='#' aria-label='{panel_name}'><span class='pz-mark' aria-hidden='true'></span>{panel_name}</a>
        <div id='headerbar'>
          <form action='#' method='get' id='searchform' role='form'>
            <div class='input-group'>
              <input id='globalsearch' type='text' class='form-control' placeholder='Search' />
              <span class='input-group-btn'>
                <button class='btn btn-default' type='button' title='Search'><span class='icon icon-lens'></span></button>
              </span>
            </div>
          </form>
          <div class='pz-topbar-actions'>
            <button type="button" class="notification" data-toggle="modal" data-target="#datalogModal" aria-live="polite">
              <span class="pz-sr">Pending changes: </span><span class="notification_text">3</span>
            </button>
            <span class='pz-user'><span class='icon icon-client' aria-hidden='true'></span>admin</span>
            <button type='button' id='logout-button' class='btn btn-sm btn-danger text-uppercase' data-load-content="login/logout.php">Log out</button>
          </div>
        </div>
      </header>

      <main id='content'>
        <form method="post" action="" id="pageForm" name="pageForm" enctype="multipart/form-data" class='form-horizontal' role='form'>
          <div id="pageContent" data-startpage="dashboard/dashboard.php">
{content}
          </div>
        </form>
      </main>

      <footer id='footer'>
        <span class='pz-credit-ispconfig'>powered by <a href="#" rel="noopener">ISPConfig</a></span>
        <span class='pz-credit-theme'><span class='pz-credit-sep'> &middot; </span><a href="#" rel="noopener">phosphor</a></span>
      </footer>
    </div>
  </div>
{scripts}
  <script src='phosphor.js'></script>
</body>
</html>
"""


CHART_BOOTSTRAP = """  <script src='vendor/chart.umd.js'></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
    var L = ['','','','','','','','','','','',''];
    createChart('loadchart', 'Server load (1 min)', L,
                [0.42,0.55,0.48,0.71,0.62,0.90,1.15,0.88,0.64,0.70,0.52,0.61]);
    createChart('memchart', 'Memory usage %', L,
                [38,41,40,45,52,58,71,64,60,55,47,49]);
    createChart('rxchart', 'Network In/kB', L,
                [3.2,2.8,4.1,3.6,8.4,12.7,9.2,5.5,4.8,6.1,5.2,4.4]);
    createChart('txchart', 'Network Out/kB', L,
                [1.1,0.9,1.4,1.2,2.9,4.2,3.1,1.8,1.5,2.0,1.7,1.5]);
  });
  </script>
"""


def login_page(panel_name: str = "Aperture Hosting") -> str:
    body = frag("login.html", harness=True)
    return HEAD.format(title=panel_name, favicon=FAVICON) + f"""
<body class='pz-login'>
  <div class='pzl-grid' aria-hidden='true'></div>
  <div class='pzl-scan' aria-hidden='true'></div>
  <div class='pzl-grain' aria-hidden='true'></div>

  <div class='pzl-scene'>
    <div class='pzl-brand'>
      <span class='pzl-brandname'>{panel_name}</span>
      <span class='pzl-cursor' aria-hidden='true'></span>
    </div>
    <main class='pzl-card' aria-label='Sign in'>
      <p class='pzl-hint'>Sign in to your control panel</p>
{body}
      <div class='pzl-custom'><a href='#'>Support: help@aperture.example</a></div>
    </main>
    <footer class='pzl-footer'>
      <span class='pz-credit-ispconfig'>powered by <a href="#" rel="noopener">ISPConfig</a></span>
      <span class='pz-credit-sep'> &middot; </span><a href="#" rel="noopener">phosphor</a>
    </footer>
  </div>
  <script src='phosphor.js'></script>
</body>
</html>
"""


SIDENAV_CLIENT = """<header>Clients</header>
<ul>
  <li><a href='#'>Add client</a></li>
  <li><a href='#' class='active'>Edit client</a></li>
  <li><a href='#'>Clients</a></li>
  <li><a href='#'>Resellers</a></li>
</ul>
<header>Templates</header>
<ul>
  <li><a href='#'>Limit templates</a></li>
  <li><a href='#'>Client circles</a></li>
</ul>"""

SIDENAV_TOOLS = """<header>Panel</header>
<ul>
  <li><a href='#'>User settings</a></li>
  <li><a href='#' class='active'>Branding</a></li>
</ul>
<header>Data</header>
<ul>
  <li><a href='#'>Import</a></li>
  <li><a href='#'>Export</a></li>
</ul>"""


PAGES = {
    "01-login.html":         ("Login",         None),
    "02-dashboard.html":     ("Dashboard",     None),
    "03-sites.html":         ("Sites",         None),
    "04-client-limits.html": ("Client limits", None),
    "05-branding.html":      ("Branding",      None),
}

CAPTIONS = {
    "phosphor-01-login-desktop.png":        ("01 — Login, 1440&times;900", "The one bold moment: a 34px terminal grid under a radial mask, one 384px glass card, the panel name with a blinking mono cursor. Two of the four glow allowances are spent here and nowhere else on the screen."),
    "phosphor-01-login-mobile.png":         ("01 — Login, 390&times;844", "The card and the grid still read at 375px; the scene is unchanged, only narrower."),
    "phosphor-02-dashboard-desktop.png":    ("02 — Dashboard, 1440&times;900", "Glass over black at one depth. The metric tiles are opaque instruments recessed into the pane, not a second layer of glass; mono numerals against grotesk prose; the chart is themed dark with an amber line and no paper island."),
    "phosphor-02-dashboard-mobile.png":     ("02 — Dashboard, 390&times;844", "Rail collapses to the drawer toggle, tiles stack, the reading strip wraps."),
    "phosphor-03-sites-desktop.png":        ("03 — Sites list, 1440&times;900", "The table treatment: a glass wrapper, a raised header band in 11px mono caps, 8&times;12 cells at 13px, an amber row wash on hover, in-row actions demoted to borderless ink. The inactive site carries a danger rule, not a red panel."),
    "phosphor-04-client-limits-desktop.png": ("04 — Client &rsaquo; Limits, 1440&times;900", "The cluttered form survives: a two-column grid inside each accordion panel at &ge;1280px, section captions in the mono voice, recessed wells on the panel's own pane — glass is not stacked on glass."),
    "phosphor-05-branding-desktop.png":     ("05 — Branding, 1440&times;900", "Two columns with a sticky preview. Each uploader sits inline with its variant, previewed on the background that variant is for. Placement is a third block about the pair, not a property of either mark."),
    "phosphor-05-branding-full.png":        ("05 — Branding, full page", "All four groups: Identity, Colour, Login screen, Panel visibility. rail_hex_light is present, stored, and labelled a no-op on this design; show_design_picker leads the visibility group."),
    "phosphor-05-branding-1024.png":        ("05 — Branding, 1024&times;900", "The single-column collapse: the preview moves above the fields rather than off the side."),
}


def build() -> None:
    (HERE / "01-login.html").write_text(login_page(), encoding="utf-8")

    (HERE / "02-dashboard.html").write_text(
        shell("Dashboard", "dashboard",
              frag("news.html", harness=True),
              frag("dashboard.html", harness=True),
              CHART_BOOTSTRAP), encoding="utf-8")

    (HERE / "03-sites.html").write_text(
        shell("Websites", "sites",
              frag("sidenav-sites.html", harness=True),
              frag("sites-list.html", harness=True)), encoding="utf-8")

    (HERE / "04-client-limits.html").write_text(
        shell("Edit Client", "client", SIDENAV_CLIENT,
              frag("client-limits.html")), encoding="utf-8")

    (HERE / "05-branding.html").write_text(
        shell("Branding", "tools", SIDENAV_TOOLS,
              frag("branding.html")), encoding="utf-8")

    for name in PAGES:
        print(f"  {name}: {len((HERE / name).read_text(encoding='utf-8'))} bytes")


def contact_sheet() -> None:
    shots = sorted(p.name for p in SHOTS.glob("phosphor-*.png"))
    cards = []
    for s in shots:
        title, note = CAPTIONS.get(s, (s, ""))
        cls = "cs-card cs-narrow" if "-mobile" in s else "cs-card"
        cards.append(f"""  <figure class='{cls}'>
    <a href='shots/{s}'><img src='shots/{s}' alt='{title}' loading='lazy'></a>
    <figcaption><h2>{title}</h2><p>{note}</p><code>shots/{s}</code></figcaption>
  </figure>""")
    page = HEAD.format(title="phosphor — contact sheet", favicon=FAVICON) + """
<body class='pz'>
<style>
  .cs-wrap { max-width: 1180px; margin: 0 auto; padding: 44px 28px 60px; position: relative; z-index: 1; }
  .cs-wrap > header { margin: 0 0 34px; }
  .cs-wrap > header h1 { font-size: 30px; letter-spacing: -.02em; }
  .cs-wrap > header p { color: var(--pz-ink-sub); max-width: 74ch; margin-top: 8px; }
  .cs-narrow img { max-width: 390px; margin: 0 auto; border-right: 1px solid var(--pz-edge);
                   border-left: 1px solid var(--pz-edge); }
  .cs-card { margin: 0 0 34px; background: var(--pz-glass); backdrop-filter: blur(var(--pz-blur));
             border: 1px solid var(--pz-edge); border-top-color: var(--pz-edge-top);
             border-radius: var(--pz-radius-card); overflow: hidden; }
  .cs-card img { display: block; width: 100%; border-bottom: 1px solid var(--pz-edge); }
  .cs-card figcaption { padding: 14px 18px 16px; }
  .cs-card h2 { font-size: var(--pz-fs-section); font-weight: 500; margin: 0 0 4px; }
  .cs-card p { color: var(--pz-ink-sub); margin: 0 0 8px; max-width: 84ch; font-size: var(--pz-fs-secondary); }
  .cs-card code { color: var(--pz-ink-muted); }
</style>
<div class='cs-wrap'>
  <header>
    <h1>Black glass with phosphor glow</h1>
    <p>Five screens built to <code>docs/superpowers/specs/2026-09-07-phosphor-design.md</code>, on real ISPConfig page markup. Nothing is implemented in <code>themes/</code>; these are HTML files under <code>mockup/phosphor/</code>. Click a shot for the full-size PNG.</p>
  </header>
""" + "\n".join(cards) + """
</div>
</body>
</html>
"""
    (HERE / "index.html").write_text(page, encoding="utf-8")
    print(f"  index.html: {len(shots)} shots")


# (page, label, viewport, full_page). Branding is shot at the viewport rather
# than full page for its headline frame: the preview column is sticky, and a
# full-page capture renders it parked at the top beside two thousand pixels of
# empty column, which is the one thing the layout does not do in use. The
# full-page and the 1024 collapse are kept alongside it.
SHOT_MATRIX = [
    ("01-login",         "desktop",  (1440, 900), True),
    ("01-login",         "mobile",   (390, 844),  False),
    ("02-dashboard",     "desktop",  (1440, 900), True),
    ("02-dashboard",     "mobile",   (390, 844),  False),
    ("03-sites",         "desktop",  (1440, 900), True),
    ("04-client-limits", "desktop",  (1440, 900), True),
    ("05-branding",      "desktop",  (1440, 900), False),
    ("05-branding",      "full",     (1440, 900), True),
    ("05-branding",      "1024",     (1024, 900), False),
]


def shoot() -> None:
    from playwright.sync_api import sync_playwright

    SHOTS.mkdir(exist_ok=True)
    srv = subprocess.Popen([sys.executable, "-m", "http.server", "8901", "-d", str(HERE)],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        with sync_playwright() as p:
            b = p.chromium.launch()
            for name, label, (w, h), full in SHOT_MATRIX:
                pg = b.new_page(viewport={"width": w, "height": h})
                failed = []
                pg.on("requestfailed", lambda r: failed.append(r.url))
                pg.goto(f"http://127.0.0.1:8901/{name}.html", wait_until="networkidle")
                # Freeze the blink and the reveal at their final state so the
                # shot is deterministic; do NOT remove anything. Drop focus
                # too: the login username carries stock ISPConfig autofocus,
                # and a focus ring in the shot competes with the one moment
                # the scene is built around.
                pg.add_style_tag(content="*,*::before,*::after{"
                                         "animation:none!important;transition:none!important;"
                                         "caret-color:transparent!important}"
                                         ".pzl-cursor{opacity:1!important}"
                                         ".pzl-scene,.pzl-grid{opacity:1!important;transform:none!important}")
                pg.evaluate("() => document.activeElement && document.activeElement.blur()")
                pg.wait_for_timeout(500)
                out = SHOTS / f"phosphor-{name}-{label}.png"
                pg.screenshot(path=str(out), full_page=full)
                print(f"  {out.name}  ({len(failed)} failed requests)")
                for u in failed[:6]:
                    print(f"      MISS {u}")
                pg.close()
            b.close()
    finally:
        srv.terminate()


if __name__ == "__main__":
    print("building mockup/phosphor/")
    build()
    if "--shoot" in sys.argv:
        print("\nscreenshotting")
        shoot()
    contact_sheet()
    print("\ndone")
