#!/usr/bin/env bash
#
# the earlier name (earlier-dark) — ISPConfig theme deploy helper.
# Installs the theme by symlinking (default) or copying themes/earlier-dark
# into an ISPConfig install, and stamps the theme's `ispconfig_version`
# file to EXACTLY match the install's ISPC_APP_VERSION.
# Touches NOTHING in ISPConfig core.
#
# Why the stamp matters: ISPConfig gates themes by an EXACT version match.
#   - interface/web/login/index.php  resets a user's theme to 'default' at login
#     unless themes/<t>/ispconfig_version EXISTS and equals ISPC_APP_VERSION.
#   - tools/form/user_settings.tform.php only lists a theme in the Design picker
#     when it has no version file OR the file equals ISPC_APP_VERSION.
# The only state that satisfies BOTH is: file present AND == ISPC_APP_VERSION.
# That value is install-specific and changes on upgrade, so we stamp it here
# (and you re-run this after a major ISPConfig upgrade).
#
# Usage:
#   ./install.sh [--copy] [ISPCONFIG_ROOT]
#     ISPCONFIG_ROOT defaults to /usr/local/ispconfig
#     --copy   copy the theme instead of symlinking (use for packaged installs)
#
set -euo pipefail

MODE="symlink"
ISPC_ROOT="/usr/local/ispconfig"

for arg in "$@"; do
  case "$arg" in
    --copy) MODE="copy" ;;
    -h|--help) grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) ISPC_ROOT="$arg" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")" && pwd)"
THEMES_DIR="$ISPC_ROOT/interface/web/themes"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"

# earlier-dark = "the earlier name" — fully self-contained (own templates incl.
# topnav.tpl.htm + own CSS/fonts/images); it needs only the stock 'default'
# theme for vendor CSS/JS.
THEMES="earlier-dark"

echo "Earlier theme installer"
echo "  source : $ROOT/themes"
echo "  themes : $THEMES"
echo "  target : $THEMES_DIR"
echo "  mode   : $MODE"
echo

[ -d "$ROOT/themes/earlier-dark" ] || { echo "ERROR: source theme not found at $ROOT/themes/earlier-dark" >&2; exit 1; }
if [ ! -d "$THEMES_DIR" ]; then
  echo "ERROR: $THEMES_DIR not found — is ISPCONFIG_ROOT correct?" >&2
  echo "       pass it explicitly, e.g.  ./install.sh /usr/local/ispconfig" >&2
  exit 1
fi
[ -d "$THEMES_DIR/default" ] || { echo "ERROR: $THEMES_DIR/default missing — the earlier name inherits vendor assets from it." >&2; exit 1; }

# --- detect ISPC_APP_VERSION from the install -------------------------------
VERSION=""
if [ -f "$CONF" ]; then
  # `|| true`: an unmatched grep must fall through to the WARNING branch below,
  # not abort the script via set -e/pipefail before anything is installed.
  VERSION="$(grep -oE "define\(['\"]ISPC_APP_VERSION['\"],[[:space:]]*['\"][^'\"]+['\"]" "$CONF" \
             | grep -oE "['\"][^'\"]+['\"][[:space:]]*\)?$" | tail -1 | tr -d "'\"" || true)"
fi

# --- deploy -----------------------------------------------------------------
for T in $THEMES; do
  SRC="$ROOT/themes/$T"
  DEST="$THEMES_DIR/$T"

  # Refuse to ship editor/agent state into a served directory. `.omc/` in particular
  # is written into whatever directory a tool cd's into, and under themes/<t>/ it
  # would be world-servable at /themes/<t>/.omc/state/... by the panel's web server.
  STRAY="$(find "$SRC" \( -name '.omc' -o -name '.git' -o -name 'node_modules' \) -print -quit 2>/dev/null || true)"
  if [ -n "$STRAY" ]; then
    if [ "$MODE" = "symlink" ]; then
      echo "ERROR: $SRC contains $STRAY" >&2
      echo "       A symlinked theme SERVES it. Remove it first:" >&2
      echo "         find '$SRC' \\( -name .omc -o -name .git \\) -prune -exec rm -rf {} +" >&2
      exit 1
    fi
    echo "NOTE: excluding $STRAY (and any other .omc/.git/node_modules) from the copy."
  fi

  if [ -e "$DEST" ] || [ -L "$DEST" ]; then
    echo "Removing existing $DEST"
    rm -rf "$DEST"
  fi
  if [ "$MODE" = "symlink" ]; then
    ln -s "$SRC" "$DEST"; echo "Symlinked $T into place."
  else
    # tar, not `cp -a`, so the excludes above are actually honoured
    tar cf - --exclude='.omc' --exclude='.git' --exclude='node_modules' \
        -C "$ROOT/themes" "$T" | tar xf - -C "$THEMES_DIR"
    echo "Copied $T into place."
  fi

  # --- stamp the version gate (writes through the symlink to the served dir) ---
  # ISPConfig checks the theme version under TWO different filenames depending on
  # the screen (a core inconsistency): 'ispconfig_version' gates login + the
  # user-settings/admin-user/client/reseller theme pickers; 'ISPC_VERSION' gates the
  # admin "Default user settings" (tpl_default) form + client circles. Stamp BOTH so
  # the theme is selectable in every official picker.
  if [ -n "$VERSION" ]; then
    printf '%s' "$VERSION" > "$DEST/ispconfig_version"
    printf '%s' "$VERSION" > "$DEST/ISPC_VERSION"
    echo "  stamped $T ispconfig_version + ISPC_VERSION = '$VERSION'"
  else
    echo "WARNING: could not detect ISPC_APP_VERSION from $CONF." >&2
    echo "         Create BOTH version files in $DEST with EXACTLY your install's string:" >&2
    echo "           V=\$(php -r \"require '$CONF'; echo ISPC_APP_VERSION;\")" >&2
    echo "           printf '%s' \"\$V\" > $DEST/ispconfig_version" >&2
    echo "           printf '%s' \"\$V\" > $DEST/ISPC_VERSION" >&2
    echo "         Without ispconfig_version, ISPConfig resets the theme to 'default' at login." >&2
  fi
done

if id -u ispconfig >/dev/null 2>&1; then
  for T in $THEMES; do chown -R ispconfig:ispconfig "$THEMES_DIR/$T" 2>/dev/null || true; done
fi

cat <<'EOF'

Done. Next steps:

  1. Per user:    Tools > User Settings > Design > select "earlier-dark" > Save,
                  then LOG OUT AND BACK IN (the theme is applied at login).
  2. System wide + login screen — set in interface/lib/config.inc.php (update-safe):

       $conf['theme'] = 'earlier-dark';

     (server/lib/config.inc.php's theme does NOT affect the web UI — interface only.)

  3. Hard-refresh the browser (Ctrl+Shift+R) — the CSS is cache-busted with ?ver=2.

After a MAJOR ISPConfig upgrade, re-run this script so ispconfig_version is
re-stamped to the new ISPC_APP_VERSION (otherwise the theme silently reverts to
default at login), and diff the two shell templates against the new stock ones.

No ISPConfig core file was modified.
EOF
