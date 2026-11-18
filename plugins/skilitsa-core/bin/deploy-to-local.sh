#!/usr/bin/env bash
# Deploy skilitsa-core into the Local site as a RUNTIME build.
#
# Why this exists: copying the development folder wholesale ships PHPUnit and
# its dependencies - about 1,700 files - into wp-content/plugins, where they do
# nothing but slow down file scanning and plugin-directory traversal. Test code
# has no business on a site, even a local one.
#
# This copies only what runs, then installs production dependencies with an
# optimised classmap.
#
#   bash plugins/skilitsa-core/bin/deploy-to-local.sh
set -euo pipefail

HERE="$(cd "$(dirname "$0")/.." && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"

PHP="${SKILITSA_PHP:-php}"
INI="${SKILITSA_PHP_INI:-$REPO/dev/php.ini}"
DEST="${SKILITSA_LOCAL_PLUGINS:?set SKILITSA_LOCAL_PLUGINS to your local wp-content/plugins folder}/skilitsa-core"

echo "Deploying to: $DEST"

rm -rf "$DEST"
mkdir -p "$DEST"

# Runtime only. No tests, no bin, no dev tooling, no phpunit config.
cp "$HERE/skilitsa-core.php" "$DEST/"
cp "$HERE/composer.json"     "$DEST/"
cp "$HERE/composer.lock"     "$DEST/"
cp -r "$HERE/src"            "$DEST/"

echo "Installing production dependencies..."
cd "$DEST"
"$PHP" -c "$INI" "$REPO/composer.phar" install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --quiet

# composer.json/lock are only needed during install; they leak dependency
# details onto the site otherwise.
rm -f "$DEST/composer.json" "$DEST/composer.lock"

echo "Deployed $(find "$DEST" -type f | wc -l) files."
