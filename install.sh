#!/usr/bin/env bash
#
# ispconfig-theme-customizer — installer for the theme and the branding module.
#
# Installs either or both components into an ISPConfig install:
#   theme   themes/clarity            -> <ispconfig>/interface/web/themes/clarity
#   module  interface/web/customizer  -> <ispconfig>/interface/web/customizer
#
# Touches NOTHING in ISPConfig core and adds no database schema. The module
# writes only to the existing sys_ini row; the theme writes nothing at runtime.
#
# The two are independent: the module works on the stock ISPConfig theme (logo,
# panel name and the per-role news feeds are read by core itself), while the
# accent colour, login background and version-hiding need a brand-aware theme
# such as Clarity. Install whichever you want.
#
# Usage:
#   ./install.sh [--theme|--module|--all] [--copy] [--no-assign] [ISPCONFIG_ROOT]
#
#     --theme       install only the theme
#     --module      install only the branding module
#     --all         install both (default)
#     --copy        copy instead of symlinking (use for packaged installs)
#     --no-assign   do not assign the module to admin users; do it by hand in
#                   System > CP Users > edit the admin user > Modules
#     ISPCONFIG_ROOT defaults to /usr/local/ispconfig
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

MODE="symlink"
ISPC_ROOT="/usr/local/ispconfig"
ASSIGN=1
WANT_THEME=""
WANT_MODULE=""

for arg in "$@"; do
  case "$arg" in
    --theme)     WANT_THEME=1 ;;
    --module)    WANT_MODULE=1 ;;
    --all)       WANT_THEME=1; WANT_MODULE=1 ;;
    --copy)      MODE="copy" ;;
    --no-assign) ASSIGN=0 ;;
    # grep -v '^#!' so the shebang does not print as a stray "!/usr/bin/env bash"
    -h|--help)   grep '^#' "$0" | grep -v '^#!' | sed 's/^# \{0,1\}//'; exit 0 ;;
    -*)          echo "ERROR: unknown option: $arg" >&2; exit 2 ;;
    *)           ISPC_ROOT="$arg" ;;
  esac
done

# neither named -> both, so a bare ./install.sh keeps doing the obvious thing
if [ -z "$WANT_THEME" ] && [ -z "$WANT_MODULE" ]; then WANT_THEME=1; WANT_MODULE=1; fi

ROOT="$(cd "$(dirname "$0")" && pwd)"
WEB_DIR="$ISPC_ROOT/interface/web"
THEMES_DIR="$WEB_DIR/themes"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"
THEME_NAME="clarity"

echo "ispconfig-theme-customizer installer"
echo "  source     : $ROOT"
echo "  target     : $ISPC_ROOT"
echo "  components : ${WANT_THEME:+theme }${WANT_MODULE:+module}"
echo "  mode       : $MODE"
echo

if [ ! -d "$WEB_DIR" ]; then
  echo "ERROR: $WEB_DIR not found — is ISPCONFIG_ROOT correct?" >&2
  echo "       pass it explicitly, e.g.  ./install.sh /usr/local/ispconfig" >&2
  exit 1
fi

# --- helpers ----------------------------------------------------------------

# A symlinked component is SERVED through the link, so editor/agent/vcs state
# inside the source tree would be reachable over HTTP (e.g. /customizer/.omc/...).
check_stray() {
  local src="$1"
  local stray
  stray="$(find "$src" \( -name '.omc' -o -name '.git' -o -name 'node_modules' \) -print -quit 2>/dev/null || true)"
  [ -n "$stray" ] || return 0
  if [ "$MODE" = "symlink" ]; then
    echo "ERROR: $src contains $stray" >&2
    echo "       A symlinked component SERVES it. Remove it first:" >&2
    echo "         find '$src' \\( -name .omc -o -name .git \\) -prune -exec rm -rf {} +" >&2
    echo "       ...or install with --copy, which excludes them." >&2
    exit 1
  fi
  echo "NOTE: excluding $stray (and any other .omc/.git/node_modules) from the copy."
}

