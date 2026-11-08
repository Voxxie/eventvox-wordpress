#!/usr/bin/env bash
# Build eventvox.zip (the folder eventvox/ with the version set) for a release: bin/build-zip.sh 1.2.0
# Used by .github/workflows/release.yml; also works locally to try an install by hand (Plugins > Nieuwe plugin > Uploaden).
set -euo pipefail
VERSION="${1:?usage: bin/build-zip.sh <version, e.g. 1.2.0>}"
if ! [[ "$VERSION" =~ ^[0-9]+(\.[0-9]+){1,3}([-.][0-9A-Za-z.]+)?$ ]]; then
    echo "Not a version: $VERSION" >&2
    exit 1
fi
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$(mktemp -d)"
trap 'rm -rf "$BUILD"' EXIT
cp -R "$ROOT/eventvox" "$BUILD/eventvox"
# The version WordPress compares with the release: in the plugin header, the constant and readme.txt
sed -i.bak -E "s/^( \* Version:[[:space:]]+).*/\1$VERSION/" "$BUILD/eventvox/eventvox.php"
sed -i.bak -E "s/define\('EVENTVOX_VERSION', '[^']*'\)/define('EVENTVOX_VERSION', '$VERSION')/" "$BUILD/eventvox/eventvox.php"
sed -i.bak -E "s/^(Stable tag:[[:space:]]+).*/\1$VERSION/" "$BUILD/eventvox/readme.txt"
find "$BUILD/eventvox" -name '*.bak' -delete
grep -q "Version:           $VERSION" "$BUILD/eventvox/eventvox.php"
grep -q "define('EVENTVOX_VERSION', '$VERSION')" "$BUILD/eventvox/eventvox.php"
rm -f "$ROOT/eventvox.zip"
(cd "$BUILD" && zip -qr "$ROOT/eventvox.zip" eventvox -x '*.DS_Store')
echo "$ROOT/eventvox.zip ($VERSION)"
