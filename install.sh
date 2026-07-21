#!/usr/bin/env bash
#
# ispconfig-customizer — installer.
# Installs the white-label branding module by symlinking (default) or copying
# interface/web/customizer into an ISPConfig install, then assigns the module
# to admin users so it appears in the navigation. Touches NOTHING in ISPConfig
# core, and adds no database schema (it writes to the existing sys_ini row).
#
# Usage:
#   ./install.sh [--copy] [--no-assign] [ISPCONFIG_ROOT]
#     ISPCONFIG_ROOT defaults to /usr/local/ispconfig
#     --copy        copy the module instead of symlinking (use for packaged installs)
#     --no-assign   skip assigning the module to admin users (do it by hand in
#                   System > CP Users > edit the admin user > Modules)
#
set -euo pipefail

MODE="symlink"
ISPC_ROOT="/usr/local/ispconfig"
ASSIGN=1

for arg in "$@"; do
  case "$arg" in
    --copy) MODE="copy" ;;
    --no-assign) ASSIGN=0 ;;
    -h|--help) grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*) echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *) ISPC_ROOT="$arg" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")" && pwd)"
SRC="$ROOT/interface/web/customizer"
WEB_DIR="$ISPC_ROOT/interface/web"
DEST="$WEB_DIR/customizer"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"

echo "ispconfig-customizer installer"
echo "  source : $SRC"
echo "  target : $DEST"
echo "  mode   : $MODE"
echo

[ -d "$SRC" ] || { echo "ERROR: source not found at $SRC" >&2; exit 1; }
if [ ! -d "$WEB_DIR" ]; then
  echo "ERROR: $WEB_DIR not found — is ISPCONFIG_ROOT correct?" >&2
  echo "       pass it explicitly, e.g.  ./install.sh /usr/local/ispconfig" >&2
  exit 1
fi

# Refuse to ship editor/vcs state into a served directory — a symlinked module
# would expose it at /customizer/.omc/... etc.
STRAY="$(find "$SRC" \( -name '.omc' -o -name '.git' -o -name 'node_modules' \) -print -quit 2>/dev/null || true)"
if [ -n "$STRAY" ] && [ "$MODE" = "symlink" ]; then
  echo "ERROR: $SRC contains $STRAY — a symlinked module SERVES it." >&2
  echo "       Remove it first, or install with --copy." >&2
  exit 1
fi

# --- deploy -----------------------------------------------------------------
if [ -e "$DEST" ] || [ -L "$DEST" ]; then
  echo "Removing existing $DEST"
  rm -rf "$DEST"
fi
if [ "$MODE" = "symlink" ]; then
  ln -s "$SRC" "$DEST"
  echo "Symlinked customizer module into place."
else
  tar cf - --exclude='.omc' --exclude='.git' --exclude='node_modules' \
      -C "$ROOT/interface/web" customizer | tar xf - -C "$WEB_DIR"
  echo "Copied customizer module into place."
  if id -u ispconfig >/dev/null 2>&1; then
    chown -R ispconfig:ispconfig "$DEST" 2>/dev/null || true
  fi
fi

if [ "$MODE" = "symlink" ]; then
  # The web server reads the module THROUGH the symlink, so every ancestor of
  # this clone must be traversable by it. A clone under /root (mode 700) serves
  # nothing — and fails with no error here.
  p="$ROOT"
  while [ "$p" != "/" ]; do
    o="$(stat -c '%a' "$p" 2>/dev/null || echo 7)"; o="${o: -1}"
    if [ $(( o % 2 )) -eq 0 ]; then
      echo "WARNING: $p is not world-traversable (others have no 'x' bit)." >&2
      echo "         The panel's web server likely cannot read the symlinked module" >&2
      echo "         from here (classic case: a clone under /root)." >&2
      echo "         Move the clone somewhere readable (e.g. /opt/ispconfig-customizer)" >&2
      echo "         and re-run, or install with --copy instead." >&2
      break
    fi
    p="$(dirname "$p")"
  done
fi

# --- assign the module to admin users ---------------------------------------
if [ "$ASSIGN" -eq 1 ]; then
  if command -v php >/dev/null 2>&1 && [ -f "$CONF" ]; then
    echo
    echo "Assigning the module to admin users:"
    php "$ROOT/bin/assign_module.php" "$CONF" || {
      echo "WARNING: automatic assignment failed — add 'customizer' by hand in" >&2
      echo "         System > CP Users > edit the admin user > Modules." >&2
    }
  else
    echo
    echo "NOTE: could not auto-assign (php CLI or $CONF missing)."
    echo "      Add the module by hand: System > CP Users > edit the admin user >"
    echo "      Modules > tick 'customizer' > Save."
  fi
fi

cat <<'EOF'

Done. Next steps:

  1. Re-log in (or reload the panel) so the new module appears in the top navigation.
  2. Open the "Customizer" module and set your logo, panel name, colours and
     login details.
  3. Colours/login-background are applied by a brand-aware theme (e.g. the Clarity
     theme). The logo, panel name and login text also apply to the stock theme.

No ISPConfig core file was modified. This module survives ISPConfig upgrades; if
you install with --copy, re-run this after a major upgrade to refresh the files.
EOF
