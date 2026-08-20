#!/usr/bin/env bash
#
# ispconfig-theme-customizer — installer.
#
# A brandable front-end for ISPConfig. It replaces the panel's front-end with a
# design, and adds an admin page — nav label "Branding" — where you set your
# logo, panel name and colours.
#
# It deploys into two directories:
#   themes/<design>           -> <ispconfig>/interface/web/themes/<design>
#   interface/web/customizer  -> <ispconfig>/interface/web/customizer
#
# Touches NOTHING in ISPConfig core and adds no database schema. The Branding
# page writes only to the existing sys_ini row; the design writes nothing at
# runtime — it READS exactly the keys that page writes. That shared contract is
# why this is one project with one version number: before v3.0.0 the halves
# lived in separate repositories with independent versions, and nothing
# enforced that they matched, so an accent colour could silently fail to apply.
#
# Two designs read that contract, and you choose with --design:
#   clarity (default) — a ground-up dark (and light) interface.
#   classic           — the stock ISPConfig look, made brandable. Ships two
#                       templates and no assets of its own; everything else
#                       comes from the panel's own default theme.
#
# You can still install one half on its own. With --module you get just the
# Branding page: logo, panel name and the per-role news feeds are read by
# ISPConfig core itself, so those brand the stock theme too. Accent colour,
# login background and version-hiding need a brand-aware design, which is what
# --theme installs. Neither design is the product's identity — anything that
# reads the same sys_ini keys inherits the Branding page, and CI enforces that
# contract.
#
# Usage:
#   ./install.sh [--theme|--module|--all] [--design=<name>] [--copy]
#                [--no-assign] [ISPCONFIG_ROOT]
#
#     --theme       install only the design(s)
#     --module      install only the Branding page
#     --all         install both — the default when no flag is given
#     --design=<n>  which design: clarity (the default), classic, or all.
#                   Repeatable, so --design=clarity --design=classic is the same
#                   as --design=all.
#     --copy        copy instead of symlinking (use for packaged installs)
#     --no-assign   do not grant admin users access to the Branding page; do it
#                   by hand in System > CP Users > edit the admin user > Modules
#     ISPCONFIG_ROOT defaults to /usr/local/ispconfig
#
# --design is deliberately a SEPARATE axis from --theme/--module/--all rather
# than a second spelling of them: those three choose which HALVES of the product
# to install, --design chooses which design the theme half means. Each flag
# keeps one job, so ./install.sh --design=classic still installs the Branding
# page (it is a plain install that happens to use the other design), and
# ./install.sh --theme --design=classic is how you ask for the design alone.
# Every invocation that worked before still means exactly what it did: with no
# --design you get clarity, as you always did.
#
# classic's two shell templates are GENERATED HERE, from the target panel's own
# themes/default/templates/, and are not in the repository. ISPConfig searches
# themes/<active>/templates first and falls back to themes/default/templates
# (interface/lib/classes/tpl_ini.inc.php), so a design that overrides only the
# shell inherits every other template from whatever version is installed — but a
# COMMITTED copy of that shell would freeze one version's markup and diverge
# from the panel silently. Generating it makes classic stock-by-construction,
# and since re-running this script after an upgrade is already mandatory for the
# version stamp, it heals itself in the same pass. See themes/classic/README.md.
#
# Why the theme is version-stamped: ISPConfig gates themes on an EXACT version
# match, under two different filenames depending on the screen (a core quirk):
#   - interface/web/login/index.php resets a user's theme to 'default' at login
#     unless themes/<t>/ispconfig_version EXISTS and equals ISPC_APP_VERSION.
#   - tools/form/user_settings.tform.php lists a theme in the Design picker only
#     when it has no version file OR the file equals ISPC_APP_VERSION.
#   - the admin "Default user settings" form reads ISPC_VERSION instead.
# The only state satisfying all of them is: both files present AND equal to
# ISPC_APP_VERSION. That value changes on EVERY upgrade, including patch
# releases, so re-run this script after any ISPConfig update.
#
set -euo pipefail

# Ignore SIGPIPE, and make the completion check below unmissable.
#
# This script REPLACES the theme directory and then stamps the version files
# that ISPConfig's theme gate requires. If it dies in between, the panel is left
# with a theme it will refuse to load — and core's response to a failed gate is
# to silently reset every affected user to the default theme at their next
# login. No error, nothing in a log. That is the worst failure this project has,
# and it was reachable by something as ordinary as piping this script's output:
#
#     ./install.sh | head        # head exits, we get SIGPIPE
#     ./install.sh | less        # ...then you press q
#     ./install.sh | tee log     # ...and tee dies
#
# Under `set -e` that killed the run mid-install. Ignoring PIPE means a closed
# stdout can no longer interrupt the work, and the EXIT trap turns any other
# early death into a loud, specific warning instead of a panel that quietly
# reverts to the stock theme a day later.
trap '' PIPE

