#!/usr/bin/env bash
# Run the skilitsa-matcher test suite. See dev/php.ini for why -c is needed.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
PHP="${SKILITSA_PHP:-php}"
INI="${SKILITSA_PHP_INI:-$REPO/dev/php.ini}"
if [ ! -f "$HERE/vendor/bin/phpunit" ]; then
  echo "PHPUnit is not installed. Run: bash plugins/skilitsa-matcher/composer.sh install" >&2
  exit 1
fi
cd "$HERE"
exec "$PHP" -c "$INI" vendor/bin/phpunit "$@"
