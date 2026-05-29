#!/usr/bin/env bash
set -euo pipefail

CHANNEL="${1:-github}"
if [[ "$CHANNEL" != "github" && "$CHANNEL" != "wporg" ]]; then
  echo "Usage: $0 [github|wporg]" >&2
  exit 1
fi

for command_name in tar awk zip; do
  if ! command -v "$command_name" >/dev/null 2>&1; then
    echo "Missing required command: $command_name" >&2
    echo "Install it and retry. On Debian/Ubuntu: sudo apt-get install -y zip tar gawk" >&2
    exit 1
  fi
done

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SOURCE_SLUG="lsky_plugin_wp"
WPORG_SLUG="lskypro"
PLUGIN_SLUG="$SOURCE_SLUG"
if [[ "$CHANNEL" == "wporg" ]]; then
  PLUGIN_SLUG="$WPORG_SLUG"
fi
BUILD_ROOT="$ROOT/build"
STAGE_ROOT="$BUILD_ROOT/$CHANNEL"
STAGE_PLUGIN="$STAGE_ROOT/$PLUGIN_SLUG"
ZIP_PATH="$BUILD_ROOT/$PLUGIN_SLUG-$CHANNEL.zip"

rm -rf "$STAGE_ROOT"
mkdir -p "$STAGE_PLUGIN"

tar \
  --exclude='./.git' \
  --exclude='./.github' \
  --exclude='./.gitignore' \
  --exclude='./.distignore' \
  --exclude='./build' \
  --exclude='./logs' \
  --exclude='./*.log' \
  --exclude='./*.zip' \
  --exclude='./node_modules' \
  --exclude='./.playwright-tools' \
  --exclude='./tmp' \
  --exclude='./build-channel.sh' \
  --exclude='./update.example.json' \
  -C "$ROOT" -cf - . | tar -C "$STAGE_PLUGIN" -xf -

if [[ "$CHANNEL" == "wporg" ]]; then
  rm -f "$STAGE_PLUGIN/src/SelfHostedUpdater.php"
  awk '
    /LSKY_GITHUB_CHANNEL_BEGIN/ { skip = 1; next }
    /LSKY_GITHUB_CHANNEL_END/ { skip = 0; next }
    !skip { print }
  ' "$STAGE_PLUGIN/src/display.php" > "$STAGE_PLUGIN/src/display.php.tmp"
  mv "$STAGE_PLUGIN/src/display.php.tmp" "$STAGE_PLUGIN/src/display.php"
  mv "$STAGE_PLUGIN/LskyPro.php" "$STAGE_PLUGIN/__wporg-main.php"
  mv "$STAGE_PLUGIN/__wporg-main.php" "$STAGE_PLUGIN/$WPORG_SLUG.php"
  awk '
    /^[[:space:]]*\*[[:space:]]Update URI:/ { next }
    /^use LskyProPlugin\\SelfHostedUpdater;/ { next }
    /^if \(class_exists\(SelfHostedUpdater::class\)\) \{/ { skip = 1; next }
    skip && /^\}/ { skip = 0; next }
    skip { next }
    { print }
  ' "$STAGE_PLUGIN/$WPORG_SLUG.php" > "$STAGE_PLUGIN/$WPORG_SLUG.php.tmp"
  mv "$STAGE_PLUGIN/$WPORG_SLUG.php.tmp" "$STAGE_PLUGIN/$WPORG_SLUG.php"
fi

rm -f "$ZIP_PATH"
(
  cd "$STAGE_ROOT"
  zip -qr "$ZIP_PATH" "$PLUGIN_SLUG"
)

echo "$ZIP_PATH"