# Ignoring the SIGPIPE signal is only half of it: with the reader gone, every
# subsequent write still fails with EPIPE, and `set -e` treats that failing echo
# as a fatal error — so the run still died mid-install. Informational output
# therefore goes through say(), which tolerates a closed stdout. Errors keep
# using `echo ... >&2`, since stderr is a different descriptor and is normally
# still attached. The rule: NOTHING the operator chose not to read may decide
# how much of the install actually happens.
say() { echo "$@" 2>/dev/null || true; }

# Print ONLY the usage header — the comment block between the shebang and
# `set -euo pipefail` — not every top-level comment in the file. The old
# implementation grepped '^#' across the whole script, so --help emitted ~150
# lines: the SIGPIPE rationale, "--- helpers ---" section markers, and every
# other note written for someone reading the source, buried the four lines an
# operator actually wanted.
usage() { sed -n '2,/^set -euo pipefail/p' "$0" | grep '^#' | sed 's/^# \{0,1\}//'; }

INSTALL_STARTED=0      # set to 1 immediately before the first destructive action
INSTALL_COMPLETED=0    # set to 1 once every requested component is fully in place
on_exit() {
    local rc=$?
    # The generated templates live in a scratch directory until they are
    # verified; never leave one behind, whatever killed us.
    [ -z "${CLASSIC_TPL_TMP:-}" ] || rm -rf "$CLASSIC_TPL_TMP"
    # Nothing to warn about for --help, a bad flag, or a failed precondition:
    # those exit before anything on the panel has been touched.
    [ "$INSTALL_STARTED" -eq 1 ] || return 0
    [ "$INSTALL_COMPLETED" -eq 0 ] || return 0
    local unstamped="" d
    if [ -n "${WANT_THEME:-}" ] && [ -n "${THEMES_DIR:-}" ]; then
        for d in ${DESIGNS:-clarity}; do
            if [ -d "$THEMES_DIR/$d" ] && [ ! -f "$THEMES_DIR/$d/ispconfig_version" ]; then
                unstamped="$unstamped $d"
            fi
        done
    fi
    echo "" >&2
    echo "*** INSTALL DID NOT COMPLETE (exit $rc) ***" >&2
    if [ -n "$unstamped" ]; then
        echo "These design directories are in place but NOT version-stamped:$unstamped" >&2
        echo "ISPConfig will reset every user on them to the default theme at their next" >&2
        echo "login, without showing an error. Re-run this script to finish — it is safe" >&2
        echo "to repeat:" >&2
        echo "    $0 ${ORIGINAL_ARGS:-}" >&2
    else
        echo "Re-run this script to finish. It is safe to run repeatedly." >&2
    fi
}
trap on_exit EXIT
ORIGINAL_ARGS="$*"

MODE="symlink"
ISPC_ROOT="/usr/local/ispconfig"
ASSIGN=1
WANT_THEME=""
WANT_MODULE=""
DESIGNS=""              # empty until --design is seen; defaults to clarity below
DESIGN_EXPLICIT=0       # 1 once --design is given, so an ignored one can be reported
CLASSIC_TPL_TMP=""      # scratch dir for the generated templates (see on_exit)

# Every design this repository ships, in install order. `classic` last so the
# "Design picker" lists them in the order the docs introduce them.
ALL_DESIGNS="clarity classic"

# Add a design to the list unless it is already there — --design=all followed by
# --design=classic must not deploy classic twice.
want_design() {
  local d
  for d in $DESIGNS; do [ "$d" = "$1" ] && return 0; done
  DESIGNS="${DESIGNS:+$DESIGNS }$1"
}

for arg in "$@"; do
  case "$arg" in
    --theme)     WANT_THEME=1 ;;
    --module)    WANT_MODULE=1 ;;
    --all)       WANT_THEME=1; WANT_MODULE=1 ;;
    --design=*)
      DESIGN_EXPLICIT=1
      case "${arg#--design=}" in
        all)     for d in $ALL_DESIGNS; do want_design "$d"; done ;;
        clarity) want_design clarity ;;
        classic) want_design classic ;;
        # A typo here must not silently install the wrong design (or none):
        # ISPConfig only shows a design in its picker once it exists on disk.
        *) echo "ERROR: unknown design: ${arg#--design=} (known: $ALL_DESIGNS, all)" >&2; exit 2 ;;
      esac
      ;;
    # Caught explicitly so the value-less form gets a useful message instead of
    # the catch-all's "unknown option: --design".
    --design)    echo "ERROR: --design needs a value, e.g. --design=classic" >&2; exit 2 ;;
    --copy)      MODE="copy" ;;
    --no-assign) ASSIGN=0 ;;
    # grep -v '^#!' so the shebang does not print as a stray "!/usr/bin/env bash"
    -h|--help)   usage; exit 0 ;;
    -*)          echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *)           ISPC_ROOT="$arg" ;;
  esac
done

