#!/usr/bin/env python3
"""Turn the SHIPPED Branding template into a mockup fragment.

mockup/build.py renders clarity's real shell around a fragment. Pointing it at
interface/web/customizer/templates/customizer_edit.htm needs three things this
module supplies:

  * every {tmpl_var} the page interpolates, with the values customizer_edit.php
    would have set — the wordbook read as TEXT (never executed, the same rule
    .github/scripts/lang_check.php follows), the sample brand's field values,
    the two <select> option lists and the six checkboxes emitted the way
    tform_base.inc.php emits them, and the fifteen disclosure names composed the
    way publish_hint_labels() composes them;
  * the five preview slots and the three status facts, which come from the
    module's own renderers through sample_previews.php;
  * a bootstrap that lets the page's own inline script run with no panel behind
    it, by answering its one fetch() with the payload those same renderers
    produced.

Nothing here reimplements a decision. Anything this file had to compute itself
would be a second copy of something the panel already decides, and the shot
would stop being evidence about the shipped page.
"""
import json
import re
import subprocess
from pathlib import Path

HERE = Path(__file__).resolve().parent            # mockup/branding
REPO = HERE.parent.parent
TPL = REPO / "interface/web/customizer/templates/customizer_edit.htm"
TFORM_LNG = REPO / "interface/web/customizer/lib/lang/en_customizer.lng"
SAMPLE = HERE / "sample_previews.php"

# Which switches are on in the sample, matching mockup/branding/branding.html.
SWITCHES = {
    "show_design_picker": True, "show_version": False, "show_news_feed": True,
    "show_donation_dashlet": False, "show_ispconfig_credit": True,
    "show_theme_credit": True,
}

_WB = re.compile(r"\$wb\[\s*'([^']+)'\s*\]\s*=\s*'((?:[^'\\]|\\.)*)'\s*;")

_SAMPLE_CACHE = None


def wordbook(path: Path) -> dict:
    """Every $wb key in a .lng file, parsed as TEXT and never executed."""
    out = {}
    for m in _WB.finditer(path.read_text(encoding="utf-8")):
        out[m.group(1)] = re.sub(r"\\(['\\])", r"\1", m.group(2))
    return out


def sample() -> dict:
    """The module's own renderers, through sample_previews.php.

    Cached: the render and the bootstrap are the SAME payload by construction,
    which is the property the shot is evidence for.
    """
    global _SAMPLE_CACHE
    if _SAMPLE_CACHE is None:
        try:
            out = subprocess.run(["php", str(SAMPLE)],
                                 capture_output=True, text=True, check=True)
        except (FileNotFoundError, subprocess.CalledProcessError) as exc:
            raise SystemExit("mockup/branding: php is needed to render the shipped "
                             "Branding page (it runs the module's own preview "
                             "renderers): %s" % exc)
        _SAMPLE_CACHE = json.loads(out.stdout)
    return _SAMPLE_CACHE


def _options(wb: dict, selected: str) -> str:
    """tform_base.inc.php:501-509 emits <option> tags and nothing else.

    The three values are customizer.tform.php's own: '' is Automatic, which is
    why the empty string — not the word "auto" — is what a browser posts back.
    """
    rows = [("", "logo_variant_auto_txt"),
            ("on_light", "logo_variant_on_light_txt"),
            ("on_dark", "logo_variant_on_dark_txt")]
    return "".join(
        "<option value='%s'%s>%s</option>\r\n"
        % (value, " SELECTED" if value == selected else "", wb[key])
        for value, key in rows)


def _checkbox(key: str, on: bool) -> str:
    """tform_base.inc.php:541-543, verbatim — the double space included.

    value="1" because customizer.tform.php declares array(0 => '0', 1 => '1')
    for all six switches and tform emits $field['value'][1].
    """
    return ('<input name="%s" id="%s" value="1" type="checkbox" %s />\r\n'
            % (key, key, " CHECKED" if on else ""))


def variables() -> dict:
    wb = wordbook(TFORM_LNG)
    data = sample()

    v = dict(wb)                      # every *_txt the template interpolates
    v.update(data["tpl"])             # the slots, the facts and the field values
    v["logo_variant_nav"] = _options(wb, "")
    v["logo_variant_login"] = _options(wb, "")
    for key, on in SWITCHES.items():
        v[key] = _checkbox(key, on)
    # publish_hint_labels(), in Python: str_replace('%s', <label>, hint_more_txt)
    # over customizer_hint_label_keys(), which sample_previews.php hands over so
    # the fifteen are never named twice.
    for key in data["hint_label_keys"]:
        v["hint_" + key] = wb["hint_more_txt"].replace("%s", wb[key])
    # Two vars only the uploader's response sets.
    v["upload_msg"] = ""
    v["upload_error"] = ""
    return v


def bootstrap() -> str:
    """The page's own inline script, with its one fetch() answered locally.

    The script is taken OUT OF THE TEMPLATE rather than copied here, so what
    runs in the shot is the code that ships. fetch is stubbed because
    customizer/preview.php needs a panel; everything the stub returns was built
    by the module's real payload function.

    The stub counts the calls it answers and leaves the tally on
    window.__nzPreviewCalls, which is how the harness can say how many POSTs
    the page really makes on load.
    """
    src = TPL.read_text(encoding="utf-8")
    js = src.split("<script>", 1)[1].rsplit("</script>", 1)[0]
    payload = json.dumps(sample()["payload"])
    return ("<script>\nwindow.__nzPreviewCalls = 0;\n"
            "window.fetch = function () {\n"
            "  window.__nzPreviewCalls++;\n"
            "  return Promise.resolve({ ok: true, status: 200,\n"
            "    json: function () { return Promise.resolve(%s); } });\n"
            "};\n</script>\n<script>%s</script>\n" % (payload, js))
