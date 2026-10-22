#!/usr/bin/env bash
#
# Checks that $plugin->version keeps every maintained MOODLE_*_STABLE line in
# order, on the branch tips and on the last AY-* release of each line.
# Moodle never installs a lower version over a higher one, so an older line
# that outranks a newer one strands sites on the older line (issue 109).
#
# Usage, from the plugin repository root:
#   .github/scripts/check-version-order.sh              # branch tips as pushed
#   .github/scripts/check-version-order.sh 501          # this checkout's version.php as 501
#   .github/scripts/check-version-order.sh 501 FILE     # FILE as the 501 version.php
#
# With LINE given, only problems that involve LINE fail; others are warnings.
# MIN_LINE (default 500) is the oldest line still supported.
# SKIP_FETCH=1 uses the refs already fetched.
# REPORT_ONLY=1 prints every problem as a warning and exits 0.

set -u

MIN_LINE=${MIN_LINE:-500}
OVERRIDE_LINE=${1:-}
OVERRIDE_FILE=${2:-version.php}

if [[ -z "${SKIP_FETCH:-}" ]]; then
  git fetch -q origin '+refs/heads/MOODLE_*_STABLE:refs/remotes/origin/MOODLE_*_STABLE' --tags \
    || { echo "error: git fetch failed" >&2; exit 2; }
fi

