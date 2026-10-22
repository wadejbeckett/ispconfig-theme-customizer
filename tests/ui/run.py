#!/usr/bin/env python3
"""Offline Clarity UI regression checks using the real theme and core templates.

Needs Playwright Firefox (or --browser chromium) and an ISPConfig source checkout, not a running panel. All browser requests are fulfilled in memory. --output must be outside the repo.

--theme-ref reads an older git revision to demonstrate regressions; the same checks deliberately fail there. No renderer/source/build directory is rewritten.

--billing-web points at a licensed ISPConfig Billing Module's interface/web directory and replaces the synthetic billing fixture with the module's own dashboard and invoice-item templates and its bundled jqPlot. The module is commercial and is never copied into this repository.
"""

import argparse
import hashlib
import importlib.util
import json
import mimetypes
from pathlib import Path
import re
import subprocess
import sys
import tempfile
from urllib.parse import parse_qs, unquote, urlsplit

sys.dont_write_bytecode = True
REPO = Path(__file__).resolve().parents[2]
ORIGIN = "https://clarity.test"
NAMES = ("dashboard", "dns", "limits", "billing")
MONTHS = ("Nov", "Dec", "Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct")

# The jqPlot chart the Billing Module draws on its dashboard (#chart1, inline 720x150), as jqPlot 1.0 builds it: full-size base and grid canvases, plot-area series, highlight and event canvases, absolutely positioned tick labels, and the colours from jqPlot's own stylesheet, which loads after the theme's.
JQPLOT_CSS = "<style>.jqplot-target{position:relative;color:#666;font-family:'Trebuchet MS',Arial,sans-serif}.jqplot-axis{font-size:.75em}.jqplot-xaxis-tick{position:absolute;top:0}.jqplot-highlighter-tooltip{position:absolute;border:1px solid #ccc;background:rgba(208,208,208,.5)}</style>"
# Stock monitor chart markup (show_sys_state.htm): the Chart.js strip the theme's canvas rules exist for.
CHARTJS_STRIP = "<div><h3>Server load</h3><div style='padding-bottom:10px;'><canvas height='30' id='ui-chartjs' style='background-color: white;'></canvas></div></div>"


def git(path, *args):
    return subprocess.check_output(["git", "-C", str(path), *args])


def contrast(foreground, background):
    # WCAG 2.1 contrast ratio between two computed rgb()/rgba() colours; alpha is ignored.
    def luminance(colour):
        channels = [float(v) / 255 for v in re.findall(r"[\d.]+", colour)[:3]]
        r, g, b = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in channels]
        return 0.2126 * r + 0.7152 * g + 0.0722 * b
    light, dark = sorted((luminance(foreground), luminance(background)), reverse=True)
    return (light + 0.05) / (dark + 0.05)


def plain_table(table_id, count, attrs=""):
    # No sortable header: the shape of a dashlet table and of a module's working table alike.
    body = "".join(f"<tr><td>{i}</td><td>Item {i}</td><td>{i * 10}.00</td></tr>" for i in range(1, count + 1))
    return f"<div class='table-wrapper'><table class='table' id='{table_id}'{attrs}><thead class='dark form-group-sm'><tr><th>Qty</th><th>Description</th><th>Sum</th></tr></thead><tbody>{body}</tbody></table></div>"


def synthetic_jqplot():
    def canvas(cls, width, height, left, top):
        return f"<canvas class='{cls}' width='{width}' height='{height}' style='position:absolute;left:{left}px;top:{top}px'></canvas>"
    ticks = "".join(f"<div class='jqplot-xaxis-tick' style='left:{20 + 57 * i}px'>{month}</div>" for i, month in enumerate(MONTHS))
    layers = [canvas("jqplot-base-canvas", 720, 150, 0, 0), f"<div class='jqplot-axis jqplot-xaxis' style='position:absolute;left:30px;top:130px;width:683px;height:16px'>{ticks}</div>", canvas("jqplot-grid-canvas", 720, 150, 0, 0)]
    layers += [canvas(cls, 683, 115, 30, 10) for cls in ("jqplot-series-shadowCanvas", "jqplot-series-canvas", "jqplot-highlight-canvas", "jqplot-event-canvas")]
    return JQPLOT_CSS + "<div id='chart1' class='jqplot-target' style='margin-top:0px; width:720px; height:150px;'>" + "".join(layers) + "<div class='jqplot-highlighter-tooltip' style='left:100px;top:20px'>120.00</div></div>"


