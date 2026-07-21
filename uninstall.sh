#!/usr/bin/env bash
#
# Clarity Theme for ISPConfig — uninstaller.
# Cleanly reverses install.sh. Touches NOTHING in ISPConfig core: it removes
# the theme directory and (with --reset-users) flips sys_user.app_theme rows
# back to 'default' — core does NOT heal that column on its own; without the
# reset, affected users get a "theme not compatible" error banner at every
# login.
#
# The one thing this script will NEVER do is edit config.inc.php. If you set
# $conf['theme'] = 'clarity' at install time, revert that line to 'default'
# yourself in BOTH files (instructions printed at the end).
#
# Usage:
#   ./uninstall.sh [--reset-users] [ISPCONFIG_ROOT]
#     ISPCONFIG_ROOT  defaults to /usr/local/ispconfig
#     --reset-users   also reset sys_user.app_theme 'clarity' -> 'default'
#                     (recommended; skip only if you are reinstalling)
#
set -euo pipefail

ISPC_ROOT="/usr/local/ispconfig"
RESET_USERS=0

for arg in "$@"; do
  case "$arg" in
    --reset-users) RESET_USERS=1 ;;
    -h|--help) grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*) echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *) ISPC_ROOT="$arg" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")" && pwd)"
DEST="$ISPC_ROOT/interface/web/themes/clarity"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"
SERVER_CONF="$ISPC_ROOT/server/lib/config.inc.php"
PHP_BIN="$(command -v php || true)"

echo "Clarity theme uninstaller"
echo "  target      : $DEST"
echo "  reset users : $([ "$RESET_USERS" = 1 ] && echo yes || echo no)"
echo

# --- 1. remove the theme directory ------------------------------------------
if [ -e "$DEST" ] || [ -L "$DEST" ]; then
  rm -rf "$DEST"
  echo "removed $DEST"
else
  echo "  theme directory not present at $DEST (already removed)"
fi

# --- 2. reset users' theme choice -------------------------------------------
if [ "$RESET_USERS" = 1 ]; then
  if [ -z "$PHP_BIN" ]; then
    echo "WARNING: php CLI not found — run bin/reset_app_theme.php manually," >&2
    echo "         or users on 'clarity' will see a theme error at every login." >&2
  else
    "$PHP_BIN" "$ROOT/bin/reset_app_theme.php" "$CONF"
  fi
else
  echo "  (skipped app_theme reset — pass --reset-users unless you are reinstalling)"
fi

# --- 3. the manual step we refuse to automate --------------------------------
echo
echo "Done. ISPConfig core was not modified."
if grep -Eq "conf\[.theme.\] *= *.clarity." "$CONF" 2>/dev/null || grep -Eq "conf\[.theme.\] *= *.clarity." "$SERVER_CONF" 2>/dev/null; then
  cat <<EOWARN

ACTION REQUIRED: \$conf['theme'] is still set to 'clarity'. Revert it to
'default' in BOTH files (this script never edits ISPConfig config):
  $CONF
  $SERVER_CONF
Until then the login screen will fall back with an error.
EOWARN
fi