version_php() {
  local line=$1 ref=$2
  if [[ "$line" == "$OVERRIDE_LINE" && "$ref" != refs/tags/* ]]; then
    cat "$OVERRIDE_FILE"
  else
    git show "$ref:version.php" 2>/dev/null
  fi
}

field() {
  sed -n "s/^\$plugin->$1 *= *'\{0,1\}\([^';]*\)'\{0,1\};.*/\1/p" | head -1
}

supported() {
  awk '/^\$plugin->supported/ { found = 1 } found { print; if (/;/) exit }' | tr -d ' \n' | grep -o '[0-9][0-9]*' | tr '\n' ' ' | sed 's/ $//'
}

# Every value must pass this before (( )): a non-number makes (( )) error and
# the check pass, $(...) in an array subscript runs, and a leading 0 is octal.
is_version() {
  [[ "$1" =~ ^[1-9][0-9]*$ ]]
}

LINES=$(git for-each-ref --format='%(refname:short)' 'refs/remotes/origin/MOODLE_*_STABLE' \
  | sed -n 's#^origin/MOODLE_\([0-9]*\)_STABLE$#\1#p' \
  | awk -v min="$MIN_LINE" '$1 >= min' | sort -n)

if [[ -n "$OVERRIDE_LINE" ]] && ! echo "$LINES" | grep -qx "$OVERRIDE_LINE"; then
  echo "error: no origin/MOODLE_${OVERRIDE_LINE}_STABLE" >&2
  exit 2
fi

ROWS=""
printf '%-6s %-12s %-8s %-10s | %-11s %-12s %s\n' LINE TIP RELEASE SUPPORTED LAST_TAG TAG_VERSION TAG_RELEASE
for LINE in $LINES; do
  TIP_PHP=$(version_php "$LINE" "origin/MOODLE_${LINE}_STABLE")
  TIP=$(echo "$TIP_PHP" | field version)
  REL=$(echo "$TIP_PHP" | field release)
  SUP=$(echo "$TIP_PHP" | supported)
  TAG=$(git tag -l "AY-${LINE}-R*" | sort -t R -k2,2n | tail -1)
  TAG_VER=""; TAG_REL=""
  if [[ -n "$TAG" ]]; then
    TAG_PHP=$(git show "refs/tags/$TAG:version.php" 2>/dev/null)
    TAG_VER=$(echo "$TAG_PHP" | field version)
    TAG_REL=$(echo "$TAG_PHP" | field release)
  fi
  printf '%-6s %-12s %-8s %-10s | %-11s %-12s %s\n' "$LINE" "$TIP" "$REL" "${SUP:--}" "${TAG:--}" "${TAG_VER:--}" "${TAG_REL:--}"
  ROWS="$ROWS$LINE|$TIP|$REL|$SUP|$TAG|$TAG_VER|$TAG_REL
"
done
echo

FAIL=0
say() { if [[ -n "${GITHUB_ACTIONS:-}" ]]; then echo "::$1::$2"; else echo "$2"; fi; }
warn() { say warning "WARN: $1"; }
# With LINE given, only problems that involve LINE fail; the rest are reported.
# REPORT_ONLY=1 reports everything and never fails.
fail() {
  if [[ -n "${REPORT_ONLY:-}" ]] || [[ -n "$OVERRIDE_LINE" && " $1 " != *" $OVERRIDE_LINE "* ]]; then
    warn "$2"
  else
    say error "FAIL: $2"
    FAIL=1
  fi
}

while IFS='|' read -r LINE TIP REL SUP TAG TAG_VER TAG_REL; do
  [[ -z "$LINE" ]] && continue
  B="MOODLE_${LINE}_STABLE"

  [[ -z "$TIP" ]] && { fail "$LINE" "$B: could not read \$plugin->version"; continue; }
  is_version "$TIP" || { fail "$LINE" "$B: \$plugin->version '$TIP' is not a number"; continue; }
  if [[ -n "$TAG_VER" ]] && ! is_version "$TAG_VER"; then
    fail "$LINE" "$TAG: \$plugin->version '$TAG_VER' is not a number"; continue
  fi

  if [[ -z "$SUP" ]]; then
    fail "$LINE" "$B: no \$plugin->supported; the Marketplace API would infer the Moodle range (want [$LINE, $LINE])"
  elif [[ "$SUP" != "$LINE $LINE" ]]; then
    fail "$LINE" "$B: \$plugin->supported is [$SUP], want [$LINE, $LINE]"
  fi

  if [[ -n "$TAG_VER" ]]; then
    if (( TIP < TAG_VER )); then
      fail "$LINE" "$B: tip $TIP is below its own release $TAG ($TAG_VER)"
    elif (( TIP == TAG_VER )) && [[ "$REL" != "$TAG_REL" ]]; then
      fail "$LINE" "$B: release changed to '$REL' but version $TIP is already published as $TAG; bump \$plugin->version"
    elif (( TIP > TAG_VER )) && [[ "$REL" == "$TAG_REL" ]]; then
      warn "$B: version moved past $TAG but release is still '$REL'; bump the release string before tagging"
    fi
  fi

  while IFS='|' read -r OLINE OTIP _ _ OTAG OTAG_VER _; do
    [[ -z "$OLINE" ]] || (( OLINE <= LINE )) && continue
    OB="MOODLE_${OLINE}_STABLE"
    # A bad value on the other line is reported on its own pass.
    is_version "$OTIP" || OTIP=""
    is_version "$OTAG_VER" || OTAG_VER=""
    if [[ -n "$OTIP" ]] && (( TIP >= OTIP )); then
      fail "$LINE $OLINE" "$B tip $TIP is not below newer $OB tip $OTIP"
    fi
    if [[ -n "$OTIP" && -n "$TAG_VER" ]] && (( OTIP <= TAG_VER )); then
      fail "$LINE $OLINE" "newer $OB tip $OTIP is not above released $TAG ($TAG_VER)"
    fi
    if [[ -n "$TAG_VER" && -n "$OTAG_VER" ]] && (( TAG_VER >= OTAG_VER )); then
      # Already published; a pull request can't change it, only a new release can.
      msg="released $TAG ($TAG_VER) is not below newer released $OTAG ($OTAG_VER); sites on $B cannot upgrade Moodle"
      if [[ -n "$OVERRIDE_LINE" ]]; then warn "$msg"; else fail "$LINE $OLINE" "$msg"; fi
    fi
    if [[ -n "$OTAG_VER" ]] && (( TIP >= OTAG_VER )) && [[ "$TIP" != "$TAG_VER" ]]; then
      warn "releasing $B at $TIP would outrank released $OTAG ($OTAG_VER); release $OB first or in the same round"
    fi
  done <<< "$ROWS"
done <<< "$ROWS"

if (( FAIL )); then
  exit 1
fi
echo "OK: no version-order problem${OVERRIDE_LINE:+ involving MOODLE_${OVERRIDE_LINE}_STABLE}"
