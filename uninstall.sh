#!/usr/bin/env bash
#
# ispconfig-theme-customizer — uninstaller.
#
# Cleanly reverses install.sh. With no flag it removes everything install.sh put
# in place. Touches NOTHING in ISPConfig core.
#
#   --theme   removes the design directories and (with --reset-users) flips
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
# $conf['theme'] = '<design>' at install time, revert it yourself in BOTH files
# (this script checks and tells you BEFORE it removes anything).
#
# Usage:
#   ./uninstall.sh [--theme|--module|--all] [--design=<name>] [--reset-users]
#                  [--purge-branding] [--keep-assignment] [ISPCONFIG_ROOT]
#
#     --theme            remove only the design(s)
#     --module           remove only the Branding page
#     --all              remove both — the default when no flag is given
#     --design=<n>       which design: clarity, classic, or all. Repeatable.
#                        DEFAULTS TO ALL, which is the opposite of install.sh's
#                        default and deliberate: installing picks what you want,
#                        uninstalling has to clear anything that might be there.
#                        Removing a design that was never installed just prints
#                        "already removed", so the wide default costs nothing —
#                        whereas a narrow one would leave a stray design in the
#                        panel's picker after "uninstall everything".
#     --reset-users      with --theme: reset sys_user.app_theme rows pointing at
#                        the removed design(s) -> 'default' (recommended; skip
#                        only if reinstalling)
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

# Ignoring the signal is only half of it: with the reader gone, every subsequent
# write still fails with EPIPE and `set -e` treats that failing echo as fatal, so
# informational output goes through say(), which tolerates a closed stdout.
# Errors keep `echo ... >&2` — a different descriptor, normally still attached.
say() { echo "$@" 2>/dev/null || true; }

# Print ONLY the usage header — the comment block between the shebang and
# `set -euo pipefail` — not every top-level comment in the file. The old
# implementation grepped '^#' across the whole script, so --help emitted ~150
# lines: the SIGPIPE rationale, "--- helpers ---" section markers, and every
# other note written for someone reading the source, buried the four lines an
# operator actually wanted.
usage() { sed -n '2,/^set -euo pipefail/p' "$0" | grep '^#' | sed 's/^# \{0,1\}//'; }


ISPC_ROOT="/usr/local/ispconfig"
RESET_USERS=0
PURGE=0
UNASSIGN=1
WANT_THEME=""
WANT_MODULE=""
DESIGNS=""
DESIGN_EXPLICIT=0
ALL_DESIGNS="clarity classic"

want_design() {
  local d
  for d in $DESIGNS; do [ "$d" = "$1" ] && return 0; done
  DESIGNS="${DESIGNS:+$DESIGNS }$1"
}

for arg in "$@"; do
  case "$arg" in
    --theme)           WANT_THEME=1 ;;
    --module)          WANT_MODULE=1 ;;
    --all)             WANT_THEME=1; WANT_MODULE=1 ;;
    --design=*)
      DESIGN_EXPLICIT=1
      case "${arg#--design=}" in
        all)     for d in $ALL_DESIGNS; do want_design "$d"; done ;;
        clarity) want_design clarity ;;
        classic) want_design classic ;;
        # A typo must not narrow the uninstall to nothing and still report success.
        *) echo "ERROR: unknown design: ${arg#--design=} (known: $ALL_DESIGNS, all)" >&2; exit 2 ;;
      esac
      ;;
    --design)          echo "ERROR: --design needs a value, e.g. --design=classic" >&2; exit 2 ;;
    --reset-users)     RESET_USERS=1 ;;
    --purge-branding)  PURGE=1 ;;
    --keep-assignment) UNASSIGN=0 ;;
    # grep -v '^#!' so the shebang does not print as a stray "!/usr/bin/env bash"
    -h|--help)         usage; exit 0 ;;
    -*)                echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *)                 ISPC_ROOT="$arg" ;;
  esac
done

if [ -z "$WANT_THEME" ] && [ -z "$WANT_MODULE" ]; then WANT_THEME=1; WANT_MODULE=1; fi
if [ -z "$DESIGNS" ]; then DESIGNS="$ALL_DESIGNS"; fi

# Same trap as install.sh: --design alongside --module alone removes no design,
# and a silent success there is worse here — the operator believes a design is
# gone from the picker when it is still installed.
if [ "$DESIGN_EXPLICIT" -eq 1 ] && [ -z "$WANT_THEME" ]; then
  echo "NOTE: --design was given but no design is removed with --module alone." >&2
  echo "      Use --all (or drop --module) to remove a design as well." >&2
fi

ROOT="$(cd "$(dirname "$0")" && pwd)"
THEMES_DIR="$ISPC_ROOT/interface/web/themes"
MOD_DEST="$ISPC_ROOT/interface/web/customizer"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"
SERVER_CONF="$ISPC_ROOT/server/lib/config.inc.php"
PHP_BIN="$(command -v php || true)"

