#!/usr/bin/env bash
#
# Clarity Theme for ISPConfig (clarity) — ISPConfig theme deploy helper.
# Installs the theme by symlinking (default) or copying themes/clarity
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
    -*) echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *) ISPC_ROOT="$arg" ;;
  esac
done

ROOT="$(cd "$(dirname "$0")" && pwd)"
THEMES_DIR="$ISPC_ROOT/interface/web/themes"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"

# clarity = "Clarity Theme for ISPConfig" — fully self-contained (own templates incl.
# topnav.tpl.htm + own CSS/fonts/images); it needs only the stock 'default'
# theme for vendor CSS/JS.
THEMES="clarity"

echo "Clarity theme installer"
echo "  source : $ROOT/themes"
echo "  themes : $THEMES"
echo "  target : $THEMES_DIR"
echo "  mode   : $MODE"
echo

[ -d "$ROOT/themes/clarity" ] || { echo "ERROR: source theme not found at $ROOT/themes/clarity" >&2; exit 1; }
if [ ! -d "$THEMES_DIR" ]; then
  echo "ERROR: $THEMES_DIR not found — is ISPCONFIG_ROOT correct?" >&2
  echo "       pass it explicitly, e.g.  ./install.sh /usr/local/ispconfig" >&2
  exit 1
fi
[ -d "$THEMES_DIR/default" ] || { echo "ERROR: $THEMES_DIR/default missing — Clarity Theme for ISPConfig inherits vendor assets from it." >&2; exit 1; }

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

if [ "$MODE" = "copy" ] && id -u ispconfig >/dev/null 2>&1; then
  # copy mode only: chown -R on a symlink argument does not follow it, and the
  # clone's own ownership is not ours to change
  for T in $THEMES; do chown -R ispconfig:ispconfig "$THEMES_DIR/$T" 2>/dev/null || true; done
fi

if [ "$MODE" = "symlink" ]; then
  # The web server reads the theme THROUGH the symlink, so every ancestor of
  # this clone must be traversable by it. A clone under /root (mode 700) serves
  # nothing — and fails with no error here.
  p="$ROOT"
  while [ "$p" != "/" ]; do
    o="$(stat -c '%a' "$p" 2>/dev/null || echo 7)"; o="${o: -1}"
    if [ $(( o % 2 )) -eq 0 ]; then
      echo "WARNING: $p is not world-traversable (others have no 'x' bit)." >&2
      echo "         The panel's web server likely cannot read the symlinked theme" >&2
      echo "         from here (classic case: a clone under /root)." >&2
      echo "         Move the clone somewhere readable (e.g. /opt/clarity-theme-ispconfig)" >&2
      echo "         and re-run, or install with --copy instead." >&2
      break
    fi
    p="$(dirname "$p")"
  done
fi

cat <<'EOF'

Done. Next steps:

  1. Per user:    Tools > User Settings > Design > select "clarity" > Save,
                  then LOG OUT AND BACK IN (the theme is applied at login).
  2. System wide + login screen — set in BOTH config files:

       $conf['theme'] = 'clarity';

     interface/lib/config.inc.php   takes effect immediately (login page +
                                    default for new users)
     server/lib/config.inc.php      makes it survive ISPConfig updates — the
                                    updater regenerates both configs and carries
                                    the theme forward from the SERVER config

  3. Hard-refresh the browser (Ctrl+Shift+R) so the new CSS is picked up.

After a MAJOR ISPConfig upgrade, re-run this script so ispconfig_version is
re-stamped to the new ISPC_APP_VERSION (otherwise the theme silently reverts to
default at login), and diff the two shell templates against the new stock ones.

No ISPConfig core file was modified.
EOF