class Fixture:
    def __init__(self, core, theme_ref, billing=None):
        self.core = core.resolve()
        self.theme_ref = theme_ref
        self.billing = billing.resolve() if billing else None
        self.hashes = {}
        spec = importlib.util.spec_from_file_location("mockup", REPO / "mockup/build.py")
        self.renderer = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(self.renderer)

    def source(self, relative, theme=False):
        root = REPO if theme else self.core
        path = (root / relative).resolve()
        if not path.is_relative_to(root):
            raise ValueError("Source outside its declared root")
        data = git(REPO, "show", f"{self.theme_ref}:{relative}") if theme and self.theme_ref else path.read_bytes()
        self.hashes[("theme/" if theme else "core/") + relative] = hashlib.sha256(data).hexdigest()
        return data

    def billing_source(self, relative):
        path = (self.billing / relative).resolve()
        if not path.is_relative_to(self.billing):
            raise ValueError("Source outside the billing module")
        data = path.read_bytes()
        self.hashes["billing/" + relative] = hashlib.sha256(data).hexdigest()
        return data

    def billing_content(self):
        if not self.billing:
            return synthetic_jqplot() + "<h3>Invoice items</h3><div id='ui-items'>" + plain_table("ui-items-table", 15) + "</div>"
        income = ",".join(str(100 + 40 * i) for i in range(len(MONTHS)))
        records = [{"invoice_id": str(i), "invoice_number": f"R-{i:04d}", "invoice_date": "2026-10-01", "invoice_amount": "120.00"} for i in range(1, 4)]
        dashboard = self.render(self.billing_source("billing/templates/billing_dashboard.htm").decode(), {"income": income, "ticks": "','".join(MONTHS), "income_txt": "Income", "currency_txt": "EUR", "has_invoice_records": True, "invoice_records": records, "has_invoice_due": True, "invoice_due": records})
        # The module closes one else branch as </tmpl_else>, which core's parser tolerates and the mockup renderer does not.
        items = self.billing_source("billing/templates/invoice_edit_item_list.htm").decode().replace("</tmpl_else>", "<tmpl_else>")
        lines = [{"invoice_item_id": str(i), "quantity": "1", "description": f"Hosting item {i}", "price": "10.00", "vat": "17", "sum": "11.70"} for i in range(1, 16)]
        items = self.render(items, {"has_invoice_items": True, "invoice_items": lines, "currency_txt": "EUR", "edit_txt": "Edit", "del_txt": "Delete"})
        return dashboard + "<h3>Invoice items</h3><div id='ui-items'>" + items + "</div>"

    def render(self, text, values):
        # These fixtures have records and no pending changes. The existing mockup engine handles the remaining native template directives.
        text = re.sub(r'<tmpl_unless name="records">.*?</tmpl_unless>', "", text, flags=re.S)
        text = text.replace("name='datalog_changes_count' op='>' value='0'", "name='datalog_changes_count'")
        result = self.renderer.render_tpl(text, values)
        if re.search(r"</?tmpl_|\{tmpl_", result):
            raise ValueError("Unrendered directive in fixture")
        return result

    def wordbook(self, relative):
        # Parse literal PHP assignments as text, never include/eval translations.
        pattern = r"^\$wb\['([^']+)'\]\s*=\s*'((?:\\.|[^'\\])*)';\s*$"
        return {key: value.replace("\\'", "'").replace("\\\\", "\\") for key, value in re.findall(pattern, self.source(relative).decode(), re.M)}

    def nav(self, active, tools=True):
        rows = [row for row in self.renderer.nav_top(active) if tools or row["module"] != "tools"]
        return self.render(self.source("themes/clarity/templates/topnav.tpl.htm", True).decode(), {"nav_top": rows})

    def tabbed(self, content, title, module):
        tabs = [{"name": "general", "title": "General", "active": ""}, {"name": "details", "title": "Records" if module == "dns" else "Limits", "active": "1"}]
        return self.render(self.source("themes/default/templates/tabbed_form.tpl.htm").decode(), {"content_tpl": content, "form_hint": title, "formTab": tabs, "app_module": module, "form_action": module + "_edit.php"})

    def legacy_footers(self):
        footers = []
        for path in ("dns/templates/dns_wizard.htm", "sites/templates/aps_install_package.htm"):
            match = re.search(r'<div class="clear">\s*<div class="right">.*?</div>\s*</div>', self.source(path).decode(), re.S)
            if not match:
                raise ValueError("Native inline-submit footer not found: " + path)
            footers.append(self.render(match[0], {"btn_save_txt": "Save", "btn_cancel_txt": "Cancel", "btn_install_txt": "Install", "pkg_id": "1"}))
        return "".join(footers)

    def content(self, name):
        if name == "dashboard":
            dashlets = [{"content": plain_table("ui-dashlet", 15)}, {"content": plain_table("ui-dashlet-nocap", 15, " data-nz-nocap")}]
            return self.render(self.source("themes/clarity/templates/dashboard/dashboard.htm", True).decode(), {"welcome_user": "Welcome admin", "info": [{"info_msg": "An ISPConfig update is available. Please update your installation."}], "leftcol": dashlets})
        if name == "billing":
            return self.billing_content() + CHARTJS_STRIP
        if name == "dns":
            values = {**self.wordbook("dns/lib/lang/en_dns_a.lng"), "parent_id": "1", "datalog_changes_count": 0, "search_active": "<option>All</option>", "search_type": "<option>All</option>", "records": [{"id": "1", "active": "Yes", "type": "A", "type_lowercase": "a", "name": "example.test.", "data": "192.0.2.1", "aux": "0", "ttl": "3600"}]}
            listing = self.render(self.source("dns/templates/dns_a_list.htm").decode(), values)
            content = self.render(self.source("dns/templates/dns_records_edit.htm").decode(), {"id": "1", "dns_records": listing})
            return self.tabbed(content, "DNS Zone", "dns")
        template = self.source("client/templates/client_edit_limits.htm").decode()
        values = self.wordbook("client/lib/lang/en_client.lng")
        for key in re.findall(r"tmpl_var\s+name=['\"]([^'\"]+)", template):
            if key.endswith(("_txt", "_placeholder")):
                values.setdefault(key, key.removesuffix("_txt").replace("_", " ").capitalize())
            elif key.startswith("limit_"):
                values.setdefault(key, "-1")
        for key in ("web_servers", "mail_servers", "db_servers", "dns_servers", "xmpp_servers"):
            values[key] = "<option selected value='1'>panel.example.test</option>"
        for key in ("limit_cgi", "limit_ssi", "limit_perl", "limit_ruby", "limit_python", "force_suexec", "limit_hterror", "limit_wildcard", "limit_ssl", "limit_ssl_letsencrypt", "limit_backup", "limit_directive_snippets", "limit_mail_backup"):
            values[key] = f"<label class='checkbox-inline'><input type='checkbox' name='{key}' checked> Yes</label>"
        values.update({"id": "1", "is_admin": True, "template_master": "<option value='0'>Custom</option>", "tpl_add_select": "<option value='0'>Select additional template</option>", "parent_client_id": "<option value='0'>admin</option>", "template_additional_list": "<li>No additional templates</li>", "limit_web_quota": "1024", "limit_traffic_quota": "10240", "btn_save_txt": "Save", "btn_cancel_txt": "Cancel", "web_php_options": "<label class='checkbox-inline'><input type='checkbox' checked> PHP-FPM</label>", "ssh_chroot": "<label class='checkbox-inline'><input type='checkbox' checked> Jailkit</label>"})
        return self.tabbed(self.render(template, values), "Client", "client")

    def page(self, name, mode):
        content = self.content(name)
        shell = self.render(self.source("themes/clarity/templates/main.tpl.htm", True).decode(), self.renderer.BASE_VARS)
        module = "client" if name == "limits" else name
        shell = self.renderer.fill(shell, r"(<div id='topnav-container'>)\s*(</div>)", self.nav(module))
        shell = self.renderer.fill(shell, r"(<div id='sidebar' class='nz-context'>)\s*(</div>)", "")
        shell = self.renderer.fill(shell, r'(<div id="pageContent"[^>]*>)<!-- AJAX CONTENT -->(</div>)', content)
        page_scripts = "\n".join(re.findall(r"<script\b.*?</script>", content, flags=re.S | re.I))
        shell = self.renderer.strip_scripts(shell)
        scripts = ("js/jquery.min.js", "themes/default/assets/javascripts/bootstrap.min.js", "themes/default/assets/javascripts/bootstrap-datetimepicker.min.js", "themes/default/assets/javascripts/ispconfig.js", "themes/default/assets/javascripts/modernizr.custom.min.js", "themes/default/assets/javascripts/pushy.min.js", "themes/default/assets/javascripts/responsive.min.js", "js/select2/select2.min.js", "themes/clarity/assets/javascripts/nz-theme.js")
        # Native post-AJAX initialisation includes Select2. Keep it in the render rather than comparing a bare native select with an enhanced live form.
        init = "<script>ISPConfig.options.useComboBox = true; ISPConfig.onAfterContentLoad('fixture', ''); ISPConfig.loadPushyMenu();</script>"
        shell = shell.replace("</body>", "\n".join(f'<script src="/{path}"></script>' for path in scripts) + page_scripts + init + "</body>")
        return self.renderer.fix_paths(shell.replace("<html lang='en'>", f"<html lang='en' data-nz-theme='{mode}'>"))