# with no component flag, both are installed, so a bare ./install.sh keeps doing
# the obvious thing
if [ -z "$WANT_THEME" ] && [ -z "$WANT_MODULE" ]; then WANT_THEME=1; WANT_MODULE=1; fi
# ...and with no --design it means clarity, exactly as it did before classic
# existed. Adding a second design to the panel's picker is something you ask
# for, not something an upgrade does to you.
if [ -z "$DESIGNS" ]; then DESIGNS="clarity"; fi

# --design only means anything when a design is actually being installed. Naming
# one alongside --module used to parse fine, set DESIGNS, and then install just
# the Branding page — a successful run that silently ignored what the operator
# asked for. Say so rather than let them discover it in the Design picker.
if [ "$DESIGN_EXPLICIT" -eq 1 ] && [ -z "$WANT_THEME" ]; then
  echo "NOTE: --design was given but no design is installed with --module alone." >&2
  echo "      Use --all (or drop --module) to install a design as well." >&2
fi

ROOT="$(cd "$(dirname "$0")" && pwd)"
WEB_DIR="$ISPC_ROOT/interface/web"
THEMES_DIR="$WEB_DIR/themes"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"

say "ispconfig-theme-customizer installer"
say "  source     : $ROOT"
say "  target     : $ISPC_ROOT"
say "  installing : ${WANT_THEME:+theme }${WANT_MODULE:+module}"
if [ -n "$WANT_THEME" ]; then say "  design(s)  : $DESIGNS"; fi
say "  mode       : $MODE"
say

if [ ! -d "$WEB_DIR" ]; then
  echo "ERROR: $WEB_DIR not found — is ISPCONFIG_ROOT correct?" >&2
  echo "       pass it explicitly, e.g.  ./install.sh /usr/local/ispconfig" >&2
  exit 1
fi

# --- helpers ----------------------------------------------------------------

# A symlinked directory is SERVED through the link, so editor/agent/vcs state
# inside the source tree would be reachable over HTTP (e.g. /customizer/.omc/...).
check_stray() {
  local src="$1"
  local stray
  stray="$(find "$src" \( -name '.omc' -o -name '.git' -o -name 'node_modules' \) -print -quit 2>/dev/null || true)"
  [ -n "$stray" ] || return 0
  if [ "$MODE" = "symlink" ]; then
    echo "ERROR: $src contains $stray" >&2
    echo "       A symlinked directory is SERVED as-is. Remove it first:" >&2
    echo "         find '$src' \\( -name .omc -o -name .git \\) -prune -exec rm -rf {} +" >&2
    echo "       ...or install with --copy, which excludes them." >&2
    exit 1
  fi
  say "NOTE: excluding $stray (and any other .omc/.git/node_modules) from the copy."
}

# The web server reads a symlinked directory THROUGH the link, so every ancestor
# of this clone must be traversable by it. A clone under /root (mode 700) serves
# nothing — and would otherwise fail silently at runtime, not here.
check_traversable() {
  [ "$MODE" = "symlink" ] || return 0
  local p="$ROOT"
  while [ "$p" != "/" ]; do
    local o
    o="$(stat -c '%a' "$p" 2>/dev/null || echo 7)"; o="${o: -1}"
    if [ $(( o % 2 )) -eq 0 ]; then
      echo "WARNING: $p is not world-traversable (others have no 'x' bit)." >&2
      echo "         The panel's web server likely cannot read the symlinked files" >&2
      echo "         from here (classic case: a clone under /root)." >&2
      echo "         Move the clone somewhere readable (e.g. /opt/ispconfig-theme-customizer)" >&2
      echo "         and re-run, or install with --copy instead." >&2
      return 0
    fi
    p="$(dirname "$p")"
  done
}

# $1 = source dir, $2 = destination dir, $3 = parent of source (tar -C), $4 = basename
deploy() {
  local src="$1" dest="$2" parent="$3" name="$4"
  if [ -e "$dest" ] || [ -L "$dest" ]; then
    say "  removing existing $dest"
    rm -rf "$dest"
  fi
  if [ "$MODE" = "symlink" ]; then
    ln -s "$src" "$dest"
    say "  symlinked $name into place."
  else
    # tar, not `cp -a`, so the excludes are actually honoured
    tar cf - --exclude='.omc' --exclude='.git' --exclude='node_modules' \
        -C "$parent" "$name" | tar xf - -C "$(dirname "$dest")"
    say "  copied $name into place."
    if id -u ispconfig >/dev/null 2>&1; then
      chown -R ispconfig:ispconfig "$dest" 2>/dev/null || true
    fi
  fi
}