# The web server reads a symlinked component THROUGH the link, so every ancestor
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
    echo "  removing existing $dest"
    rm -rf "$dest"
  fi
  if [ "$MODE" = "symlink" ]; then
    ln -s "$src" "$dest"
    echo "  symlinked $name into place."
  else
    # tar, not `cp -a`, so the excludes are actually honoured
    tar cf - --exclude='.omc' --exclude='.git' --exclude='node_modules' \
        -C "$parent" "$name" | tar xf - -C "$(dirname "$dest")"
    echo "  copied $name into place."
    if id -u ispconfig >/dev/null 2>&1; then
      chown -R ispconfig:ispconfig "$dest" 2>/dev/null || true
    fi
  fi
}

# --- warn about a previous split installation -------------------------------
# Before the merge these shipped as two repos (clarity-theme-ispconfig and
# ispconfig-customizer), typically cloned to /root/clarity-theme and
# /root/ispconfig-customizer. Installing from here replaces what they deployed,
# but their clones linger and re-running one of THEIR installers afterwards
# would silently overwrite this install with older code.
for old in /root/clarity-theme /root/ispconfig-customizer /opt/clarity-theme-ispconfig /opt/ispconfig-customizer; do
  if [ -d "$old" ] && [ "$old" != "$ROOT" ]; then
    echo "NOTE: found a clone of the previous split layout at $old"
    echo "      Both projects now live in this one repository. That clone is stale —"
    echo "      remove it once this install is verified, so nobody re-runs its installer."
    echo
  fi
done

