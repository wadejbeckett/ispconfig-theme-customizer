#!/usr/bin/env python3
"""Offline Clarity UI regression checks using the real theme and core templates.

Needs Playwright Firefox and an ISPConfig source checkout, not a running panel. All browser requests are fulfilled in memory. --output must be outside the repo.

--theme-ref reads an older git revision to demonstrate regressions; the same checks deliberately fail there. No renderer/source/build directory is rewritten.
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


def git(path, *args):
    return subprocess.check_output(["git", "-C", str(path), *args])


class Fixture:
    def __init__(self, core, theme_ref):
        self.core = core.resolve()
        self.theme_ref = theme_ref
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
            return self.render(self.source("themes/clarity/templates/dashboard/dashboard.htm", True).decode(), {"welcome_user": "Welcome admin", "info": [{"info_msg": "An ISPConfig update is available. Please update your installation."}]})
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
  return out;
}"""


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--core-web", type=Path, default=REPO / ".refs/ispconfig3/interface/web")
    parser.add_argument("--theme-ref", help="Read theme assets from this git revision instead of the working tree")
    parser.add_argument("--output", type=Path, help="Evidence directory outside the repository")
    parser.add_argument("--only", choices=[f"{mode}-{name}-{size}" for mode in ("light", "dark") for name in ("dashboard", "dns", "limits") for size in ("desktop", "narrow", "mobile")], help="Run one named fixture for a focused reproduction")
    args = parser.parse_args()
    if not (args.core_web / "client/templates/client_edit_limits.htm").is_file():
        parser.error("Supply an ISPConfig source checkout with --core-web")
    if args.output and args.output.resolve().is_relative_to(REPO):
        parser.error("--output must be outside the repository")
    try:
        from playwright.sync_api import sync_playwright, TimeoutError as PlaywrightTimeoutError
    except ImportError:
        parser.error("Playwright and its Firefox browser are required; see CONTRIBUTING.md")
    temporary = tempfile.TemporaryDirectory(prefix="clarity-ui-") if not args.output else None
    output = args.output.resolve() if args.output else Path(temporary.name)
    output.mkdir(parents=True, exist_ok=True)
    fixture = Fixture(args.core_web, args.theme_ref)
    checks, results = [], []

    def check(condition, message):
        checks.append({"passed": bool(condition), "message": message})
        print(("PASS " if condition else "FAIL ") + message)

    with sync_playwright() as p:
        browser = p.firefox.launch()
        browser_version = browser.version
        for width, size in ((1920, "desktop"), (1024, "narrow"), (390, "mobile")):
            for mode in ("light", "dark"):
                for name in ("dashboard", "dns", "limits"):
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
    report = {"complete": True, "theme_ref": args.theme_ref or "working-tree", "browser": "Firefox " + browser_version, "core_web": str(args.core_web.resolve()), "checks": checks, "cases": results, "source_sha256": fixture.hashes}
    (output / "results.json").write_text(json.dumps(report, indent=2) + "\n")
    failed = sum(not c["passed"] for c in checks)
    print(f"\n{len(checks) - failed} passed, {failed} failed; evidence: {output}")
    if temporary:
        temporary.cleanup()
    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
