#!/usr/bin/env bash
# Build a WordPress-ready ZIP for GitHub Releases.
# Usage: ./bin/build-release.sh [version]
# Example: ./bin/build-release.sh 1.1.0
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SLUG="opkk-wp"
VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
  VERSION="$(grep -E '^\s*\*\s*Version:' "$ROOT/opkk-wp.php" | head -1 | sed -E 's/.*Version:\s*//')"
fi

if [[ -z "$VERSION" ]]; then
  echo "Could not detect version. Pass it explicitly: ./bin/build-release.sh 1.1.0" >&2
  exit 1
fi

OUT_DIR="$ROOT/dist"
STAGE="$OUT_DIR/$SLUG"
ZIP_NAME="${SLUG}-${VERSION}.zip"

mkdir -p "$OUT_DIR"
rm -rf "$STAGE"
mkdir -p "$STAGE"

# Copy plugin files into opkk-wp/ folder (required WP install layout)
rsync -a \
  --exclude '.git/' \
  --exclude '.cursor/' \
  --exclude '.idea/' \
  --exclude '.vscode/' \
  --exclude 'dist/' \
  --exclude 'bin/' \
  --exclude 'cache/*' \
  --exclude '*.zip' \
  --exclude '.DS_Store' \
  --exclude '*.log' \
  --exclude '*.tmp' \
  --exclude '*.bak' \
  "$ROOT/" "$STAGE/"

# Keep cache dir placeholder
mkdir -p "$STAGE/cache"
if [[ -f "$ROOT/cache/.gitignore" ]]; then
  cp "$ROOT/cache/.gitignore" "$STAGE/cache/.gitignore"
fi

# Safe defaults for schedule flags in release packages
if [[ -f "$STAGE/schedule.json" ]]; then
  printf '%s\n' '{"enable":false}' > "$STAGE/schedule.json"
fi
if [[ -f "$STAGE/schedule_kk.json" ]]; then
  printf '%s\n' '{"enable":false}' > "$STAGE/schedule_kk.json"
fi

(
  cd "$OUT_DIR"
  rm -f "$ZIP_NAME"
  zip -rq "$ZIP_NAME" "$SLUG"
)

rm -rf "$STAGE"

echo "Built: $OUT_DIR/$ZIP_NAME"
echo "Upload this ZIP as a GitHub Release asset (tag v${VERSION})."
