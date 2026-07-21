#!/usr/bin/env bash
#
# ispconfig-customizer — uninstaller.
# Cleanly reverses install.sh. Touches NOTHING in ISPConfig core: it removes
# the module directory, strips the module from every user's module list (and
# resets any startmodule that pointed at it), and — only when explicitly asked
# — wipes the stored branding values.
#
# By default your branding SURVIVES uninstall (reinstall-friendly): the panel
# name / login text / logo are stock ISPConfig fields that keep working (and
# remain editable under System > Interface Config), and the [branding] colours
# stay inert in sys_ini for any brand-aware theme.
#
# Usage:
#   ./uninstall.sh [--purge-branding] [--keep-assignment] [ISPCONFIG_ROOT]
#     ISPCONFIG_ROOT     defaults to /usr/local/ispconfig
#     --purge-branding   also wipe ALL branding: drop [branding], blank the
#                        panel name / login text / login link, clear the logo
#     --keep-assignment  leave 'customizer' in users' module lists (only
#                        removes the directory; the nav entry disappears
#                        anyway because the module dir is gone)
#
set -euo pipefail

ISPC_ROOT="/usr/local/ispconfig"
PURGE=0
UNASSIGN=1

for arg in "$@"; do
  case "$arg" in
    --purge-branding) PURGE=1 ;;
    --keep-assignment) UNASSIGN=0 ;;
    -h|--help) grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*) echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *) ISPC_ROOT="$arg" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")" && pwd)"
DEST="$ISPC_ROOT/interface/web/customizer"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"
PHP_BIN="$(command -v php || true)"

echo "ispconfig-customizer uninstaller"
echo "  target : $DEST"
echo "  purge  : $([ "$PURGE" = 1 ] && echo yes || echo 'no (branding values preserved)')"
echo

if [ ! -e "$DEST" ] && [ ! -L "$DEST" ]; then
  echo "  module directory not present at $DEST (already removed)"
fi

# --- 1. database cleanup first (the scripts live in this repo, not in DEST) --
if [ -z "$PHP_BIN" ]; then
  echo "WARNING: php CLI not found — skipping DB cleanup." >&2
  echo "         Run bin/unassign_module.php (and bin/purge_branding.php if wanted) manually." >&2
else
  if [ "$UNASSIGN" = 1 ]; then
    echo "removing the module from user accounts:"
    "$PHP_BIN" "$ROOT/bin/unassign_module.php" "$CONF"
  fi
  if [ "$PURGE" = 1 ]; then
    echo "purging stored branding:"
    "$PHP_BIN" "$ROOT/bin/purge_branding.php" "$CONF"
  fi
fi

# --- 2. remove the module directory -----------------------------------------
if [ -e "$DEST" ] || [ -L "$DEST" ]; then
  rm -rf "$DEST"
  echo "removed $DEST"
fi

echo
echo "Done. ISPConfig core was not modified."
if [ "$PURGE" = 0 ]; then
  cat <<'EONOTE'
Branding values were preserved. To remove them later either reinstall and use
the module, edit System > Interface Config (panel name / login text), or run:
  php bin/purge_branding.php
EONOTE
fi
