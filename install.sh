#!/usr/bin/env bash
#
# ispconfig-theme-customizer — installer.
#
# A modern, brandable front-end for ISPConfig. It replaces the panel's
# front-end with a dark (and light) design, and adds an admin page — nav label
# "Branding" — where you set your logo, panel name and colours.
#
# It deploys into two directories:
#   themes/clarity            -> <ispconfig>/interface/web/themes/clarity
#   interface/web/customizer  -> <ispconfig>/interface/web/customizer
#
# Touches NOTHING in ISPConfig core and adds no database schema. The Branding
# page writes only to the existing sys_ini row; the design writes nothing at
# runtime — it READS exactly the keys that page writes. That shared contract is
# why this is one project with one version number: before v3.0.0 the halves
# lived in separate repositories with independent versions, and nothing
# enforced that they matched, so an accent colour could silently fail to apply.
#
# You can still install one half on its own. With --module you get just the
# Branding page: logo, panel name and the per-role news feeds are read by
# ISPConfig core itself, so those brand the stock theme too. Accent colour,
# login background and version-hiding need a brand-aware design, which is what
# --theme installs. Clarity is the design this SHIPS WITH, not the product's
# identity — anything that reads the same sys_ini keys inherits the Branding
# page, and CI enforces that contract.
#
# Usage:
#   ./install.sh [--theme|--module|--all] [--copy] [--no-assign] [ISPCONFIG_ROOT]
#
#     --theme       install only the design (themes/clarity)
#     --module      install only the Branding page
#     --all         install both — the default when no flag is given
#     --copy        copy instead of symlinking (use for packaged installs)
#     --no-assign   do not grant admin users access to the Branding page; do it
#                   by hand in System > CP Users > edit the admin user > Modules
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
INSTALL_STARTED=0      # set to 1 immediately before the first destructive action
INSTALL_COMPLETED=0    # set to 1 once every requested component is fully in place
on_exit() {
    local rc=$?
    # Nothing to warn about for --help, a bad flag, or a failed precondition:
    # those exit before anything on the panel has been touched.
    [ "$INSTALL_STARTED" -eq 1 ] || return 0
    [ "$INSTALL_COMPLETED" -eq 0 ] || return 0
    echo "" >&2
    echo "*** INSTALL DID NOT COMPLETE (exit $rc) ***" >&2
    if [ -n "${WANT_THEME:-}" ] && [ -d "${THEMES_DIR:-}/${THEME_NAME:-clarity}" ] \
       && [ ! -f "${THEMES_DIR:-}/${THEME_NAME:-clarity}/ispconfig_version" ]; then
        echo "The theme directory is in place but NOT version-stamped. ISPConfig will" >&2
        echo "reset every user on it to the default theme at their next login, without" >&2
        echo "showing an error. Re-run this script to finish — it is safe to repeat:" >&2
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

# with no component flag, both are installed, so a bare ./install.sh keeps doing
# the obvious thing
if [ -z "$WANT_THEME" ] && [ -z "$WANT_MODULE" ]; then WANT_THEME=1; WANT_MODULE=1; fi

ROOT="$(cd "$(dirname "$0")" && pwd)"
WEB_DIR="$ISPC_ROOT/interface/web"
THEMES_DIR="$WEB_DIR/themes"
CONF="$ISPC_ROOT/interface/lib/config.inc.php"
THEME_NAME="clarity"

say "ispconfig-theme-customizer installer"
say "  source     : $ROOT"
say "  target     : $ISPC_ROOT"
say "  installing : ${WANT_THEME:+theme }${WANT_MODULE:+module}"
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
  SRC="$ROOT/themes/$THEME_NAME"
  DEST="$THEMES_DIR/$THEME_NAME"

  say "Design ($THEME_NAME):"
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
  # more here than for a stock theme: the Branding page offers a "hide the
  # version" toggle, and serving it as a static file undercuts that entirely.
  say
  say "  SECURITY NOTE: $DEST/ispconfig_version and ISPC_VERSION are served over"
  say "  HTTP and disclose your exact ISPConfig version to anyone who can reach the"
  say "  login page. Deny them at the web-server layer."
  if [ -d "$ROOT/contrib/webserver" ]; then
    say "  Ready-made Apache and nginx snippets: contrib/webserver/ (see its README)."
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
  if [ -n "$WANT_THEME" ]; then
    STEP=$((STEP + 1))
    say "  $STEP. Per user:  Tools > User Settings > Design > select \"$THEME_NAME\" > Save."
    say "                Core updates your session and reloads the page, so it"
    say "                applies immediately. If the frame still looks stock,"
    say "                hard-refresh (Ctrl+Shift+R) to drop the cached CSS."
    STEP=$((STEP + 1))
    say "  $STEP. System wide + login screen — set in BOTH config files:"
    say
    say "       \$conf['theme'] = '$THEME_NAME';"
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
    say "     hiding need a brand-aware design such as Clarity, installed by --theme."
  fi
  say
  if [ -n "$WANT_THEME" ]; then
    say "IMPORTANT — after ANY ISPConfig upgrade, including patch releases such as"
    say "3.3.1p1 -> 3.3.1p2, re-run this script so the version gate is re-stamped."
    say "Skip it and the theme silently reverts to 'default' at every login."
    say "Then diff the six overridden templates (three shell + three dashboard"
    say "dashlets) against the new stock ones — they are listed with their pinned"
    say "contracts in themes/$THEME_NAME/BUILT-AGAINST.txt."
    say
  fi
  say "No ISPConfig core file was modified."
}
