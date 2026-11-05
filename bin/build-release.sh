#!/usr/bin/env bash
# Build dist/wbcom-demo-installer-<version>.zip with only the files the plugin needs at runtime.
set -euo pipefail
cd "$(dirname "$0")/.."
VERSION=$(sed -n 's/^ \* Version: *//p' wbcom-demo-installer.php | tr -d '\r')
STAGE=$(mktemp -d)/wbcom-demo-installer
mkdir -p "$STAGE" dist
rsync -a --exclude-from=.distignore ./ "$STAGE/"
( cd "$(dirname "$STAGE")" && zip -qr "$OLDPWD/dist/wbcom-demo-installer-$VERSION.zip" wbcom-demo-installer )
echo "dist/wbcom-demo-installer-$VERSION.zip"
