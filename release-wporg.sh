#!/usr/bin/env bash
set -euo pipefail

# Release the wporg channel build to the WordPress.org plugin SVN.
#
# Usage:
#   bash release-wporg.sh            # build, sync trunk, confirm, commit, tag
#   bash release-wporg.sh --yes      # same but skip the confirmation prompt
#   bash release-wporg.sh --dry-run  # build + sync + show status, no commit/tag
#
# Auth: svn prompts for credentials on first use, or set
#   WPORG_SVN_USERNAME / WPORG_SVN_PASSWORD environment variables.
# The version is read from readme.txt (Stable tag) and must match the
# Version header in LskyPro.php.

YES=0
DRY_RUN=0
for arg in "$@"; do
  case "$arg" in
    --yes) YES=1 ;;
    --dry-run) DRY_RUN=1 ;;
    *) echo "Usage: $0 [--yes] [--dry-run]" >&2; exit 1 ;;
  esac
done

# svn/zip only exist inside WSL on this machine; re-exec there when needed.
if ! command -v svn >/dev/null 2>&1; then
  if command -v wsl.exe >/dev/null 2>&1; then
    echo "svn not found in this shell, re-running inside WSL..."
    WIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && (pwd -W 2>/dev/null || pwd))"
    exec wsl.exe -e bash -c "cd \"\$(wslpath '$WIN_DIR')\" && bash release-wporg.sh $*"
  fi
  echo "Missing required command: svn" >&2
  exit 1
fi

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WPORG_SLUG="yaoyue-image-upload-for-lskypro"
REPO_URL="https://plugins.svn.wordpress.org/$WPORG_SLUG"
SVN_DIR="${WPORG_SVN_DIR:-$ROOT/../wporg-svn}"
SVN_USERNAME="${WPORG_SVN_USERNAME:-yaoyue}"

AUTH_ARGS=(--username "$SVN_USERNAME")
if [[ -n "${WPORG_SVN_PASSWORD:-}" ]]; then
  AUTH_ARGS+=(--password "$WPORG_SVN_PASSWORD" --non-interactive --no-auth-cache)
fi

VERSION="$(awk -F': *' '/^Stable tag:/ {gsub(/[ \r]/, "", $2); print $2; exit}' "$ROOT/readme.txt")"
HEADER_VERSION="$(awk -F': *' '/^[[:space:]]*\*[[:space:]]*Version:/ {gsub(/[ \r]/, "", $2); print $2; exit}' "$ROOT/LskyPro.php")"

if [[ -z "$VERSION" ]]; then
  echo "Could not read Stable tag from readme.txt" >&2
  exit 1
fi
if [[ "$VERSION" != "$HEADER_VERSION" ]]; then
  echo "Version mismatch: readme.txt Stable tag is '$VERSION' but LskyPro.php Version is '$HEADER_VERSION'." >&2
  echo "Align both before releasing." >&2
  exit 1
fi

echo "Releasing version: $VERSION"

if svn ls "$REPO_URL/tags/$VERSION/" --non-interactive >/dev/null 2>&1; then
  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "Note: tags/$VERSION already exists on wp.org (ok for a dry run)."
  else
    echo "tags/$VERSION already exists on wp.org. Bump the version first." >&2
    exit 1
  fi
fi

bash "$ROOT/build-channel.sh" wporg
STAGE="$ROOT/build/wporg/$WPORG_SLUG"

if [[ ! -d "$SVN_DIR/.svn" ]]; then
  echo "SVN working copy not found, checking out to $SVN_DIR ..."
  svn checkout "$REPO_URL" "$SVN_DIR" --non-interactive
else
  svn update "$SVN_DIR" --non-interactive
fi

TRUNK="$SVN_DIR/trunk"
find "$TRUNK" -mindepth 1 -delete
cp -r "$STAGE/." "$TRUNK/"

cd "$SVN_DIR"
svn add --force trunk >/dev/null
svn status trunk | awk '/^!/ {print $2}' | while read -r missing; do
  svn rm "$missing" >/dev/null
done

CHANGES="$(svn status trunk)"
echo "--- svn status (trunk):"
if [[ -n "$CHANGES" ]]; then
  echo "$CHANGES"
else
  echo "(no changes vs current trunk)"
fi

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "Dry run: skipping commit and tag."
  exit 0
fi

if [[ "$YES" -ne 1 ]]; then
  read -r -p "Commit trunk and create tags/$VERSION on wp.org? [y/N] " answer
  if [[ "$answer" != "y" && "$answer" != "Y" ]]; then
    echo "Aborted."
    exit 1
  fi
fi

if [[ -n "$CHANGES" ]]; then
  svn commit trunk -m "Release $VERSION" "${AUTH_ARGS[@]}"
fi
svn copy "$REPO_URL/trunk" "$REPO_URL/tags/$VERSION" -m "Tagging version $VERSION" "${AUTH_ARGS[@]}"
svn update >/dev/null 2>&1 || true

echo "Done. Released $VERSION to https://wordpress.org/plugins/$WPORG_SLUG/"