say "ispconfig-theme-customizer uninstaller"
say "  target     : $ISPC_ROOT"
say "  removing   : ${WANT_THEME:+theme }${WANT_MODULE:+module}"
if [ -n "$WANT_THEME" ]; then say "  design(s)  : $DESIGNS"; fi
say

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
# Checking first matters: once a design directory is gone, a panel still
# configured for it serves a login page with every stylesheet and script
# resolving into the removed directory. The operator needs to know that before,
# not after.
CONFIGURED_DESIGN=""
if [ -n "$WANT_THEME" ]; then
  for d in $DESIGNS; do
    if grep -Eq "conf\[.theme.\] *= *.$d." "$CONF" 2>/dev/null \
    || grep -Eq "conf\[.theme.\] *= *.$d." "$SERVER_CONF" 2>/dev/null; then
      CONFIGURED_DESIGN="$d"
    fi
  done
  if [ -n "$CONFIGURED_DESIGN" ]; then
    cat >&2 <<EOWARN
ACTION REQUIRED — read before continuing.

\$conf['theme'] is still set to '$CONFIGURED_DESIGN' in your ISPConfig config.
This script never edits ISPConfig configuration, so you must revert it to
'default' in BOTH files:
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
    say "removing 'customizer' from user accounts:"
    "$PHP_BIN" "$ROOT/bin/unassign_module.php" "$CONF" || {
      echo "WARNING: unassign failed — 'customizer' may remain in users' module lists." >&2
      echo "         Remove it by hand in System > CP Users, or re-run once the DB is reachable." >&2
    }
  fi
  if [ -n "$WANT_MODULE" ] && [ "$PURGE" = 1 ]; then
    say "purging stored branding:"
    "$PHP_BIN" "$ROOT/bin/purge_branding.php" "$CONF" || {
      echo "WARNING: purge failed — stored branding values are still in sys_ini." >&2
    }
  fi
  if [ -n "$WANT_THEME" ] && [ "$RESET_USERS" = 1 ]; then
    say "resetting users' theme choice:"
    # The design names are passed explicitly: this must reset exactly the designs
    # being removed, and nobody who uninstalls one design expects users of the
    # other to be flipped back to stock.
    "$PHP_BIN" "$ROOT/bin/reset_app_theme.php" "$CONF" $DESIGNS || {
      echo "WARNING: app_theme reset failed — users still set to one of '$DESIGNS' will" >&2
      echo "         see a 'theme not compatible' error at EVERY login. Fix the database" >&2
      echo "         and re-run: php bin/reset_app_theme.php $CONF $DESIGNS" >&2
    }
  elif [ -n "$WANT_THEME" ]; then
    say "  (skipped app_theme reset — pass --reset-users unless you are reinstalling)"
  fi
fi

# --- 2. remove the deployed directories -------------------------------------
# A symlinked install wrote its install-time artefacts THROUGH the link, i.e.
# into the source clone: the version stamps for every design, and additionally
# classic's GENERATED templates. Removing only the link would leave them behind,
# and a later install against a DIFFERENT panel would inherit them — a wrong
# version stamp makes the theme look selectable and then resets every user at
# login, and a stale generated shell is a copy of another ISPConfig's markup.
# Clear them via the link first.
#
# $1 = destination, $2 = label, $3.. = paths inside the theme dir to clear from
# the source clone. Pass ONLY generated paths: `templates` is generated for
# classic but is committed source for clarity, so it must never be listed for
# a design that ships it.
remove_dest() {
  local dest="$1" label="$2"
  shift 2
  if [ ! -e "$dest" ] && [ ! -L "$dest" ]; then
    say "  $label not present at $dest (already removed)"
    return 0
  fi
  if [ "$#" -gt 0 ] && [ -L "$dest" ]; then
    local target rel
    target="$(readlink -f "$dest" 2>/dev/null || true)"
    if [ -n "$target" ] && [ -d "$target" ]; then
      for rel in "$@"; do rm -rf "${target:?}/$rel"; done
      say "  cleared install-time artefacts ($*) from the source clone ($target)"
    fi
  fi
  rm -rf "$dest"
  say "removed $dest"
}

[ -n "$WANT_MODULE" ] && remove_dest "$MOD_DEST" "Branding page directory"
if [ -n "$WANT_THEME" ]; then
  for d in $DESIGNS; do
    case "$d" in
      classic) remove_dest "$THEMES_DIR/$d" "design directory ($d)" ispconfig_version ISPC_VERSION templates ;;
      *)       remove_dest "$THEMES_DIR/$d" "design directory ($d)" ispconfig_version ISPC_VERSION ;;
    esac
  done
fi

# --- 3. closing notes --------------------------------------------------------
say
say "Done. ISPConfig core was not modified."

if [ -n "$WANT_MODULE" ] && [ "$PURGE" = 0 ]; then
  say
  say "Branding values were preserved (reinstall-friendly). To remove them later,"
  say "either reinstall and use the Branding page, edit System > Interface Config"
  say "(panel name and login text), or run:"
  say "  php bin/purge_branding.php"
fi

if [ -n "$CONFIGURED_DESIGN" ]; then
  cat >&2 <<EOWARN

STILL OUTSTANDING: \$conf['theme'] = '$CONFIGURED_DESIGN' remains in your config
and that design directory is now gone. Set it back to 'default' in both files NOW:
  $CONF
  $SERVER_CONF
The login screen is unstyled until you do.
EOWARN
fi