# Generate classic's two shell templates from the TARGET PANEL's stock ones.
# $1 = directory to write them into (a scratch dir; the caller places them).
#
# The whole transform is three mechanical changes, and nothing else may differ:
#
#   1. themes/<tmpl_var name='current_theme'>/assets/  ->  themes/default/assets/
#      Template fallback does NOT extend to assets. Under classic the stock
#      markup would ask for themes/classic/assets/…, a directory that does not
#      exist and never will, and every stylesheet, script and icon on the page
#      would 404. Pinning them to the default theme is what lets classic ship
#      no assets at all.
#
#   2. brand.php, title.php and favicon.php linked immediately before </head>,
#      so the design can read the brand contract. Nothing else reads it on a
#      stock shell.
#
#   3. every <link rel='icon'> / rel='shortcut icon'> is REPLACED by the
#      favicon.php link from change 2. Change 1 alone would leave them
#      pinned to themes/default/assets/favicon/ — i.e. serving the ISPConfig
#      mark on every tab of a panel whose whole point is to carry someone
#      else's brand. The endpoint falls back to those very files when nothing is
#      stored, so an unbranded panel is unchanged.
#      Stock declares the icon three times (two sized PNGs and the legacy
#      shortcut .ico) and the replacement is ONE unsized, untyped link: what the
#      endpoint returns depends on what the operator stored, and a browser skips
#      a link whose declared type it cannot render. That makes this the one
#      change that alters the output's LINE COUNT, so the count check below is
#      derived from how many icon links were actually removed rather than being
#      a constant — see it for the arithmetic.
#      The other icon references (apple-touch-icon, mask-icon, manifest, tile
#      config) are deliberately left pointing at stock's assets: they are
#      platform install artefacts with their own size and format contracts, not
#      the tab icon, and each would need its own endpoint to be brandable.
#
# Deliberately NOT added: a "generated file, do not edit" banner. It would ship
# in the HTML of a pre-authentication page and announce, to anyone who can reach
# the login screen, which extension this panel runs — the exact disclosure a
# white-label install is for. The warning lives in themes/classic/README.md and
# in .gitignore instead.
generate_classic_templates() {
  local outdir="$1"
  local stock="$THEMES_DIR/default/templates"
  local find_str="themes/<tmpl_var name='current_theme'>/assets/"
  local tpl src out prefix scene ins1 ins2 ins3 src_n out_n rc icons out_icons

  # ONE definition of "this line is a tab-icon <link>", used by the awk transform
  # (which deletes them) and by the shell check afterwards (which proves exactly
  # one survives and that it is ours). POSIX character classes rather than \t so
  # awk and grep -E read the pattern identically. Anchored at the start of the
  # line: a <link> that does not begin its line is not a shape this generator
  # claims to understand, and it is refused loudly below rather than mangled.
  local icon_re="^[[:space:]]*<link[^>]*rel=['\"](shortcut icon|icon)['\"]"
  local cnt_file="$outdir/.nzc_icon_count"

  for tpl in main.tpl.htm main_login.tpl.htm; do
    src="$stock/$tpl"
    out="$outdir/$tpl"
    if [ ! -f "$src" ]; then
      echo "ERROR: $src not found — classic is generated from the panel's own stock" >&2
      echo "       templates and cannot be built without them." >&2
      return 1
    fi

    # How deep the page using this template is served from. The app frame is
    # rendered at the web root and links its assets as 'themes/…'; the login
    # shell is rendered from /login/ and links '../themes/…'. Root-relative
    # '/themes/…' would work on a panel mounted at the docroot and break on one
    # behind a sub-path, so take whichever prefix the stock markup itself uses
    # and stay exactly as portable as core is.
    if grep -q -F "href='../${find_str}stylesheets/" "$src"; then
      prefix='../'
    elif grep -q -F "href='${find_str}stylesheets/" "$src"; then
      prefix=''
    else
      echo "ERROR: cannot tell how $tpl links its stylesheets." >&2
      echo "       This ISPConfig's stock shell is not the shape classic knows how to" >&2
      echo "       transform. Install clarity instead (--design=clarity) and open an" >&2
      echo "       issue with your ISPConfig version." >&2
      return 1
    fi

    # Only the login shell gets ?scene=login. brand.php has a few rules that must
    # apply to the login screen alone (its background, its one primary button),
    # and stock's login <body> carries no class, no id and nothing stable to hang
    # a selector on — clarity adds one in its own template, classic may not touch
    # the markup. So the scope travels in the URL instead of the DOM.
    scene=''
    [ "$tpl" = "main_login.tpl.htm" ] && scene='?scene=login'

    ins1="  <link rel='stylesheet' href='${prefix}themes/classic/brand.php${scene}' />"
    ins2="  <script src='${prefix}themes/classic/title.php'></script>"
    # No type/sizes attributes, and the same ${prefix} as the endpoints above so
    # a panel served from a sub-path keeps working (stock links its favicons
    # root-relative, but its stylesheets tell us what this panel can rely on).
    ins3="  <link rel='icon' href='${prefix}themes/classic/favicon.php'>"

    # The footer credits have to become individually addressable, or the Branding
    # page ends up with two toggles that do nothing on this design. Stock renders
    # the whole line as bare text inside <footer id='footer'>, so CSS can only
    # hide all of it or none of it. Wrapping core's own line in one span and
    # appending ours in a second gives show_ispconfig_credit and
    # show_theme_credit a target each — the same shape clarity's template uses.
    #
    # Both ship ON. Nothing is hidden until an administrator chooses to hide it,
    # and licence notices are never touched by either toggle — these are courtesy
    # lines, which is a different thing.
    cred2="<span class='nzc-credit-theme'><span class='nzc-credit-sep'> &middot; </span><a href='https://github.com/wadejbeckett/ispconfig-theme-customizer' target='_blank' rel='noopener'>Classic</a></span>"

    # index()/substr rather than gsub(): the search string is a literal full of
    # regex metacharacters, and a literal search cannot be defeated by quoting.
    #
    # Exit status is a three-way answer, not a boolean: 0 transformed, 1 no
    # </head> (nowhere to link anything), 2 an icon <link> that shares its line
    # with other markup — a shape this generator does not know how to rewrite,
    # which must stop the install rather than silently drop whatever else was on
    # that line. The END rule's own exit would override the earlier one, hence
    # the `aborted` guard.
    rc=0
    awk -v find="$find_str" -v repl="themes/default/assets/" \
        -v ins1="$ins1" -v ins2="$ins2" -v ins3="$ins3" -v cred2="$cred2" \
        -v icon_re="$icon_re" -v cnt_file="$cnt_file" '
        function lreplace(s,   acc, p) {
            acc = ""
            while ((p = index(s, find)) > 0) {
                acc = acc substr(s, 1, p - 1) repl
                s   = substr(s, p + length(find))
            }
            return acc s
        }
        # how many tags start on this line — one is the shape we handle
        function tag_count(s,   parts) { return split(s, parts, "<") - 1 }
        { line = lreplace($0) }
        line ~ icon_re {
            if (tag_count(line) != 1) { aborted = 1; exit 2 }
            icons++
            next
        }
        !done && line ~ /<\/head>/ { print ins3; print ins1; print ins2; done = 1 }
        # Split the footer credit into two addressable spans, preserving stock
        # indentation. Matching on both "powered by" and app_link keeps this off
        # any other line that happens to contain one of them.
        !fdone && line ~ /powered by/ && line ~ /app_link/ {
            match(line, /^[ \t]*/)
            ind  = substr(line, 1, RLENGTH)
            body = substr(line, RLENGTH + 1)
            printf "%s<span class=\047nzc-credit-ispconfig\047>%s</span>\n", ind, body
            printf "%s%s\n", ind, cred2
            fdone = 1
            next
        }
        { print line }
        END { print icons + 0 > cnt_file; if (!aborted && !done) exit 1 }
    ' "$src" > "$out" || rc=$?

    if [ "$rc" -eq 2 ]; then
      echo "ERROR: an icon <link> shares its line with other markup in $src:" >&2
      grep -nE "$icon_re" "$src" >&2 || true
      echo "       classic replaces those links with one pointing at its favicon" >&2
      echo "       endpoint, and it will not delete a line carrying anything else." >&2
      echo "       Install clarity instead (--design=clarity) and open an issue with" >&2
      echo "       your ISPConfig version." >&2
      return 1
    elif [ "$rc" -ne 0 ]; then
      echo "ERROR: no </head> in $src — nowhere to link the brand endpoints." >&2
      return 1
    fi

    # How many icon links awk removed. Read from the file awk wrote rather than
    # counted again here: one definition of the pattern, one engine applying it,
    # so the count and the deletion can never disagree.
    icons="$(cat "$cnt_file" 2>/dev/null || true)"
    case "$icons" in
      ''|*[!0-9]*)
        echo "ERROR: the template transform did not report how many icon links it" >&2
        echo "       removed from $tpl, so its output cannot be verified." >&2
        return 1
        ;;
    esac

    # --- verify the output is the stock file plus exactly those changes ------
    # A silent miss here is the worst outcome this script has: a classic shell
    # that still points at themes/classic/assets/ serves a panel with every
    # stylesheet and script 404ing, and nothing logs it.
    src_n="$(awk 'END{print NR}' "$src")"
    out_n="$(awk 'END{print NR}' "$out")"
    # +3 for the three <head> links (favicon, brand, title), MINUS the icon links
    # that were replaced by the first of them — stock declares the tab icon three
    # times, so this is normally a net zero on the app frame and the login shell
    # alike. Deriving it from the count awk reported, rather than hardcoding
    # "minus 3", is what lets this check keep working on an ISPConfig that ships
    # a different number of icon links: the check moves with the transform
    # instead of having to be relaxed.
    # +1 more if the footer credit was split in two. That split is expected in
    # the app frame and absent from the login shell, which has no footer — so it
    # too is derived from the output rather than assumed, and a future ISPConfig
    # that moves the footer degrades to a warning instead of failing an install
    # over a courtesy line.
    expected=$(( src_n + 3 - icons ))
    if grep -q "nzc-credit-ispconfig" "$out"; then expected=$(( expected + 1 )); fi
    if [ "$out_n" -ne "$expected" ]; then
      echo "ERROR: generated $tpl is $out_n lines, expected $expected" >&2
      echo "       (stock $src_n + 3 head links - $icons icon links replaced)." >&2
      return 1
    fi
    if [ "$tpl" = "main.tpl.htm" ] && ! grep -q "nzc-credit-ispconfig" "$out"; then
      echo "WARNING: could not find the footer credit line in $src, so the" >&2
      echo "         'hide footer credits' toggles will have no effect on classic." >&2
      echo "         Everything else works; please report your ISPConfig version." >&2
    fi
    # After the rewrite the template must not reference the active theme AT ALL.
    # If a future ISPConfig uses current_theme for something other than an asset
    # path, that path would resolve into classic's non-existent directory too —
    # so this check failing means "look at it", not "loosen the check".
    if grep -q "current_theme" "$out"; then
      echo "ERROR: $tpl still references current_theme after the rewrite:" >&2
      grep -n "current_theme" "$out" >&2
      echo "       Under classic those paths resolve into themes/classic/, which holds" >&2
      echo "       no assets. Refusing to install a shell that would 404 everything." >&2
      return 1
    fi
    if ! grep -q -F "themes/classic/brand.php" "$out" || ! grep -q -F "themes/classic/title.php" "$out" \
       || ! grep -q -F "themes/classic/favicon.php" "$out"; then
      echo "ERROR: the brand endpoints are missing from the generated $tpl." >&2
      return 1
    fi

    # Exactly one tab-icon link, and it is ours. This is the check that catches
    # an icon <link> written in a shape the pattern above did not recognise: the
    # stock one would survive alongside ours, the browser would be free to pick
    # either, and a panel would keep flying the ISPConfig flag on some tabs and
    # the operator's on others — the kind of half-applied white-label that gets
    # noticed by a customer rather than by a check.
    out_icons="$(grep -cE "$icon_re" "$out" || true)"
    if [ "$out_icons" != "1" ]; then
      echo "ERROR: generated $tpl carries $out_icons tab-icon <link> tags, expected 1:" >&2
      grep -nE "$icon_re" "$out" >&2 || true
      return 1
    fi
  done

  # The scratch dir is copied from by name, not wholesale, so this only tidies —
  # but a leftover counter file in a directory the caller may one day copy
  # entirely is exactly the sort of thing that ships by accident.
  rm -f "$cnt_file"
}

