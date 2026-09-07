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
    # The Branding redesign lives in its own directory with the stylesheet it
    # would ship inlined into; fragment paths are resolved against fragments/,
    # so a sibling directory is reachable without teaching the engine anything.
    "dark-branding":   ("tools", "../branding/sidenav-tools.html", "../branding/branding.html"),
    # ...and the SHIPPED template beside it, rendered from
    # interface/web/customizer/templates/customizer_edit.htm with the wordbook
    # and the mockup's sample brand substituted for its tmpl_vars, so the built
    # page can be compared against branding/shots/ shot for shot. The content is
    # built rather than read from fragments/, which is why this value is a
    # callable.
    "dark-branding-shipped": ("tools", "../branding/sidenav-tools.html", None),
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
  createChart('memchart', 'Memory usage %', L,
              [38,41,40,45,52,58,71,64,60,55,47,49]);
  createChart('rxchart', 'Network In/kB', L,
              [3.2,2.8,4.1,3.6,8.4,12.7,9.2,5.5,4.8,6.1,5.2,4.4]);
  createChart('txchart', 'Network Out/kB', L,
              [1.1,0.9,1.4,1.2,2.9,4.2,3.1,1.8,1.5,2.0,1.7,1.5]);
});
// mirrors themes/clarity/templates/dashboard/metrics.htm — keep in sync
function nzStatFmt(n) {
    n = Number(n);
    if (!isFinite(n)) return '—';
    return Math.abs(n) >= 100 ? String(Math.round(n)) : String(Math.round(n * 10) / 10);
}
function createChart(chartname, label, labels, data) {
    var el = document.getElementById(chartname);
    if (!el) return;
    var last = data.length ? data[data.length - 1] : null;
    var v = document.getElementById('nz-stat-' + chartname);
    if (v && last !== null) v.textContent = nzStatFmt(last);
    var chips = document.getElementById('nz-dash-chips');
    var chipNames = { loadchart: 'Load', memchart: 'Memory', rxchart: 'Net in', txchart: 'Net out' };
    if (chips && last !== null && chipNames[chartname]) {
        var chip = document.createElement('span');
        chip.className = 'nz-chip';
        var k = document.createElement('span');
        k.className = 'nz-chip-k';
        k.textContent = chipNames[chartname];
        chip.appendChild(k);
        chip.appendChild(document.createTextNode(nzStatFmt(last)));
        chips.appendChild(chip);
    }
    new Chart(el.getContext('2d'), {
        type: 'line',
        data: { labels: labels, datasets: [{
            label: label, data: data, borderWidth: 1.5, tension: 0.35,
            pointRadius: 0, pointHoverRadius: 3, fill: true }] },
        options: {
            maintainAspectRatio: false,
            scales: { x: { display: false },
                      y: { beginAtZero: true, ticks: { maxTicksLimit: 4 } } },
            plugins: { legend: { display: false } }
        }
    });
}
</script>
"""

# ---------------------------------------------------------------------------
# the SHIPPED Branding page
#
# interface/web/customizer/templates/customizer_edit.htm rendered here rather
# than the hand-written branding/branding.html, so what is reviewed is the file
# that ships. Its tmpl_vars come from the English wordbooks — the same strings
# tform resolves — plus the mockup's invented brand (Karoo Hosting) for the
# stored values, so the two pages can be compared shot for shot.
#
# The server-rendered slots (the marks, the "also used on" strips, the favicon
# row) are lifted out of branding.html: lib/preview.inc.php builds that markup
# on a live panel and there is no PHP here to run it.
# ---------------------------------------------------------------------------

BRANDING_TPL = REPO / "interface/web/customizer/templates/customizer_edit.htm"
BRANDING_WB = [REPO / "interface/web/customizer/lib/lang/en_customizer.lng",
               REPO / "interface/web/customizer/lib/lang/en.lng"]
BRANDING_MOCKUP = HERE / "branding/branding.html"

# The .lng files are PHP, and are parsed as TEXT here for the same reason
# .github/scripts/lang_check.php does it: nothing in this harness may execute a
# translation file.
_WB_RE = re.compile(r"\$wb\['([^']+)'\]\s*=\s*'((?:[^'\\]|\\.)*)'\s*;")


def wordbook() -> dict:
    out = {}
    for f in BRANDING_WB:
        for m in _WB_RE.finditer(f.read_text(encoding="utf-8")):
            out[m.group(1)] = m.group(2).replace("\\'", "'").replace("\\\\", "\\")
    return out


def mockup_slot(slot_id: str) -> str:
    """The server-rendered contents of one preview slot, out of the mockup."""
    html = BRANDING_MOCKUP.read_text(encoding="utf-8")
    m = re.search(r'id="%s">(.*?)</div>' % re.escape(slot_id), html, re.S)
    if m is None:
        raise SystemExit(f"no #{slot_id} slot in branding.html")
    # The "Also used on" label lives OUTSIDE the slot on the shipped page — the
    # preview endpoint replaces the slot's innerHTML wholesale — so the mockup's
    # copy of it inside the slot is dropped here rather than shown twice.
    return re.sub(r'<span class="nz-surfacelabel">.*?</span>', "", m.group(1), flags=re.S)


def mockup_navmark(cls: str) -> str:
    html = BRANDING_MOCKUP.read_text(encoding="utf-8")
    m = re.search(r'class="[^"]*%s"[^>]*>\s*<img class="nz-prev-navmark" src="([^"]+)"' % re.escape(cls),
                  html, re.S)
    if m is None:
        raise SystemExit(f"no .{cls} nav mark in branding.html")
    return m.group(1)


# The stored values. Deliberately not clarity's own palette: a preview painted
# in the active design's colours proves nothing.
SAMPLE = {
    "company_name": "Karoo Hosting",
    "accent_hex": "#2E7D6B", "rail_hex": "#123A34",
    "rail_hex_light": "#E6EEEC", "login_bg": "#0E2723",
    "logo_url": "", "logo_url_on_dark": "/themes/custom/karoo-on-dark.svg",
    "favicon_url": "",
    "custom_login_text": "Support: help@karoo.example",
    "custom_login_link": "https://karoo.example/support",
    "id": "1", "field_errors_json": "{}",
}
SAMPLE_RATIOS = {"nz-accent-ratio": "4.93:1", "nz-rail-ratio": "12.50:1",
                 "nz-rail-light-ratio": "17.79:1", "nz-login-ratio": "15.75:1"}

# What tform emits for a select and a checkbox — only the <option> tags and the
# bare <input> come from it; the shells are in the template.
_OPTIONS = ("<option value='auto' selected='selected'>Automatic — match the background</option>"
            "<option value='on_light'>Always the mark for light backgrounds</option>"
            "<option value='on_dark'>Always the mark for dark backgrounds</option>")


def _switch(name: str, on: bool) -> str:
    return ("<input name=\"%s\" id=\"%s\" value=\"y\" type=\"checkbox\"%s />"
            % (name, name, " CHECKED" if on else ""))


def shipped_branding() -> str:
    wb = wordbook()
    v = dict(wb)
    # publish_hint_labels() in customizer_edit.php, in one line: every "?" gets
    # hint_more_txt with the label of the thing it explains substituted in.
    for key, val in list(wb.items()):
        if key.endswith("_txt"):
            v["hint_" + key] = wb["hint_more_txt"].replace("%s", val)
    # publish_brand_summary() — customizer_brand_summary()'s three facts for
    # this sample: one design installed, two marks supplied, a favicon set.
    v["summary_fact_design"] = wb["summary_design_txt"].replace("%s", "Clarity")
    v["summary_fact_marks"] = wb["summary_marks_txt"].replace("%d", "2")
    v["summary_fact_favicon"] = wb["summary_favicon_txt"]
    v.update(SAMPLE)
    for slot in ("used_logo", "used_logo_on_dark", "used_logo_more",
                 "used_logo_on_dark_more", "used_favicon"):
        v[slot] = mockup_slot(slot)
    v["logo_variant_nav"] = _OPTIONS
    v["logo_variant_login"] = _OPTIONS
    for name, on in (("show_design_picker", True), ("show_version", False),
                     ("show_news_feed", True), ("show_donation_dashlet", False),
                     ("show_ispconfig_credit", True), ("show_theme_credit", True)):
        v[name] = _switch(name, on)
    return render_tpl(BRANDING_TPL.read_text(encoding="utf-8"), v)


CONTENT_BUILDERS = {"dark-branding-shipped": shipped_branding}

# The page's own <script> is stripped with every other script in this harness,
# and there is no PHP endpoint behind it either, so this stands in for both:
# it writes exactly what the mockup wrote as inline style attributes, and
# nothing more. It is NOT a second implementation of the page's JS — no
# listeners, no fetch — it only puts the sample brand on screen so the built
# page and branding/shots/ can be compared.
BRANDING_SHIPPED_BOOTSTRAP = """
<script>
(function () {
  var NAV_MARK = '%(navmark)s';
  var NAV_MARK_LIGHT = '%(navmark_light)s';
  var NAME = '%(name)s';
  function paint(sel, bg, ink) {
    var els = document.querySelectorAll(sel), i;
    for (i = 0; i < els.length; i++) {
      if (bg) els[i].style.background = bg;
      if (ink) els[i].style.color = ink;
    }
  }
  paint('.nz-prev-rail', '%(rail)s', '#FFFFFF');
  paint('.nz-prev-rail-light', '%(rail_light)s', '%(rail)s');
  paint('.nz-prev-login', '%(login)s', '#FFFFFF');
  paint('.nz-prev-accent', '%(accent)s', '#FFFFFF');
  var rules = document.querySelectorAll('.nz-prev-accent-rule'), i;
  for (i = 0; i < rules.length; i++) rules[i].style.color = '%(accent)s';
  var names = document.querySelectorAll('.nz-prev-name');
  for (i = 0; i < names.length; i++) names[i].textContent = NAME;
  // paintNavBrand(): the resolved mark replaces the name in a brand slot.
  function mark(sel, src) {
    var slot = document.querySelector(sel);
    if (!slot) return;
    var img = document.createElement('img');
    img.className = 'nz-prev-navmark';
    img.setAttribute('alt', '');
    img.setAttribute('src', src);
    slot.insertBefore(img, slot.firstChild);
    var n = slot.querySelector('.nz-prev-name');
    if (n) n.hidden = true;
  }
  mark('.nz-prev-nav-brand', NAV_MARK);
  mark('.nz-prev-nav-brand-light', NAV_MARK_LIGHT);
  // paintSurfaces() reveals the light sample only when rail_hex_light is set.
  var pane = document.querySelector('.nz-prev-light-pane');
  if (pane) pane.hidden = false;
  var ratios = %(ratios)s;
  Object.keys(ratios).forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.textContent = ratios[id];
  });
  var chips = document.querySelectorAll('.nz-brandchip-hex[data-hex-field]');
  for (i = 0; i < chips.length; i++) {
    var f = document.getElementById(chips[i].getAttribute('data-hex-field'));
    if (f && f.value) chips[i].textContent = f.value.toUpperCase();
  }
  var flash = document.getElementById('nz-save-flash');
  if (flash) flash.innerHTML = '<div class="alert alert-success clear">%(saved)s</div>';
})();
</script>
"""


def _branding_bootstrap() -> str:
    import json
    wb = wordbook()
    return BRANDING_SHIPPED_BOOTSTRAP % {
        "navmark": mockup_navmark("nz-prev-nav-brand"),
        "navmark_light": mockup_navmark("nz-prev-nav-brand-light"),
        "name": SAMPLE["company_name"],
        "rail": SAMPLE["rail_hex"], "rail_light": SAMPLE["rail_hex_light"],
        "login": SAMPLE["login_bg"], "accent": SAMPLE["accent_hex"],
        "ratios": json.dumps(SAMPLE_RATIOS),
        "saved": wb.get("settings_saved_txt", "Changes saved."),
    }


PAGE_SCRIPTS = {"dark-dashboard": CHART_BOOTSTRAP,
                "dark-branding-shipped": _branding_bootstrap(),
                "light-branding-shipped": _branding_bootstrap()}


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
    content = (CONTENT_BUILDERS[name]() if content_frag is None
               else (FRAG / content_frag).read_text(encoding="utf-8"))
    page = fill(page, r"(<div id=\"pageContent\"[^>]*>)<!-- AJAX CONTENT -->(</div>)", content)
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
    # branding/: the Branding redesign's own directory, so its fragment can link
    # branding/branding.css the way the shipping template will inline it
    (WEBROOT / "branding").symlink_to(HERE / "branding")

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
    for src, dst in (("dark-dashboard", "light-dashboard"), ("dark-login", "light-login"),
                     ("dark-branding", "light-branding"),
                     ("dark-branding-shipped", "light-branding-shipped")):
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
    ("dark-branding", ("desktop", "fold", "narrow")),
    ("light-branding", ("desktop", "fold")),
    ("dark-branding-shipped", ("desktop", "fold", "narrow")),
    ("light-branding-shipped", ("desktop", "fold")),
    ("light-dashboard", ("desktop",)),
    ("light-login", ("desktop",)),
    ("default", ("desktop",)),
]

# "narrow" is the width at which the Branding page's container query collapses
# it to one column — the rail takes 248px, so the page itself has ~700px there.
VIEWPORTS = {"desktop": (1440, 900), "mobile": (390, 844),
             "narrow": (1000, 900), "fold": (1440, 900)}

# Labels shot at the viewport rather than full-page. A sticky footer renders at
# the fold in a full-page capture, so a page that has one needs both: the
# full-page pull for reviewing the whole layout, and the fold for what an
# operator actually sees.
VIEWPORT_ONLY = ("mobile", "fold")

# A page whose shots belong beside its own source rather than in shots/.
SHOT_DIRS = {"dark-branding": HERE / "branding/shots",
             "light-branding": HERE / "branding/shots"}


def shoot(only: str = "", dest_root: Path = None) -> None:
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
                    if label not in VIEWPORT_ONLY:
                        # A sticky footer is painted at the fold in a full-page
                        # capture and hides what is behind it; the fold shot is
                        # where it is shown doing its job.
                        pg.add_style_tag(content="#nz-brandpage .nz-actions{position:static!important}")
                    pg.wait_for_timeout(400)
                    dest = dest_root or SHOT_DIRS.get(name, SHOTS)
                    dest.mkdir(parents=True, exist_ok=True)
                    out = dest / f"{name}-{label}.png"
                    pg.screenshot(path=str(out), full_page=(label not in VIEWPORT_ONLY))
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
        # A review pass wants its shots somewhere other than beside the design
        # record; --shots-dir sends every capture of this run to one directory.
        dest = next((Path(a.split("=", 1)[1]) for a in sys.argv if a.startswith("--shots-dir=")), None)
        shoot(only, dest)
    print("\ndone")
