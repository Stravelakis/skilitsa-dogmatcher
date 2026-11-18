<?php

/**
 * Import the Google Sheet and migrate breed posts. DRY RUN BY DEFAULT.
 *
 * Run through wp-cli so WordPress is loaded:
 *
 *   wp eval-file bin/migrate.php                     # dry run, writes nothing
 *   wp eval-file bin/migrate.php -- --apply-import   # write the sheet data
 *   wp eval-file bin/migrate.php -- --apply-migration
 *   wp eval-file bin/migrate.php -- --apply-import --apply-migration
 *
 * Order matters: the migration creates the breed posts, and the import joins
 * trait values to them by slug. On a first run, migrate before importing.
 *
 * No `declare(strict_types=1)` here: wp-cli eval-files this file, and the
 * declaration must be the first statement in a script.
 */

use Skilitsa\Core\Csv\CsvParser;
use Skilitsa\Core\Import\ImportExecutor;
use Skilitsa\Core\Import\ImportPlanner;
use Skilitsa\Core\Import\ImportReporter;
use Skilitsa\Core\Import\SheetSchema;
use Skilitsa\Core\Migration\MigrationExecutor;
use Skilitsa\Core\Migration\MigrationPlanner;
use Skilitsa\Core\Migration\MigrationReporter;
use Skilitsa\Core\WordPress\BreedPostFinder;
use Skilitsa\Core\WordPress\BreedPostType;

$args           = $args ?? [];
$applyImport    = in_array('--apply-import', $args, true);
$applyMigration = in_array('--apply-migration', $args, true);
$force          = in_array('--force', $args, true);

// The setting is what someone can change without a developer; the environment
// variable stays as an escape hatch for a scripted run against another sheet.
$storedSettings = get_option(\Skilitsa\Core\Data\Settings::OPTION_NAME, []);
$storedSheetId  = is_array($storedSettings) ? (string) ($storedSettings['sheet_id'] ?? '') : '';

$sheetId = getenv('SKILITSA_SHEET_ID')
    ?: ($storedSheetId !== '' ? $storedSheetId : \Skilitsa\Core\Data\Settings::DEFAULT_SHEET_ID);

echo "Skilitsa import & migration\n";
echo str_repeat('=', 60), "\n";
echo 'Mode: ', ($applyImport || $applyMigration) ? "APPLY\n" : "DRY RUN (nothing will be written)\n";
echo "\n";

// ---------------------------------------------------------------- migration
echo "--- MIGRATION ---\n\n";

$posts = (new BreedPostFinder())->find();
echo 'Breed posts found: ', count($posts), "\n\n";

$migrationPlan = (new MigrationPlanner())->plan(
    $posts,
    BreedPostType::POST_TYPE,
    BreedPostType::SLUG
);

echo (new MigrationReporter())->render($migrationPlan);
echo "\n";

if ($applyMigration) {
    $result = (new MigrationExecutor())->apply($migrationPlan, false, $force);

    printf("Applied: %d | skipped: %d\n", $result['applied'], $result['skipped']);
    foreach ($result['log'] as $line) {
        echo '  ', $line, "\n";
    }

    $redirectFile = WP_CONTENT_DIR . '/skilitsa-redirects.csv';
    $handle       = fopen($redirectFile, 'w');
    if ($handle !== false) {
        fwrite($handle, "from,to\n");
        foreach ($result['redirects'] as $redirect) {
            fwrite($handle, $redirect['from'] . ',' . $redirect['to'] . "\n");
        }
        fclose($handle);
        echo 'Redirect map written to ', $redirectFile, "\n";
        echo "Import it into Rank Math > Redirections.\n";
    }
}

// ------------------------------------------------------------------- import
echo "\n--- IMPORT ---\n\n";

$parser = new CsvParser();
$tabs   = [];

foreach (SheetSchema::REQUIRED_TABS as $tab) {
    $url = sprintf(SheetSchema::EXPORT_URL, rawurlencode($sheetId), rawurlencode($tab));

    $response = wp_remote_get($url, ['timeout' => 30]);
    if (is_wp_error($response)) {
        echo '  fetch failed for ', $tab, ': ', $response->get_error_message(), "\n";
        continue;
    }
    if (wp_remote_retrieve_response_code($response) !== 200) {
        echo '  fetch failed for ', $tab, ': HTTP ', wp_remote_retrieve_response_code($response), "\n";
        continue;
    }

    $tabs[$tab] = $parser->parse(wp_remote_retrieve_body($response));
    printf("  fetched %-18s %4d rows\n", $tab, count($tabs[$tab]['rows']));
}

echo "\n";

$importPlan = (new ImportPlanner())->plan($tabs);
echo (new ImportReporter())->render($importPlan);

if ($applyImport && !$importPlan->isSafeToApply()) {
    // The executor refuses an unsafe plan by throwing. Checking first turns a
    // stack trace into a sentence: the difference between a script that says
    // the sheet is unreachable and one that looks broken itself.
    echo "\nNothing was written. Fix the errors above and run it again.\n";
    $applyImport = false;
}

if ($applyImport) {
    echo "\n";
    $written = (new ImportExecutor())->apply($importPlan, false);
    echo "Written:\n";
    foreach ($written as $table => $count) {
        printf("  %-22s %d\n", $table, $count);
    }
}

echo "\nDone.\n";
