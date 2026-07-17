#!/usr/bin/env python3
"""Render ISPConfig pages under the Clarity Theme for ISPConfig dark theme (and the stock
default baseline), entirely locally.

For the dark theme this is NOT a hand-written page: the theme's own
main.tpl.htm / main_login.tpl.htm / topnav.tpl.htm are rendered by a small
vlibTemplate-compatible engine with the same variables ISPConfig supplies,
then the server-injected regions (#topnav-container, #sidebar, #pageContent)
are filled with captured/synthesized content fragments from fragments/.
So the stylesheet set, its order, AND the shell markup are precisely what
the live panel would serve.

    python3 build.py            # build webroot/
    python3 build.py --shoot    # build + screenshot (needs playwright)
"""
import re
import shutil
import subprocess
import sys
from pathlib import Path

HERE = Path(__file__).resolve().parent
REPO = HERE.parent
STOCK = REPO / ".refs/ispconfig3/interface/web"
DARK = REPO / "themes/clarity"
FRAG = HERE / "fragments"
WEBROOT = HERE / "webroot"
SHOTS = HERE / "shots"

LOGO_DEFAULT = ("<div id='logo' style=\"background: url(themes/default/assets/images/logo.png) "
                "no-repeat;width:148px;height:40px\"><a href='#'></a></div>")

# ---------------------------------------------------------------------------
# mini vlibTemplate engine (the subset the clarity templates use)
# ---------------------------------------------------------------------------

_IF_RE = re.compile(
    r"<tmpl_if\s+(?P<attrs>[^>]*?)>(?P<body>(?:(?!<tmpl_if\b).)*?)</tmpl_if>",
    re.S | re.I)
_LOOP_RE = re.compile(
    r"<tmpl_loop\s+name=['\"](?P<name>[^'\"]+)['\"]\s*>(?P<body>.*?)</tmpl_loop>",
    re.S | re.I)
_ATTR_RE = re.compile(r"(\w+)=['\"]([^'\"]*)['\"]")


def _render_ifs(text: str, vars: dict) -> str:
    while True:
        m = _IF_RE.search(text)
        if m is None:
            return text
        attrs = dict(_ATTR_RE.findall(m.group("attrs")))
        body = m.group("body")
        assert "<tmpl_elseif" not in body, "tmpl_elseif not supported by mockup engine"
        then, _, other = body.partition("<tmpl_else>")
        val = vars.get(attrs.get("name", ""), "")
        if "value" in attrs:
            op = attrs.get("op", "==")
            assert op in ("==", "!="), f"tmpl_if op={op!r} not supported by mockup engine"
            cond = (str(val) == attrs["value"]) if op == "==" else (str(val) != attrs["value"])
        else:
            cond = bool(val)
        text = text[:m.start()] + (then if cond else other) + text[m.end():]


def render_tpl(text: str, vars: dict) -> str:
    def do_loop(m):
        rows = vars.get(m.group("name"), []) or []
        return "".join(render_tpl(m.group("body"), {**vars, **row}) for row in rows)
    text = _LOOP_RE.sub(do_loop, text)
    text = _render_ifs(text, vars)
    text = re.sub(r"<tmpl_dyninclude\s+name=['\"]content_tpl['\"]\s*/?>",
                  lambda m: vars.get("content_tpl", ""), text)
    text = re.sub(r"<tmpl_var\s+name=['\"]([^'\"]+)['\"]\s*/?>",
                  lambda m: str(vars.get(m.group(1), "")), text)
    text = re.sub(r"\{tmpl_var\s+name=['\"]([^'\"]+)['\"]\}",
                  lambda m: str(vars.get(m.group(1), "")), text)
    return text


# ---------------------------------------------------------------------------
# page data
# ---------------------------------------------------------------------------

