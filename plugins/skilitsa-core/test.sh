#!/usr/bin/env bash
# Run the skilitsa-core test suite.
#
#   bash plugins/skilitsa-core/test.sh                    # everything
#   bash plugins/skilitsa-core/test.sh --testsuite unit   # unit only
#   bash plugins/skilitsa-core/test.sh --filter BreedScorerTest
#
# Override the PHP binary with SKILITSA_PHP if you are not on the machine this
# was set up on. See dev/php.ini for why the -c flag is needed.
set -euo pipefail

HERE="$(cd "$(dirname "$0")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"

PHP="${SKILITSA_PHP:-php}"
INI="${SKILITSA_PHP_INI:-$REPO/dev/php.ini}"

if [ ! -f "$HERE/vendor/bin/phpunit" ]; then
  echo "PHPUnit is not installed. Run:" >&2
  echo "  bash plugins/skilitsa-core/composer.sh install" >&2
  exit 1
fi

cd "$HERE"
exec "$PHP" -c "$INI" vendor/bin/phpunit "$@"
