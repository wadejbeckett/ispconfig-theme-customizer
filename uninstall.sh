#!/usr/bin/env bash
#
# ispconfig-theme-customizer — uninstaller.
#
# Cleanly reverses install.sh. With no flag it removes everything install.sh put
# in place. Touches NOTHING in ISPConfig core.
#
#   --theme   removes themes/clarity and (with --reset-users) flips
#             sys_user.app_theme rows back to 'default'. Core does NOT heal that
#             column on its own — without the reset, affected users get a
#             "theme not compatible" banner at every login.
#   --module  removes interface/web/customizer (the Branding page), strips
#             'customizer' from every user's module list (resetting any
#             startmodule that pointed at it), and — only when explicitly
#             asked — wipes the stored branding.
#
# Removing files is NOT the same as restoring stock. By default your branding
# SURVIVES uninstall (reinstall-friendly): the panel name / login text / logo
# are stock ISPConfig fields that keep working and stay editable under System >
# Interface Config, and the [branding] values sit inert in sys_ini for any
# brand-aware theme. Use --reset-users and --purge-branding to go further.
#
# The one thing this script will NEVER do is edit config.inc.php. If you set
# $conf['theme'] = 'clarity' at install time, revert it yourself in BOTH files
# (this script checks and tells you BEFORE it removes anything).
#
# Usage:
#   ./uninstall.sh [--theme|--module|--all] [--reset-users] [--purge-branding]
#                  [--keep-assignment] [ISPCONFIG_ROOT]
#
#     --theme            remove only the design (themes/clarity)
#     --module           remove only the Branding page
#     --all              remove both — the default when no flag is given
#     --reset-users      with --theme: reset sys_user.app_theme 'clarity' ->
#                        'default' (recommended; skip only if reinstalling)
#     --purge-branding   with --module: also wipe ALL stored branding values
#     --keep-assignment  with --module: leave 'customizer' in users' module lists
#     ISPCONFIG_ROOT     defaults to /usr/local/ispconfig
#
set -euo pipefail

# Ignore SIGPIPE — see the same guard in install.sh for the full reasoning.
# Short version: this script does database work and then removes directories,
# and a closed stdout (`| head`, `| less` then q, a `tee` that dies) used to
# kill it under `set -e` somewhere in between, leaving a half-uninstalled panel.
# Output being truncated must never decide how far the work gets.
trap '' PIPE

ISPC_ROOT="/usr/local/ispconfig"
RESET_USERS=0
PURGE=0
UNASSIGN=1
WANT_THEME=""
WANT_MODULE=""

for arg in "$@"; do
  case "$arg" in
    --theme)           WANT_THEME=1 ;;
    --module)          WANT_MODULE=1 ;;
    --all)             WANT_THEME=1; WANT_MODULE=1 ;;
    --reset-users)     RESET_USERS=1 ;;
    --purge-branding)  PURGE=1 ;;
    --keep-assignment) UNASSIGN=0 ;;
    # grep -v '^#!' so the shebang does not print as a stray "!/usr/bin/env bash"
    -h|--help)         grep '^#' "$0" | grep -v '^#!' | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*)                echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *)                 ISPC_ROOT="$arg" ;;
  esac
done

if [ -z "$WANT_THEME" ] && [ -z "$WANT_MODULE" ]; then WANT_THEME=1; WANT_MODULE=1; fi

ROOT="$(cd "$(dirname "$0")" && pwd)"
THEME_NAME="clarity"
THEME_DEST="$ISPC_ROOT/interface/web/themes/$THEME_NAME"
MOD_DEST="$ISPC_ROOT/interface/web/customizer"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"
SERVER_CONF="$ISPC_ROOT/server/lib/config.inc.php"
PHP_BIN="$(command -v php || true)"

echo "ispconfig-theme-customizer uninstaller"
echo "  target     : $ISPC_ROOT"
echo "  removing   : ${WANT_THEME:+theme }${WANT_MODULE:+module}"
echo

# Refuse a wrong ISPCONFIG_ROOT rather than reporting a cheerful success. Without
# this, a typo'd path finds nothing to remove, prints "Done. ISPConfig core was
# not modified." and exits 0 — leaving the operator certain they uninstalled when
# the real install is untouched.
if [ ! -d "$ISPC_ROOT/interface/web" ]; then
  echo "ERROR: $ISPC_ROOT/interface/web not found — is ISPCONFIG_ROOT correct?" >&2
  echo "       pass it explicitly, e.g.  ./uninstall.sh /usr/local/ispconfig" >&2
  exit 1
fi

# --- 0. warn BEFORE destroying anything -------------------------------------
# Checking first matters: once themes/clarity is gone, a panel still configured
# for it serves a login page with every stylesheet and script resolving into the
# removed directory. The operator needs to know that before, not after.
THEME_STILL_CONFIGURED=0
if [ -n "$WANT_THEME" ]; then
  if grep -Eq "conf\[.theme.\] *= *.$THEME_NAME." "$CONF" 2>/dev/null \
  || grep -Eq "conf\[.theme.\] *= *.$THEME_NAME." "$SERVER_CONF" 2>/dev/null; then
    THEME_STILL_CONFIGURED=1
    cat >&2 <<EOWARN