METRICS = """() => {
  const box = el => el.getBoundingClientRect().toJSON();
  const style = el => { const s = getComputedStyle(el); return {padding:s.padding, leftInset:parseFloat(s.paddingLeft), rightInset:parseFloat(s.paddingRight), borderTop:parseFloat(s.borderTopWidth), position:s.position}; };
  const out = {overflow:document.documentElement.scrollWidth > innerWidth, username:{tag:document.querySelector('.nz-user').tagName, name:document.querySelector('.nz-user').getAttribute('aria-label')}};
  const alert = document.querySelector('#infomsg');
  if(alert) { const button = alert.querySelector('.close'); out.notice = {outer:box(alert), button:box(button), gutter:style(alert).rightInset}; }
  const dns = document.querySelector('.panel_dns_soa');
  if(dns) { const first = dns.querySelector('.dns-record-btn'); out.dns = {inset:box(first).left-box(dns).left-parseFloat(getComputedStyle(dns).borderLeftWidth), buttonCount:dns.querySelectorAll('.dns-record-btn').length}; }
  const panel = document.querySelector('.panel_client');
  if(panel) { out.limits = {form:style(panel.querySelector('.pnl_formsarea')), helper:style(panel.querySelector('#tpl_add_btn').closest('.clear')), footer:style(panel.querySelector('[data-submit-form]').closest('.clear')), body:style(panel.querySelector('.panel-body'))}; }
  // The theme's "Show all" toggle is the only valueless button in a body row; the module's own Edit/Delete buttons carry value=.
  const toggle = 'button.btn-default:not([value])';
  const rows = table => table && {shown:[...table.tBodies[0].rows].filter(r => getComputedStyle(r).display !== 'none' && !r.querySelector(toggle)).length, toggle:!!table.tBodies[0].querySelector(toggle)};
  out.tables = {dashlet:rows(document.querySelector('#ui-dashlet')), nocap:rows(document.querySelector('#ui-dashlet-nocap')), items:rows(document.querySelector('#ui-items table'))};
  // The colour actually behind an element: the nearest ancestor with an opaque-enough background.
  const behind = el => { for (let n = el; n; n = n.parentElement) { const c = getComputedStyle(n).backgroundColor; if (c && !/^rgba\\(.*,\\s*0\\)$|^transparent$/.test(c)) return c; } return getComputedStyle(document.body).backgroundColor; };
  const jq = document.querySelector('.jqplot-target');
  if(jq) {
    const tick = jq.querySelector('.jqplot-xaxis-tick');
    // jqPlot may scale a canvas's backing store for HiDPI and set its CSS size inline; otherwise its attribute size is its intended size.
    out.jqplot = {canvases:[...jq.querySelectorAll('canvas')].map(c => ({cls:c.className, want:[parseFloat(c.style.width) || c.width, parseFloat(c.style.height) || c.height], got:[box(c).width, box(c).height], left:box(c).left - box(jq).left})), target:{height:box(jq).height, border:parseFloat(getComputedStyle(jq).borderTopWidth), shadow:getComputedStyle(jq).boxShadow}, tick:tick && {color:getComputedStyle(tick).color, behind:behind(tick)}};
  }
  const strip = document.querySelector('#ui-chartjs');
  if(strip) { const wrap = strip.parentElement; const s = getComputedStyle(wrap); out.chartjs = {width:box(strip).width, room:wrap.clientWidth - parseFloat(s.paddingLeft) - parseFloat(s.paddingRight), wrapper:box(wrap).height, card:parseFloat(getComputedStyle(wrap.parentElement).borderTopWidth)}; }
  return out;
}"""


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--core-web", type=Path, default=REPO / ".refs/ispconfig3/interface/web")
    parser.add_argument("--theme-ref", help="Read theme assets from this git revision instead of the working tree")
    parser.add_argument("--output", type=Path, help="Evidence directory outside the repository")
    parser.add_argument("--only", choices=[f"{mode}-{name}-{size}" for mode in ("light", "dark") for name in NAMES for size in ("desktop", "narrow", "mobile")], help="Run one named fixture for a focused reproduction")
    parser.add_argument("--browser", choices=("firefox", "chromium"), default="firefox", help="Playwright browser engine")
    parser.add_argument("--billing-web", type=Path, help="A licensed Billing Module's interface/web directory (the one holding billing/)")
    args = parser.parse_args()
    if not (args.core_web / "client/templates/client_edit_limits.htm").is_file():
        parser.error("Supply an ISPConfig source checkout with --core-web")
    if args.billing_web and not (args.billing_web / "billing/templates/billing_dashboard.htm").is_file():
        parser.error("--billing-web must be the module's interface/web directory")
    if args.billing_web and args.billing_web.resolve().is_relative_to(REPO):
        parser.error("--billing-web must be outside the repository")
    if args.output and args.output.resolve().is_relative_to(REPO):
        parser.error("--output must be outside the repository")
    try:
        from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError
    except ImportError:
        parser.error("Playwright and its Firefox (or Chromium) browser are required; see CONTRIBUTING.md")
    temporary = tempfile.TemporaryDirectory(prefix="clarity-ui-") if not args.output else None
    output = args.output.resolve() if args.output else Path(temporary.name)
    output.mkdir(parents=True, exist_ok=True)
    fixture = Fixture(args.core_web, args.theme_ref, args.billing_web)
    checks, results = [], []

    def check(condition, message):
        checks.append({"passed": bool(condition), "message": message})
        print(("PASS " if condition else "FAIL ") + message)

    with sync_playwright() as p:
        browser = getattr(p, args.browser).launch()
        browser_version = browser.version
        for width, size in ((1920, "desktop"), (1024, "narrow"), (390, "mobile")):
            for mode in ("light", "dark"):
                for name in NAMES:
                    label = f"{mode}-{name}-{size}"
                    if args.only and args.only != label:
                        continue
                    page_html = fixture.page(name, mode)
                    (output / f"{label}.html").write_text(page_html)
                    requests, errors, missing, dialogs = [], [], [], []
                    state = {"module": "client" if name == "limits" else name, "settings_visits": 0}
                    context = browser.new_context(viewport={"width": width, "height": 1080}, reduced_motion="reduce", service_workers="block")
                    context.add_init_script(f"localStorage.setItem('nz-theme', '{mode}');")

                    def route_request(route):
                        url = urlsplit(route.request.url)
                        rel = unquote(url.path).lstrip("/")
                        requests.append({"path": url.path, "query": url.query, "method": route.request.method})
                        if url.netloc != "clarity.test":
                            missing.append(route.request.url)
                            return route.abort()
                        if rel == "fixture.html":
                            return route.fulfill(content_type="text/html", body=page_html)
                        if rel == "themes/clarity/brand.php":
                            return route.fulfill(content_type="text/css", body="")
                        if rel == "capp.php":
                            state["module"] = parse_qs(url.query).get("mod", [""])[0]
                            return route.fulfill(body="HEADER_REDIRECT:tools/user_settings.php")
                        if rel == "tools/user_settings.php":
                            state["settings_visits"] += 1
                            return route.fulfill(content_type="text/html", body=f"<h1 id='ui-settings-page' data-visit='{state['settings_visits']}'>User Settings</h1>")
                        if rel == "nav.php":
                            body = fixture.nav(state["module"]) if parse_qs(url.query).get("nav") == ["top"] else ""
                            return route.fulfill(content_type="text/html", body=body)
                        if rel == "datalogstatus.php":
                            return route.fulfill(content_type="application/json", body='{"entries":[],"count":0}')
                        if fixture.billing and rel.startswith("billing/lib/js/") and Path(rel).suffix.lower() in (".css", ".js"):
                            try:
                                return route.fulfill(content_type=mimetypes.guess_type(rel)[0], body=fixture.billing_source(rel))
                            except FileNotFoundError:
                                missing.append(rel)
                                return route.fulfill(status=404, body="Missing billing asset")
                        if rel.startswith(("themes/clarity/assets/", "themes/default/assets/", "js/")) and Path(rel).suffix.lower() in (".css", ".js", ".woff", ".woff2", ".ttf", ".svg", ".png", ".ico", ".xml", ".webmanifest"):
                            try:
                                data = fixture.source(rel, rel.startswith("themes/clarity/"))
                                return route.fulfill(content_type=mimetypes.guess_type(rel)[0] or "application/octet-stream", body=data)
                            except (FileNotFoundError, subprocess.CalledProcessError):
                                missing.append(rel)
                                return route.fulfill(status=404, body="Missing fixture asset")
                        return route.fulfill(content_type="application/json", body="{}")

                    context.route("**/*", route_request)
                    page = context.new_page()
                    page.on("pageerror", lambda error: errors.append(str(error)))
                    def dismiss_dialog(dialog):
                        dialogs.append(dialog.message)
                        dialog.dismiss()
                    page.on("dialog", dismiss_dialog)
                    page.goto(ORIGIN + "/fixture.html", wait_until="networkidle")
                    page.evaluate("document.fonts.ready")
                    metrics = page.evaluate(METRICS)
                    # The Billing Module sizes its chart inline at 720px, wider than a phone under any theme; the theme leaves jqPlot geometry alone on purpose.
                    if not (name == "billing" and width < 720):
                        check(not metrics["overflow"], label + ": no horizontal page overflow")
                    if name == "dashboard":
                        notice = metrics["notice"]
                        outer, button = notice["outer"], notice["button"]
                        check(button["right"] <= outer["right"] and button["left"] >= outer["left"] and button["top"] >= outer["top"] and button["bottom"] <= outer["bottom"], label + ": close target inside notice")
                        check(button["width"] >= 24 and button["height"] >= 24, label + ": close target at least 24px")
                        check(notice["gutter"] >= button["width"] + 8, label + ": message has a close-button gutter")
                    if name == "dns":
                        check(metrics["dns"]["inset"] >= 16 and metrics["dns"]["buttonCount"] == 21, label + ": all DNS types inset from panel")
                    if name == "limits":
                        limits = metrics["limits"]
                        check(limits["form"]["leftInset"] >= 16, label + ": client form inset")
                        check(limits["helper"]["position"] == "static" and limits["helper"]["borderTop"] == 0, label + ": helper is not a footer")
                        check(limits["footer"]["position"] == "sticky", label + ": actual submit footer remains sticky")
                        check(limits["body"]["leftInset"] == 16, label + ": accordion inset is not doubled")
                    tables = metrics["tables"]
                    if name == "dashboard":
                        check(tables["dashlet"] == {"shown": 10, "toggle": True}, label + ": long dashlet folds to 10 rows behind Show all")
                        check(tables["nocap"] == {"shown": 15, "toggle": False}, label + ": data-nz-nocap dashlet keeps every row")
                    if name == "billing":
                        check(tables["items"] == {"shown": 15, "toggle": False}, label + ": module table off the dashboard keeps every row")
                        jq = metrics["jqplot"]
                        check(len(jq["canvases"]) >= 4 and all(abs(c["got"][0] - c["want"][0]) < 1 and abs(c["got"][1] - c["want"][1]) < 1 for c in jq["canvases"]), label + ": every jqPlot canvas keeps its own size")
                        check(len({c["left"] for c in jq["canvases"] if c["want"][0] == jq["canvases"][0]["want"][0]}) == 1, label + ": full-size jqPlot layers stay aligned")
                        check(abs(jq["target"]["height"] - 150) < 1 and jq["target"]["border"] == 0 and jq["target"]["shadow"] == "none", label + ": jqPlot target keeps its height and gets no card")
                        check(jq["tick"] and contrast(jq["tick"]["color"], jq["tick"]["behind"]) >= 4.5, label + ": jqPlot tick labels reach 4.5:1")
                        strip = metrics["chartjs"]
                        check(abs(strip["width"] - strip["room"]) < 1 and abs(strip["wrapper"] - 110) < 1 and strip["card"] == 1, label + ": stock chart strip still fills a 110px wrapper on a card")
                    page.screenshot(path=str(output / f"{label}.png"))
                    if name == "limits":
                        page.evaluate("window.scrollTo(0, 500)")
                        page.screenshot(path=str(output / f"{label}-scrolled.png"))
                        page.evaluate("window.scrollTo(0, 0)")
                        page.locator('#tpl_add_btn').evaluate("el => el.textContent = 'Zusätzliches Template hinzufügen'")
                        check(not page.evaluate("document.documentElement.scrollWidth > innerWidth"), label + ": long helper label stays within page")
                        page.locator('[href="#collapseWeb"]').click()
                        page.wait_for_function("!document.getElementById('collapseWeb').classList.contains('collapsing')")
                        page.locator("#collapseWeb").wait_for(state="hidden")
                        check(not page.locator("#collapseWeb").is_visible(), label + ": accordion collapses")
                        page.locator('[href="#collapseWeb"]').click()
                        page.wait_for_function("!document.getElementById('collapseWeb').classList.contains('collapsing')")
                        page.locator("#collapseWeb").wait_for(state="visible")
                        check(page.locator("#collapseWeb").is_visible(), label + ": accordion expands")
                    if name == "dashboard":
                        page.locator("#infomsg .close").click()
                        check(page.locator("#infomsg").count() == 0, label + ": notice dismisses")
                    if width == 1920:
                        user = page.locator(".nz-user")
                        is_button = user.evaluate("el => el.tagName === 'BUTTON'")
                        check(is_button, label + ": username is a real button")
                        if is_button:
                            for action in ("click", "Enter", "Space"):
                                # Every input method starts over the original form/list. A previous click must not replace it with the settings fixture before Enter is tested.
                                page.goto(ORIGIN + "/fixture.html", wait_until="networkidle")
                                start = len(requests)
                                dialog_start = len(dialogs)
                                visit = state["settings_visits"] + 1
                                opened = True
                                try:
                                    with page.expect_response(lambda response: urlsplit(response.url).path == "/tools/user_settings.php", timeout=5000):
                                        if action == "click":
                                            user.click()
                                        else:
                                            user.focus()
                                            page.keyboard.press(action)
                                    page.locator(f"#ui-settings-page[data-visit='{visit}']").wait_for(state="visible", timeout=5000)
                                    page.wait_for_function("document.querySelector('#main-navigation [data-nz-module=tools].active') !== null", timeout=5000)
                                except PlaywrightTimeoutError:
                                    opened = False
                                calls = requests[start:]
                                check(sum(r["path"] == "/capp.php" and parse_qs(r["query"]).get("mod") == ["tools"] for r in calls) == 1 and any(r["path"] == "/tools/user_settings.php" for r in calls), label + ": " + action + " uses native Tools switch exactly once")
                                check(opened, label + ": " + action + " opens fresh settings content")
                                allowed_paths = ("/capp.php", "/tools/user_settings.php", "/nav.php", "/datalogstatus.php")
                                check(not dialogs[dialog_start:] and all(r["method"] == "GET" and r["path"] in allowed_paths for r in calls), label + ": " + action + " does not submit, filter or invoke a form helper")
                            top = page.locator("#topnav-container")
                            top.evaluate("(el, markup) => el.innerHTML = markup", fixture.nav("tools", False))
                            page.wait_for_function("document.querySelector('.nz-user').tagName === 'SPAN'")
                            check(user.evaluate("el => el.tabIndex === -1 && getComputedStyle(el).borderTopWidth === '0px'"), label + ": no Tools means plain nonfocusable identity")
                            top.evaluate("(el, markup) => el.innerHTML = markup", fixture.nav("tools"))
                            page.wait_for_function("document.querySelector('.nz-user').tagName === 'BUTTON'")
                            check(user.get_attribute("data-capp") == "tools", label + ": active Tools without data-capp on nav still enables username")
                            top.evaluate("el => el.innerHTML = ''")
                            page.wait_for_function("document.querySelector('.nz-user').tagName === 'SPAN'")
                            top.evaluate("(el, markup) => el.innerHTML = markup", fixture.nav("client"))
                            page.wait_for_function("document.querySelector('.nz-user').tagName === 'BUTTON'")
                            check(user.get_attribute("aria-label") == "User Settings: admin", label + ": delayed menu restores a labelled control")
                    if width == 390:
                        page.locator(".menu-btn").click()
                        check(page.locator("body").evaluate("el => el.classList.contains('pushy-active')"), label + ": mobile drawer still opens")
                    page.locator("#pageContent").evaluate("(el, markup) => el.insertAdjacentHTML('beforeend', '<div id=ui-legacy-footers>' + markup + '</div>')", fixture.legacy_footers())
                    check(page.locator("#ui-legacy-footers .clear").evaluate_all("rows => rows.length === 2 && rows.every(row => getComputedStyle(row).position === 'sticky')"), label + ": native inline-submit footers retain styling")
                    check(not errors and not missing, label + ": no script errors or missing assets")
                    results.append({"case": label, "metrics": metrics, "requests": requests, "errors": errors, "missing": missing, "dialogs": dialogs})
                    (output / "results.json").write_text(json.dumps({"complete": False, "checks": checks, "cases": results}, indent=2) + "\n")
                    context.close()
        browser.close()
    report = {"complete": True, "theme_ref": args.theme_ref or "working-tree", "browser": args.browser.capitalize() + " " + browser_version, "billing": "module" if args.billing_web else "synthetic", "core_web": str(args.core_web.resolve()), "checks": checks, "cases": results, "source_sha256": fixture.hashes}
    (output / "results.json").write_text(json.dumps(report, indent=2) + "\n")
    failed = sum(not c["passed"] for c in checks)
    print(f"\n{len(checks) - failed} passed, {failed} failed; evidence: {output}")
    if temporary:
        temporary.cleanup()
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
