#!/usr/bin/env bash
# Build dist/wbcom-demo-installer-<version>.zip with only the files the plugin needs at runtime.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(sed -n 's/^ \* Version: *//p' wbcom-demo-installer.php | tr -d '\r')
STAGE=$(mktemp -d)/wbcom-demo-installer
mkdir -p "$STAGE" dist
rsync -a --exclude-from=.distignore ./ "$STAGE/"
# Every tracked file outside the dev-only top-level paths must ship (an unanchored pattern once dropped media/docs).
MISSING=$(git ls-files | grep -vE '^(\.git|\.distignore|bin/|docs/|tests/|phpcs|phpstan|README\.md|CLAUDE\.md)' | while read -r f; do [ -e "$STAGE/$f" ] || echo "$f"; done)
[ -z "$MISSING" ] || { echo "Missing from build: $MISSING" >&2; exit 1; }
( cd "$(dirname "$STAGE")" && zip -qr "$OLDPWD/dist/wbcom-demo-installer-$VERSION.zip" wbcom-demo-installer )
echo "dist/wbcom-demo-installer-$VERSION.zip"
