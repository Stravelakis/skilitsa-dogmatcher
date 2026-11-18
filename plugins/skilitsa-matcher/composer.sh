#!/usr/bin/env bash
# Run Composer against skilitsa-matcher with the dev php.ini loaded.
#
#   bash plugins/skilitsa-matcher/composer.sh install
#   bash plugins/skilitsa-matcher/composer.sh require --dev some/package
#
# Composer itself needs the openssl extension, which Local's CLI PHP does not
# enable by default. See dev/php.ini.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"

PHP="${SKILITSA_PHP:-php}"
INI="${SKILITSA_PHP_INI:-$REPO/dev/php.ini}"

if [ ! -f "$REPO/composer.phar" ]; then
  echo "composer.phar is missing. Fetch it with:" >&2
  echo "  curl -sS --ssl-no-revoke -o composer.phar https://getcomposer.org/composer-stable.phar" >&2
  exit 1
fi

cd "$HERE"
exec "$PHP" -c "$INI" "$REPO/composer.phar" "$@"