MODULES = [  # (title, module, icon)
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

BASE_VARS = {
    "company_name": "", "app_title": "ISPConfig", "app_link": "#",
    "current_theme": "clarity",
    "logged_in": "y", "cpuser": "admin", "usertype": "normaluser",
    "logout_txt": "Logout", "startpage": "dashboard/dashboard.php",
    "datalog_changes_count": "3",
    "datalog_changes_txt": "Changes that have not yet been applied",
    "datalog_changes_close_txt": "Close",
    "datalog_changes": [],
    "globalsearch_searchfield_watermark_txt": "Search",
    "globalsearch_resultslimit_of_txt": "of",
    "globalsearch_resultslimit_results_txt": "results",
    "globalsearch_noresults_text_txt": "No results.",
    "globalsearch_noresults_limit_txt": "Raise limit",
    "tabchange_discard_enabled": "", "tabchange_warning_enabled": "",
    "global_tabchange_warning_txt": "", "global_tabchange_discard_txt": "",
    "js_d_includes": [],
    "custom_login": "",
}

DARK_PAGES = {  # name -> (active module, sidebar fragment, pageContent fragment)
    "dark-dashboard":  ("dashboard", "news.html", "dashboard.html"),
    "dark-sites":      ("sites", "sidenav-sites.html", "sites-list.html"),
    "dark-form":       ("mail", "sidenav-mail.html", "mail-user-form.html"),
    "dark-components": ("dashboard", "news.html", "components.html"),
}

# The metrics dashlet renders through <canvas>, so the harness (which strips
# all <script> to avoid 404s from missing ISPConfig core JS) can't draw it.
# For the dashboard page we re-inject a controlled bootstrap: real Chart.js,
# the SHIPPED nz-theme.js (so the screenshot proves the actual chart plugin),
# and the stock createChart verbatim — animation off for a deterministic shot.
CHART_BOOTSTRAP = """
<script src='js/chartjs/chart.umd.js'></script>
<script src='themes/clarity/assets/javascripts/nz-theme.js'></script>
<script>
Chart.defaults.animation = false;
document.addEventListener('DOMContentLoaded', function () {
  var L = ['','','','','','','','','','','',''];
  createChart('loadchart', 'Server load (1 min)', L,
              [0.42,0.55,0.48,0.71,0.62,0.90,1.15,0.88,0.64,0.70,0.52,0.61]);
  createChart('memchart', 'Memory usage (%)', L,
              [38,41,40,45,52,58,71,64,60,55,47,49]);
});
function createChart(chartname, label, labels, data) {
    var ctx = document.getElementById(chartname).getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: { labels: labels, datasets: [{
            label: label, data: data, borderWidth: 1, tension: 0.4,
            cubicInterpolationMode: 'default',
            borderColor: 'rgb(75, 192, 192)',
            backgroundColor: 'rgba(75, 192, 192, 0.2)', fill: true }] },
        options: {
            scales: { x: { display: false }, y: { beginAtZero: true } },
            plugins: { legend: { labels: { generateLabels: function(chart) {
                return chart.data.datasets.map(function(dataset, i) {
                    return { text: dataset.label, fillStyle: 'white',
                        hidden: !chart.isDatasetVisible(i), strokeStyle: 'white',
                        pointStyle: 'white', lineWidth: dataset.borderWidth,
                        datasetIndex: i };
                });
            } } } }
        }
    });
}
</script>
"""

PAGE_SCRIPTS = {"dark-dashboard": CHART_BOOTSTRAP}


def nav_top(active: str):
    return [{"title": t, "module": m, "icon": i, "active": "1" if m == active else ""}
            for (t, m, i) in MODULES]


def strip_scripts(html: str) -> str:
    return re.sub(r"<script\b.*?</script>", "", html, flags=re.S | re.I)


def fix_paths(html: str) -> str:
    html = html.replace("href='/themes/", "href='themes/").replace('href="/themes/', 'href="themes/')
    html = html.replace("content='/themes/", "content='themes/")
    html = html.replace("'../themes/", "'themes/").replace('"../themes/', '"themes/')
    html = html.replace("'../js/", "'js/").replace('"../js/', '"js/')
    return html


def fill(html: str, container_re: str, content: str) -> str:
    new, n = re.subn(container_re, lambda m: m.group(1) + content + m.group(2), html, count=1, flags=re.S)
    if n != 1:
        raise SystemExit(f"container not found: {container_re}")
    return new


def build_dark_page(name: str, active: str, sidebar_frag: str, content_frag: str) -> str:
    shell = (DARK / "templates/main.tpl.htm").read_text(encoding="utf-8")
    page = render_tpl(shell, BASE_VARS)  # BASE_VARS already sets startpage; render_tpl never mutates its vars

    topnav = render_tpl((DARK / "templates/topnav.tpl.htm").read_text(encoding="utf-8"),
                        {"nav_top": nav_top(active)})
    page = fill(page, r"(<div id='topnav-container'>)\s*(</div>)", "\n" + topnav + "\n")
    page = fill(page, r"(<div id='sidebar' class='nz-context'>)\s*(</div>)",
                "\n" + (FRAG / sidebar_frag).read_text(encoding="utf-8") + "\n")
    page = fill(page, r"(<div id=\"pageContent\"[^>]*>)<!-- AJAX CONTENT -->(</div>)",
                (FRAG / content_frag).read_text(encoding="utf-8"))
    # the datalog chip is JS-toggled at runtime; show it in the static shot
    page = page.replace('class="notification" data-toggle="modal" data-target="#datalogModal" style="display: none;"',
                        'class="notification" data-toggle="modal" data-target="#datalogModal"', 1)
    # strip the shell's scripts (missing core JS), then re-inject only the
    # controlled per-page bootstrap (e.g. the chart renderer for the dashboard)
    page = strip_scripts(page)
    extra = PAGE_SCRIPTS.get(name, "")
    if extra:
        page = page.replace("</body>", extra + "\n</body>", 1)
    return fix_paths(page)


def build_dark_login() -> str:
    shell = (DARK / "templates/main_login.tpl.htm").read_text(encoding="utf-8")
    page = render_tpl(shell, {**BASE_VARS,
                              "content_tpl": (FRAG / "login.html").read_text(encoding="utf-8")})
    return fix_paths(strip_scripts(page))


# ---------------------------------------------------------------------------
# stock default baseline — captured body.html + stock theme head
# ---------------------------------------------------------------------------

def head_from(template: Path, theme: str) -> str:
    src = template.read_text(encoding="utf-8")
    head = src[: src.index("</head>") + len("</head>")]
    head = re.sub(r"<tmpl_if name='logged_in' value='n'>.*?</tmpl_if>", "", head, flags=re.S)
    head = re.sub(r"<tmpl_var name=['\"]current_theme['\"]>", theme, head)
    head = re.sub(r"<tmpl_var name=['\"][^'\"]+['\"]>", "", head)
    head = re.sub(r"\{tmpl_var name=[^}]+\}", "", head)
    return fix_paths(head)


def build() -> None:
    if WEBROOT.exists():
        shutil.rmtree(WEBROOT)
    (WEBROOT / "themes").mkdir(parents=True)
    (WEBROOT / "themes/default").symlink_to(STOCK / "themes/default")
    (WEBROOT / "themes/clarity").symlink_to(DARK)
    # vendor JS (Chart.js) for the pages that re-inject a controlled bootstrap
    (WEBROOT / "js").symlink_to(STOCK / "js")

    # stock baseline for comparison
    body = strip_scripts((HERE / "body.html").read_text(encoding="utf-8"))
    head = head_from(STOCK / "themes/default/templates/main.tpl.htm", "default")
    page = f"{head}\n<body>\n{body.replace('<!--LOGO-->', LOGO_DEFAULT)}\n</body>\n</html>\n"
    (WEBROOT / "default.html").write_text(page, encoding="utf-8")

    # clarity: real shell render
    for name, (active, sidebar_frag, content_frag) in DARK_PAGES.items():
        (WEBROOT / f"{name}.html").write_text(
            build_dark_page(name, active, sidebar_frag, content_frag), encoding="utf-8")
    (WEBROOT / "dark-login.html").write_text(build_dark_login(), encoding="utf-8")

    # light-mode variants: same pages with the switcher attribute pre-set
    # (statically, since mockup pages ship without scripts)
    for src, dst in (("dark-dashboard", "light-dashboard"), ("dark-login", "light-login")):
        page = (WEBROOT / f"{src}.html").read_text(encoding="utf-8")
        (WEBROOT / f"{dst}.html").write_text(
            page.replace("<html lang='en'>", "<html lang='en' data-nz-theme='light'>", 1),
            encoding="utf-8")

    # report stylesheet resolution for every page
    for f in sorted(WEBROOT.glob("*.html")):
        html = f.read_text(encoding="utf-8")
        links = re.findall(r"<link rel='stylesheet' href='([^']+)'", html)
        missing = [l for l in links if not (WEBROOT / l.split("?")[0]).exists()]
        print(f"  {f.name}: {len(links)} stylesheets, {len(missing)} missing")
        for l in missing:
            print(f"      MISS {l}")


SHOT_MATRIX = [
    # (page, viewports)
    ("dark-dashboard", ("desktop", "mobile")),
    ("dark-sites", ("desktop",)),
    ("dark-form", ("desktop",)),
    ("dark-components", ("desktop",)),  # QA gallery, not a marketing shot
    ("dark-login", ("desktop", "mobile")),
    ("light-dashboard", ("desktop",)),
    ("light-login", ("desktop",)),
    ("default", ("desktop",)),
]

VIEWPORTS = {"desktop": (1440, 900), "mobile": (390, 844)}


def shoot(only: str = "") -> None:
    from playwright.sync_api import sync_playwright

    SHOTS.mkdir(exist_ok=True)
    srv = subprocess.Popen([sys.executable, "-m", "http.server", "8899", "-d", str(WEBROOT)],
                           stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        with sync_playwright() as p:
            b = p.chromium.launch()
            for name, labels in SHOT_MATRIX:
                if only and only not in name:
                    continue
                for label in labels:
                    w, h = VIEWPORTS[label]
                    pg = b.new_page(viewport={"width": w, "height": h}, reduced_motion="reduce")
                    errors = []
                    pg.on("requestfailed", lambda r: errors.append(r.url))
                    pg.goto(f"http://127.0.0.1:8899/{name}.html", wait_until="networkidle")
                    pg.add_style_tag(content="*,*::before,*::after{"
                                             "animation:none!important;transition:none!important;"
                                             "caret-color:transparent!important}")
                    pg.wait_for_timeout(400)
                    out = SHOTS / f"{name}-{label}.png"
                    pg.screenshot(path=str(out), full_page=(label == "desktop"))
                    print(f"  {out.name}  ({len(errors)} failed requests)")
                    for e in errors[:6]:
                        print(f"      404 {e}")
                    pg.close()
            b.close()
    finally:
        srv.terminate()


if __name__ == "__main__":
    print("building webroot/")
    build()
    if "--shoot" in sys.argv:
        print("\nscreenshotting")
        only = next((a.split("=", 1)[1] for a in sys.argv if a.startswith("--only=")), "")
        shoot(only)
    print("\ndone")