ACTION REQUIRED — read before continuing.

\$conf['theme'] is still set to '$THEME_NAME' in your ISPConfig config. This
script never edits ISPConfig configuration, so you must revert it to 'default'
in BOTH files:
  $CONF
  $SERVER_CONF

Until you do, the login screen renders COMPLETELY UNSTYLED — ISPConfig falls
back to the stock login template, but that template loads its stylesheets and
scripts from the theme directory this script is about to remove, so every one
of them 404s. No error is displayed. Login itself still works, so the panel is
usable but looks broken to anyone who sees it.

EOWARN
  fi
fi

# --- 1. database cleanup FIRST ----------------------------------------------
# Order matters. The DB steps must run while the files are still in place: if a
# directory were removed first and the DB step then failed (MySQL down, stale
# credentials in config.inc.php), `set -e` would abort with the files gone and
# the database still pointing at them — the worst of both states. Each DB step
# therefore also WARNS rather than aborting, so a database problem can never
# leave a half-finished uninstall.
if [ -z "$PHP_BIN" ]; then
  echo "WARNING: php CLI not found — skipping all database cleanup." >&2
  echo "         Run the scripts in bin/ manually once php is available." >&2
else
  if [ -n "$WANT_MODULE" ] && [ "$UNASSIGN" = 1 ]; then
    echo "removing 'customizer' from user accounts:"
    "$PHP_BIN" "$ROOT/bin/unassign_module.php" "$CONF" || {
      echo "WARNING: unassign failed — 'customizer' may remain in users' module lists." >&2
      echo "         Remove it by hand in System > CP Users, or re-run once the DB is reachable." >&2
    }
  fi
  if [ -n "$WANT_MODULE" ] && [ "$PURGE" = 1 ]; then
    echo "purging stored branding:"
    "$PHP_BIN" "$ROOT/bin/purge_branding.php" "$CONF" || {
      echo "WARNING: purge failed — stored branding values are still in sys_ini." >&2
    }
  fi
  if [ -n "$WANT_THEME" ] && [ "$RESET_USERS" = 1 ]; then
    echo "resetting users' theme choice:"
    "$PHP_BIN" "$ROOT/bin/reset_app_theme.php" "$CONF" || {
      echo "WARNING: app_theme reset failed — users still set to '$THEME_NAME' will see a" >&2
      echo "         'theme not compatible' error at EVERY login. Fix the database and" >&2
      echo "         re-run: php bin/reset_app_theme.php $CONF" >&2
    }
  elif [ -n "$WANT_THEME" ]; then
    echo "  (skipped app_theme reset — pass --reset-users unless you are reinstalling)"
  fi
fi

# --- 2. remove the deployed directories -------------------------------------
# A symlinked install stamped ispconfig_version / ISPC_VERSION THROUGH the link,
# i.e. into the source clone. Removing only the link would leave those stamps
# behind, and a later install against a DIFFERENT panel whose version could not
# be detected would then silently inherit a wrong version — the theme looks
# selectable and resets every user at login. Clear them via the link first.
remove_dest() {
  local dest="$1" label="$2" strip_stamps="${3:-0}"
  if [ ! -e "$dest" ] && [ ! -L "$dest" ]; then
    echo "  $label not present at $dest (already removed)"
    return 0
  fi
  if [ "$strip_stamps" = "1" ] && [ -L "$dest" ]; then
    local target
    target="$(readlink -f "$dest" 2>/dev/null || true)"
    if [ -n "$target" ] && [ -d "$target" ]; then
      rm -f "$target/ispconfig_version" "$target/ISPC_VERSION"
      echo "  cleared version stamps from the source clone ($target)"
    fi
  fi
  rm -rf "$dest"
  echo "removed $dest"
}

[ -n "$WANT_MODULE" ] && remove_dest "$MOD_DEST" "Branding page directory"
[ -n "$WANT_THEME" ]  && remove_dest "$THEME_DEST" "theme directory" 1

# --- 3. closing notes --------------------------------------------------------
echo
echo "Done. ISPConfig core was not modified."

if [ -n "$WANT_MODULE" ] && [ "$PURGE" = 0 ]; then
  cat <<'EONOTE'

Branding values were preserved (reinstall-friendly). To remove them later,
either reinstall and use the Branding page, edit System > Interface Config
(panel name and login text), or run:
  php bin/purge_branding.php
EONOTE
fi

if [ "$THEME_STILL_CONFIGURED" = 1 ]; then
  cat >&2 <<EOWARN

STILL OUTSTANDING: \$conf['theme'] = '$THEME_NAME' remains in your config and the
theme directory is now gone. Set it back to 'default' in both files NOW:
  $CONF
  $SERVER_CONF
The login screen is unstyled until you do.
EOWARN
fi
