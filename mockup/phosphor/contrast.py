#!/usr/bin/env python3
"""Measure phosphor's ink/backdrop pairs. Tokens are READ FROM phosphor.css,
not retyped, so a token edit shows up here.

Glass is never load-bearing for contrast: every pane pair is measured against
the OPAQUE composite (the value the backdrop-filter fallback paints), not the
blurred one, per the spec's Accessibility section."""
import re, sys
from pathlib import Path

CSS = (Path(__file__).resolve().parent / "phosphor.css").read_text()
ROOT = CSS.split(":root {", 1)[1].split("\n}", 1)[0]
TOK = dict(re.findall(r"^\s*(--pz-[\w-]+):\s*([^;]+);", ROOT, re.M))


def rgb(v):
    v = v.strip()
    m = re.match(r"#([0-9A-Fa-f]{6})$", v)
    if m:
        h = m.group(1)
        return (int(h[0:2], 16), int(h[2:4], 16), int(h[4:6], 16), 1.0)
    m = re.match(r"rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$", v)
    if m:
        a = float(m.group(4)) if m.group(4) else 1.0
        return (float(m.group(1)), float(m.group(2)), float(m.group(3)), a)
    raise ValueError(v)


def over(fg, bg):
    """source-over composite of fg (may be translucent) onto opaque bg"""
    fr, fg_, fb, fa = fg
    br, bgc, bb, _ = bg
    return (fr * fa + br * (1 - fa), fg_ * fa + bgc * (1 - fa), fb * fa + bb * (1 - fa), 1.0)


def hexof(c):
    return "#%02X%02X%02X" % tuple(int(round(x)) for x in c[:3])


def lin(c):
    c /= 255.0
    return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4


def L(c):
    return 0.2126 * lin(c[0]) + 0.7152 * lin(c[1]) + 0.0722 * lin(c[2])


def ratio(a, b):
    la, lb = L(a), L(b)
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)


T = {k: rgb(v) for k, v in TOK.items() if re.match(r"^(#|rgba?\()", v.strip())}

GROUND = T["--pz-ground"]
BACKDROPS = [
    ("page ground   --pz-ground",   GROUND),
    ("rail band     --pz-band",     T["--pz-band"]),
    ("glass on page (opaque fallback composite)", over(T["--pz-glass"], GROUND)),
    ("pane solid    --pz-pane-solid", T["--pz-pane-solid"]),
    ("input well    --pz-raised",   T["--pz-raised"]),
    ("hover strata  --pz-lift",     T["--pz-lift"]),
]
INKS = [
    ("body        --pz-ink-base",        "--pz-ink-base",        7.0),
    ("labels      --pz-ink-sub",         "--pz-ink-sub",         4.5),
    ("captions    --pz-ink-muted",       "--pz-ink-muted",       4.5),
    ("placeholder --pz-ink-placeholder", "--pz-ink-placeholder", 4.5),
    ("accent      --pz-accent",          "--pz-accent",          4.5),
    ("decor/disabled --pz-ink-faint",    "--pz-ink-faint",       0.0),
]

fail = 0
print("glass composite check: %s over %s = %s  (spec says #12161B)\n"
      % (TOK["--pz-glass"].strip(), hexof(GROUND), hexof(over(T["--pz-glass"], GROUND))))

hdr = "%-34s" % "" + "".join("%-13s" % b[0].split()[0] for b in BACKDROPS)
print(hdr)
for label, key, floor in INKS:
    row = "%-34s" % label
    for _, bg in BACKDROPS:
        r = ratio(T[key], bg)
        mark = "" if floor == 0.0 or r >= floor else "!"
        row += "%-13s" % ("%.2f%s" % (r, mark))
        if mark:
            fail += 1
    print(row)

print("\nnon-text contrast (SC 1.4.11, floor 3.0) — control boundaries")
CONTROLS = [
    ("--pz-edge-input", "--pz-raised",    "form-control well"),
    ("--pz-edge-input", "--pz-pane-solid", "ghost .btn on a pane"),
    ("--pz-edge-input", "--pz-ground",    "ghost .btn on the page"),
    ("--pz-accent",     "--pz-ground",    "focus ring outer stop"),
]
for name, base, what in CONTROLS:
    e = over(T[name], T[base])
    r_in = ratio(e, T[base])
    r_pg = ratio(e, GROUND)
    worst = min(r_in, r_pg)
    print("  %-38s %-18s %.2f%s" % (what, name, worst, "" if worst >= 3.0 else "   ! under 3.0"))
    if worst < 3.0:
        fail += 1

print("\nnon-text, informational — decorative hairlines, not sole affordances")
for name, base, what in (("--pz-edge", "--pz-ground", "pane hairline"),
                         ("--pz-edge", "--pz-pane-solid", "launcher chip (label carries the affordance)"),
                         ("--pz-edge-strong", "--pz-ground", "--pz-edge-strong, no longer a control boundary")):
    e = over(T[name], T[base])
    print("  %-58s %.2f" % (what, ratio(e, T[base])))

print("\nink on a filled accent")
for ink, name in ((T["--pz-on-accent"], "--pz-on-accent"), ((255, 255, 255, 1.0), "white")):
    r = ratio(ink, T["--pz-accent"])
    print("  %-18s on --pz-accent   %.2f%s" % (name, r, "" if r >= 4.5 else "   (prohibited)"))

print("\nrail ink on the shipped band (--pz-band)")
for k in ("--pz-rail-text", "--pz-rail-text-hover", "--pz-rail-heading"):
    c = over(T[k], T["--pz-band"])
    print("  %-22s %s  %.2f" % (k, hexof(c), ratio(c, T["--pz-band"])))

print("\n%d pair(s) under their floor" % fail)
sys.exit(1 if fail else 0)
