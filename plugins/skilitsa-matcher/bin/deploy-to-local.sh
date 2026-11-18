#!/usr/bin/env bash
# Deploy skilitsa-matcher into the Local site as a runtime build.
# See plugins/skilitsa-core/bin/deploy-to-local.sh for why this exists.
set -euo pipefail

HERE="$(cd "$(dirname "$0")/.." && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"

PHP="${SKILITSA_PHP:-php}"
INI="${SKILITSA_PHP_INI:-$REPO/dev/php.ini}"
DEST="${SKILITSA_LOCAL_PLUGINS:?set SKILITSA_LOCAL_PLUGINS to your local wp-content/plugins folder}/skilitsa-matcher"

echo "Deploying to: $DEST"

rm -rf "$DEST"
mkdir -p "$DEST"

cp "$HERE/skilitsa-matcher.php" "$DEST/"
cp "$HERE/composer.json"        "$DEST/"
cp "$HERE/composer.lock"        "$DEST/"
cp -r "$HERE/src"               "$DEST/"
cp -r "$HERE/assets"            "$DEST/"
cp -r "$HERE/templates"         "$DEST/"

cd "$DEST"
"$PHP" -c "$INI" "$REPO/composer.phar" install \
    --no-dev --optimize-autoloader --no-interaction --quiet

rm -f "$DEST/composer.json" "$DEST/composer.lock"

echo "Deployed $(find "$DEST" -type f | wc -l) files."