# --- warn about a previous split installation -------------------------------
# Before v3.0.0 this shipped as two repos (clarity-theme-ispconfig and
# ispconfig-customizer), typically cloned to /root/clarity-theme and
# /root/ispconfig-customizer. Installing from here replaces what they deployed,
# but their clones linger and re-running one of THEIR installers afterwards
# would silently overwrite this install with older code.
for old in /root/clarity-theme /root/ispconfig-customizer /opt/clarity-theme-ispconfig /opt/ispconfig-customizer; do
  if [ -d "$old" ] && [ "$old" != "$ROOT" ]; then
    say "NOTE: found a clone of the pre-3.0.0 split layout at $old"
    say "      This is now one project on one version number, in this repository."
    say "      That clone is stale, and its versions were never checked against"
    say "      each other — remove it once this install is verified, so nobody"
    say "      re-runs its installer."
    say
  fi
done

# From here on the panel gets modified, so the EXIT trap's warning becomes
# meaningful. Everything above this line is read-only checks.
INSTALL_STARTED=1

# --- theme ------------------------------------------------------------------
if [ -n "$WANT_THEME" ]; then
  [ -d "$THEMES_DIR" ] || { echo "ERROR: $THEMES_DIR not found — is ISPCONFIG_ROOT correct?" >&2; exit 1; }
  # Both designs lean on the stock theme: Clarity inherits its vendor assets,
  # classic inherits everything AND is generated from its templates.
  [ -d "$THEMES_DIR/default" ] || { echo "ERROR: $THEMES_DIR/default missing — the designs inherit vendor assets from it." >&2; exit 1; }

  # --- detect ISPC_APP_VERSION once, stamp every design with it ------------
  # `|| true`: an unmatched grep must fall through to the WARNING branch, not
  # abort via set -e/pipefail after a theme is already in place.
  VERSION=""
  if [ -f "$CONF" ]; then
    VERSION="$(grep -oE "define\(['\"]ISPC_APP_VERSION['\"],[[:space:]]*['\"][^'\"]+['\"]" "$CONF" \
               | grep -oE "['\"][^'\"]+['\"][[:space:]]*\)?$" | tail -1 | tr -d "'\"" || true)"
  fi

  for THEME_NAME in $DESIGNS; do
    SRC="$ROOT/themes/$THEME_NAME"
    DEST="$THEMES_DIR/$THEME_NAME"

    say "Design ($THEME_NAME):"
    [ -d "$SRC" ] || { echo "ERROR: source theme not found at $SRC" >&2; exit 1; }

    check_stray "$SRC"

    # classic's shell is built from the panel's own stock templates. Build and
    # VERIFY it before the directory exists on the panel, not after: between
    # deploy() and the templates landing, classic would be a theme ISPConfig
    # lists in its Design picker (a directory with no version file is offered)
    # whose every asset path resolves into an empty directory. Generating first
    # shrinks that window to a single move of already-checked files.
    if [ "$THEME_NAME" = "classic" ]; then
      CLASSIC_TPL_TMP="$(mktemp -d)"
      if ! generate_classic_templates "$CLASSIC_TPL_TMP"; then
        echo "ERROR: could not generate classic's shell templates — nothing was installed" >&2
        echo "       for this design." >&2
        exit 1
      fi
    fi

    deploy "$SRC" "$DEST" "$ROOT/themes" "$THEME_NAME"

    if [ "$THEME_NAME" = "classic" ]; then
      # In symlink mode this writes THROUGH the link into the clone, which is
      # where the generated templates belong (themes/classic/.gitignore keeps
      # them out of git). Replaced wholesale every run, so a template left over
      # from an older ISPConfig can never survive an upgrade.
      rm -rf "$DEST/templates"
      mkdir -p "$DEST/templates"
      cp "$CLASSIC_TPL_TMP/main.tpl.htm" "$CLASSIC_TPL_TMP/main_login.tpl.htm" "$DEST/templates/"
      rm -rf "$CLASSIC_TPL_TMP"; CLASSIC_TPL_TMP=""
      # Explicit modes, because these are the only files here created from
      # scratch rather than unpacked by tar (which restores the archived mode and
      # ignores the umask). Under a hardened root umask such as 027 they would
      # otherwise land unreadable to the panel's web user, and an unreadable
      # shell template renders the entire panel from the stock fallback with
      # every asset path pointing into themes/classic/ — a blank panel.
      chmod 755 "$DEST/templates" 2>/dev/null || true
      chmod 644 "$DEST/templates/main.tpl.htm" "$DEST/templates/main_login.tpl.htm" 2>/dev/null || true
      if [ "$MODE" = "copy" ] && id -u ispconfig >/dev/null 2>&1; then
        chown -R ispconfig:ispconfig "$DEST/templates" 2>/dev/null || true
      fi
      say "  generated templates/main.tpl.htm + main_login.tpl.htm from this panel's"
      say "  stock shell (verified: stock markup, asset paths pinned to themes/default,"
      say "  brand endpoints linked)."
    fi

    if [ -n "$VERSION" ]; then
      printf '%s' "$VERSION" > "$DEST/ispconfig_version"
      printf '%s' "$VERSION" > "$DEST/ISPC_VERSION"
      # deploy() chowned the tree before these two existed, so chown them too —
      # otherwise they are the only root-owned files in an ispconfig-owned theme
      if [ "$MODE" = "copy" ] && id -u ispconfig >/dev/null 2>&1; then
          chown ispconfig:ispconfig "$DEST/ispconfig_version" "$DEST/ISPC_VERSION" 2>/dev/null || true
      fi
      say "  stamped ispconfig_version + ISPC_VERSION = '$VERSION'"
    else
      # Leave NO stale stamp behind: a version file from a previous install against
      # a different panel is worse than none — it makes the theme look selectable
      # and then silently resets every user at login.
      rm -f "$DEST/ispconfig_version" "$DEST/ISPC_VERSION"
      echo "WARNING: could not detect ISPC_APP_VERSION from $CONF." >&2
      echo "         Removed any stale stamps from $THEME_NAME. Create BOTH files with your exact version:" >&2
      echo "           V=\$(php -r \"require '$CONF'; echo ISPC_APP_VERSION;\")" >&2
      echo "           printf '%s' \"\$V\" > $DEST/ispconfig_version" >&2
      echo "           printf '%s' \"\$V\" > $DEST/ISPC_VERSION" >&2
      echo "         Without ispconfig_version, ISPConfig resets the theme to 'default' at login." >&2
    fi
    say
  done

  # --- version-disclosure advisory -----------------------------------------
  # Those two files sit inside a directory the panel serves statically, so
  # `curl https://panel:8080/themes/clarity/ispconfig_version` returns the exact
  # ISPConfig version with no session. Core cannot be asked to rename them (it
  # reads those exact names), so the fix belongs in the web server. This matters
  # more here than for a stock theme: the Branding page offers a "hide the
  # version" toggle, and serving it as a static file undercuts that entirely.
  say "  SECURITY NOTE: ispconfig_version and ISPC_VERSION in each design directory"
  say "  are served over HTTP and disclose your exact ISPConfig version to anyone who"
  say "  can reach the login page. Deny them at the web-server layer."
  if [ -d "$ROOT/contrib/webserver" ]; then
    say "  Ready-made Apache and nginx snippets: contrib/webserver/ (see its README —"
    say "  the rules match any design directory, so they cover classic too)."
  fi
  say
fi

# --- module -----------------------------------------------------------------
if [ -n "$WANT_MODULE" ]; then
  SRC="$ROOT/interface/web/customizer"
  DEST="$WEB_DIR/customizer"

  say "Branding page:"
  [ -d "$SRC" ] || { echo "ERROR: source not found at $SRC" >&2; exit 1; }

  check_stray "$SRC"
  deploy "$SRC" "$DEST" "$ROOT/interface/web" "customizer"

  if [ "$ASSIGN" -eq 1 ]; then
    if command -v php >/dev/null 2>&1 && [ -f "$CONF" ]; then
      say "  granting admin users access to Branding:"
      # Capture the helper's output instead of letting it inherit our stdout.
      # If stdout is a pipe the operator has closed, the helper's own writes fail
      # and it exits non-zero — which used to be reported as "automatic
      # assignment failed" when the assignment had in fact succeeded. A false
      # alarm that sends someone hand-editing CP Users is worse than no message.
      if assign_out="$(php "$ROOT/bin/assign_module.php" "$CONF" 2>&1)"; then
        [ -n "$assign_out" ] && say "$assign_out"
      else
        echo "WARNING: automatic assignment failed — add 'customizer' by hand in" >&2
        echo "         System > CP Users > edit the admin user > Modules." >&2
        [ -n "$assign_out" ] && echo "$assign_out" >&2
      fi
    else
      say "  NOTE: could not auto-assign (php CLI or $CONF missing)."
      say "        Add it by hand: System > CP Users > edit the admin user >"
      say "        Modules > tick 'customizer' > Save."
    fi
  fi
  say
fi

check_traversable

# --- closing instructions ---------------------------------------------------
# Every requested component is in place and, for the theme, stamped. Anything
# after this point is text; a failure here must not read as a broken install.
INSTALL_COMPLETED=1

{
  say "Done."
  say
  # ONE numbered list, in the order an operator actually performs them: switch
  # the panel over to the design first, then log back in and brand it. The steps
  # differ because ISPConfig applies them in different places, not because there
  # is more than one thing installed here.
  say "Next steps:"
  STEP=0
  # The design to put in the config example. With more than one installed the
  # first requested wins — a config file holds exactly one theme, and guessing
  # differently from the operator's own flag order would be worse than picking.
  PRIMARY_DESIGN="${DESIGNS%% *}"
  if [ -n "$WANT_THEME" ]; then
    STEP=$((STEP + 1))
    if [ "$DESIGNS" = "$PRIMARY_DESIGN" ]; then
      say "  $STEP. Per user:  Tools > User Settings > Design > select \"$PRIMARY_DESIGN\" > Save."
    else
      say "  $STEP. Per user:  Tools > User Settings > Design > select one of \"$DESIGNS\" > Save."
    fi
    say "                Core updates your session and reloads the page, so it"
    say "                applies immediately. If the frame still looks stock,"
    say "                hard-refresh (Ctrl+Shift+R) to drop the cached CSS."
    STEP=$((STEP + 1))
    say "  $STEP. System wide + login screen — set in BOTH config files:"
    say
    say "       \$conf['theme'] = '$PRIMARY_DESIGN';"
    say
    say "     interface/lib/config.inc.php   takes effect immediately (login page +"
    say "                                    default for new users)"
    say "     server/lib/config.inc.php      makes it survive ISPConfig updates — the"
    say "                                    updater regenerates both configs and carries"
    say "                                    the theme forward from the SERVER config"
    say
    say "     Then hard-refresh the browser (Ctrl+Shift+R) so the new CSS is picked up."
  fi
  if [ -n "$WANT_MODULE" ]; then
    STEP=$((STEP + 1))
    say "  $STEP. Re-log in (or reload the panel) so \"Branding\" appears in the top navigation."
    STEP=$((STEP + 1))
    say "  $STEP. Open Branding and set your logo, panel name, colours and login details."
    say "     Logo, panel name and the news-feed toggle also apply to the STOCK ISPConfig"
    say "     theme — core reads those itself. Accent colour, login background and version"
    say "     hiding need a brand-aware design: clarity, or classic if you want to keep"
    say "     the stock look (--design=classic)."
  fi
  say
  if [ -n "$WANT_THEME" ]; then
    say "IMPORTANT — after ANY ISPConfig upgrade, including patch releases such as"
    say "3.3.1p1 -> 3.3.1p2, re-run this script so the version gate is re-stamped."
    say "Skip it and the design silently reverts to 'default' at every login."
    for d in $DESIGNS; do
      case "$d" in
        classic)
          say "For classic that same run also regenerates its two shell templates from the"
          say "new stock ones, so it stays the stock look by construction — there is"
          say "nothing to diff by hand."
          ;;
        *)
          say "For $d, then diff the seven overridden templates (three shell + four dashboard)"
          say "against the new stock ones — they are listed with their pinned"
          say "contracts in themes/$d/BUILT-AGAINST.txt."
          ;;
      esac
    done
    say
  fi
  say "No ISPConfig core file was modified."
}
