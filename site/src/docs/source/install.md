# Install DogMatcher

You need WordPress 6.4 or newer and PHP 8.1 or newer. You also need a Google Sheet with the eight tabs described in the Guide.

## Install the two plugins

1. Copy `plugins/skilitsa-core` and `plugins/skilitsa-matcher` into `wp-content/plugins/` on your site. (The repository's `bin/build-release.sh` builds upload-ready zips into `build/`, but it contains a Windows-specific PHP path you will need to change with `SKILITSA_PHP`.)
2. In WordPress, activate **Skilitsa Core** first, then **Skilitsa Matcher**. Matcher needs Core.
3. Open **Skilitsa -> Import**, point it at your sheet, review the plan, and apply it.
4. Add the quiz to a page with the block or `[skilitsa_matcher]`.

## Run the tests

From each plugin folder, `test.sh` runs PHPUnit (a dev dependency installed with Composer).

## Good to know

- The code has a default sheet ID set in `Settings.php`. Change the sheet ID in the plugin settings to your own before importing.
- The repository also holds `legacy/v1`, the old plugin, for reference only. Do not install it.