# --- theme ------------------------------------------------------------------
if [ -n "$WANT_THEME" ]; then
  SRC="$ROOT/themes/$THEME_NAME"
  DEST="$THEMES_DIR/$THEME_NAME"

  echo "Theme ($THEME_NAME):"
  [ -d "$SRC" ] || { echo "ERROR: source theme not found at $SRC" >&2; exit 1; }
  [ -d "$THEMES_DIR" ] || { echo "ERROR: $THEMES_DIR not found — is ISPCONFIG_ROOT correct?" >&2; exit 1; }
  [ -d "$THEMES_DIR/default" ] || { echo "ERROR: $THEMES_DIR/default missing — Clarity inherits vendor assets from it." >&2; exit 1; }

  check_stray "$SRC"
  deploy "$SRC" "$DEST" "$ROOT/themes" "$THEME_NAME"

  # --- detect ISPC_APP_VERSION and stamp the gate --------------------------
  # `|| true`: an unmatched grep must fall through to the WARNING branch, not
  # abort via set -e/pipefail after the theme is already in place.
  VERSION=""
  if [ -f "$CONF" ]; then
    VERSION="$(grep -oE "define\(['\"]ISPC_APP_VERSION['\"],[[:space:]]*['\"][^'\"]+['\"]" "$CONF" \
               | grep -oE "['\"][^'\"]+['\"][[:space:]]*\)?$" | tail -1 | tr -d "'\"" || true)"
  fi

  if [ -n "$VERSION" ]; then
    printf '%s' "$VERSION" > "$DEST/ispconfig_version"
    printf '%s' "$VERSION" > "$DEST/ISPC_VERSION"
    echo "  stamped ispconfig_version + ISPC_VERSION = '$VERSION'"
  else
    # Leave NO stale stamp behind: a version file from a previous install against
    # a different panel is worse than none — it makes the theme look selectable
    # and then silently resets every user at login.
    rm -f "$DEST/ispconfig_version" "$DEST/ISPC_VERSION"
    echo "WARNING: could not detect ISPC_APP_VERSION from $CONF." >&2
    echo "         Removed any stale stamps. Create BOTH files with your exact version:" >&2
    echo "           V=\$(php -r \"require '$CONF'; echo ISPC_APP_VERSION;\")" >&2
    echo "           printf '%s' \"\$V\" > $DEST/ispconfig_version" >&2
    echo "           printf '%s' \"\$V\" > $DEST/ISPC_VERSION" >&2
    echo "         Without ispconfig_version, ISPConfig resets the theme to 'default' at login." >&2
  fi

  # --- version-disclosure advisory -----------------------------------------
  # Those two files sit inside a directory the panel serves statically, so
  # `curl https://panel:8080/themes/clarity/ispconfig_version` returns the exact
  # ISPConfig version with no session. Core cannot be asked to rename them (it
  # reads those exact names), so the fix belongs in the web server. This matters
  # more here than for a stock theme: the branding module offers a "hide the
  # version" toggle, and serving it as a static file undercuts that entirely.
  echo
  echo "  SECURITY NOTE: $DEST/ispconfig_version and ISPC_VERSION are served over"
  echo "  HTTP and disclose your exact ISPConfig version to anyone who can reach the"
  echo "  login page. Deny them at the web-server layer."
  if [ -d "$ROOT/contrib/webserver" ]; then
    echo "  Ready-made Apache and nginx snippets: contrib/webserver/ (see its README)."
  fi
  echo
fi

# --- module -----------------------------------------------------------------
if [ -n "$WANT_MODULE" ]; then
  SRC="$ROOT/interface/web/customizer"
  DEST="$WEB_DIR/customizer"

  echo "Branding module:"
  [ -d "$SRC" ] || { echo "ERROR: source not found at $SRC" >&2; exit 1; }

  check_stray "$SRC"
  deploy "$SRC" "$DEST" "$ROOT/interface/web" "customizer"

  if [ "$ASSIGN" -eq 1 ]; then
    if command -v php >/dev/null 2>&1 && [ -f "$CONF" ]; then
      echo "  assigning the module to admin users:"
      php "$ROOT/bin/assign_module.php" "$CONF" || {
        echo "WARNING: automatic assignment failed — add 'customizer' by hand in" >&2
        echo "         System > CP Users > edit the admin user > Modules." >&2
      }
    else
      echo "  NOTE: could not auto-assign (php CLI or $CONF missing)."
      echo "        Add it by hand: System > CP Users > edit the admin user >"
      echo "        Modules > tick 'customizer' > Save."
    fi
  fi
  echo
fi

check_traversable

# --- closing instructions ---------------------------------------------------
{
  echo "Done."
  echo
  if [ -n "$WANT_THEME" ]; then
    echo "Theme — next steps:"
    echo "  1. Per user:  Tools > User Settings > Design > select \"$THEME_NAME\" > Save,"
    echo "                Core updates your session and reloads the page, so it"
    echo "                applies immediately. If the frame still looks stock,"
    echo "                hard-refresh (Ctrl+Shift+R) to drop the cached CSS."
    echo "  2. System wide + login screen — set in BOTH config files:"
    echo
    echo "       \$conf['theme'] = '$THEME_NAME';"
    echo
    echo "     interface/lib/config.inc.php   takes effect immediately (login page +"
    echo "                                    default for new users)"
    echo "     server/lib/config.inc.php      makes it survive ISPConfig updates — the"
    echo "                                    updater regenerates both configs and carries"
    echo "                                    the theme forward from the SERVER config"
    echo
    echo "  3. Hard-refresh the browser (Ctrl+Shift+R) so the new CSS is picked up."
    echo
  fi
  if [ -n "$WANT_MODULE" ]; then
    echo "Module — next steps:"
    echo "  1. Re-log in (or reload the panel) so \"Branding\" appears in the top navigation."
    echo "  2. Open Branding and set your logo, panel name, colours and login details."
    echo "  3. Logo, panel name and the news-feed toggle also apply to the STOCK theme —"
    echo "     core reads those itself. Accent colour, login background and version"
    echo "     hiding need a brand-aware theme such as Clarity."
    echo
  fi
  if [ -n "$WANT_THEME" ]; then
    echo "IMPORTANT — after ANY ISPConfig upgrade, including patch releases such as"
    echo "3.3.1p1 -> 3.3.1p2, re-run this script so the version gate is re-stamped."
    echo "Skip it and the theme silently reverts to 'default' at every login."
    echo "Then diff the six overridden templates (three shell + three dashboard"
    echo "dashlets) against the new stock ones — they are listed with their pinned"
    echo "contracts in themes/$THEME_NAME/BUILT-AGAINST.txt."
    echo
  fi
  echo "No ISPConfig core file was modified."
}
