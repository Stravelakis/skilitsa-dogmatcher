#!/usr/bin/env bash
# Builds the two plugins as upload-ready zips.
#
# Same rule as deploy-to-local.sh: runtime files only. Shipping the dev folder
# wholesale puts PHPUnit and ~1,700 files it drags in onto a live site, where
# they do nothing but slow down every plugin-directory scan.
#
# The one difference is bin/migrate.php. The local deploy leaves it out, but the
# live migration is run through it and there is no admin screen for the
# migration yet, so the release build includes it. Delete it from the server
# once the migration has run - the runbook says so at the right step.
#
#   bash bin/build-release.sh
#
# Output: build/skilitsa-core.zip, build/skilitsa-matcher.zip
set -euo pipefail

REPO="$(cd "$(dirname "$0")/.." && pwd)"
BUILD="$REPO/build"
PHP="${SKILITSA_PHP:-php}"
INI="${SKILITSA_PHP_INI:-$REPO/dev/php.ini}"

rm -rf "$BUILD"
mkdir -p "$BUILD"

build_plugin() {
    local name="$1"
    local src="$REPO/plugins/$name"
    local dest="$BUILD/$name"

    echo "--- $name"

    mkdir -p "$dest"
    cp "$src/$name.php" "$dest/"
    cp -r "$src/src" "$dest/"

    [ -d "$src/assets" ] && cp -r "$src/assets" "$dest/"
    [ -d "$src/templates" ] && cp -r "$src/templates" "$dest/"
    [ -d "$src/languages" ] && cp -r "$src/languages" "$dest/"

    if [ -f "$src/composer.json" ]; then
        cp "$src/composer.json" "$dest/"
        [ -f "$src/composer.lock" ] && cp "$src/composer.lock" "$dest/"

        ( cd "$dest" && "$PHP" -c "$INI" "$REPO/composer.phar" install \
            --no-dev --optimize-autoloader --no-interaction --quiet )

        # Only needed during install; they leak dependency detail onto the site.
        rm -f "$dest/composer.json" "$dest/composer.lock"
    fi
}

build_plugin skilitsa-core
build_plugin skilitsa-matcher

# The migration entry point, and only it. The calibration instruments and the
# rehearsal script have no business on a server.
mkdir -p "$BUILD/skilitsa-core/bin"
cp "$REPO/plugins/skilitsa-core/bin/migrate.php" "$BUILD/skilitsa-core/bin/"

echo
echo "--- zipping"

# python rather than zip(1): Git Bash on Windows ships without zip, and a
# build script that only runs on one machine is a build script nobody can run.
python - "$BUILD" <<'PYZIP'
import os, sys, zipfile

build = sys.argv[1]

for name in ("skilitsa-core", "skilitsa-matcher"):
    root = os.path.join(build, name)
    out = os.path.join(build, name + ".zip")

    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
        for folder, _, files in os.walk(root):
            for f in files:
                full = os.path.join(folder, f)
                z.write(full, os.path.relpath(full, build))

    with zipfile.ZipFile(out) as z:
        print("  %-24s %6.1f KB  %d files"
              % (name + ".zip", os.path.getsize(out) / 1024, len(z.namelist())))
PYZIP

echo
echo "Nothing here has been uploaded. See docs/RELEASE-RUNBOOK.md."
