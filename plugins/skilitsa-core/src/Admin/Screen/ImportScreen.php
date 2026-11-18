<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Screen;

use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Admin\View\ImportDiffViewModel;
use Skilitsa\Core\Csv\CsvParser;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Data\Settings;
use Skilitsa\Core\Import\ImportExecutor;
use Skilitsa\Core\Import\ImportPlan;
use Skilitsa\Core\Import\ImportPlanner;
use Skilitsa\Core\Import\SheetSchema;
use Throwable;

/**
 * Fetch the sheet, show what an import would change, then apply it.
 *
 * A shim. The one rule it enforces itself: a fetch failure is rendered and
 * nothing is written. That was v1's whole disaster - a failed fetch produced
 * empty arrays, the writer saw a non-empty result, and 397 KB of good data
 * became nothing while the log said success.
 */
final class ImportScreen
{
    private const NONCE = 'skilitsa_import';

    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('You do not have permission to view this page.');
        }

        $sheetId  = $this->sheetId();
        $fetched  = false;
        $applied  = null;
        $failure  = null;
        $model    = null;
        $plan     = null;

        $action = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            ? (string) ($_POST['skilitsa_action'] ?? '')
            : '';

        if ($action !== '') {
            check_admin_referer(self::NONCE);

            try {
                $plan    = $this->plan($sheetId);
                $fetched = true;
            } catch (Throwable $e) {
                $failure = $e->getMessage();
            }
        }

        if ($plan !== null) {
            $model = ImportDiffViewModel::build(
                $plan,
                $this->currentCounts(),
                $this->currentBreedKeys(),
                $this->tunedTraits()
            );

            if ($action === 'apply' && $model->canApply) {
                $applied = (new ImportExecutor())->apply($plan, false);
            }
        }

        $nonceField = self::NONCE;

        require dirname(__DIR__) . '/templates/import.php';
    }

    /**
     * The sheet to import from.
     *
     * The setting wins, because that is the one someone can change without a
     * developer. The environment variable stays as an escape hatch for a
     * scripted run against a different sheet.
     */
    private function sheetId(): string
    {
        $fromEnv = getenv('SKILITSA_SHEET_ID');

        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        $stored = get_option(Settings::OPTION_NAME, []);
        $id     = is_array($stored) ? (string) ($stored['sheet_id'] ?? '') : '';

        return $id !== '' ? $id : Settings::DEFAULT_SHEET_ID;
    }

    private function plan(string $sheetId): ImportPlan
    {
        $parser = new CsvParser();
        $tabs   = [];
        $failed = [];

        foreach (SheetSchema::REQUIRED_TABS as $tab) {
            $url = sprintf(SheetSchema::EXPORT_URL, rawurlencode($sheetId), rawurlencode($tab));

            $response = wp_remote_get($url, ['timeout' => 30]);

            if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
                $failed[] = $tab;
                continue;
            }

            $tabs[$tab] = $parser->parse(wp_remote_retrieve_body($response));
        }

        $plan = (new ImportPlanner())->plan($tabs);

        // A tab that never arrived is an error, not a smaller import.
        if ($failed !== []) {
            return new ImportPlan(
                errors: array_map(
                    static fn (string $tab) => sprintf('Tab %s could not be fetched.', $tab),
                    $failed
                )
            );
        }

        return $plan;
    }

    /** @return array<string,int> */
    private function currentCounts(): array
    {
        global $wpdb;

        $tables = Schema::tableNames($wpdb->prefix);

        $traitValues = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['breed_traits']}");

        return [
            'traits'       => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['trait_definitions']}"),
            'questions'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['questions']}"),
            'links'        => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['question_trait_map']}"),
            'templates'    => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tables['explanation_templates']}"),
            'ui_texts'     => 0,
            'settings'     => 0,
            'breeds'       => (int) $wpdb->get_var("SELECT COUNT(DISTINCT breed_id) FROM {$tables['breed_traits']}"),
            'trait_values' => $traitValues,
        ];
    }

    /** @return list<string> */
    private function currentBreedKeys(): array
    {
        global $wpdb;

        $tables = Schema::tableNames($wpdb->prefix);

        $keys = $wpdb->get_col(
            "SELECT DISTINCT p.post_name
             FROM {$tables['breed_traits']} bt
             INNER JOIN {$wpdb->posts} p ON p.ID = bt.breed_id"
        );

        return is_array($keys) ? array_map('strval', $keys) : [];
    }

    /** @return list<string> */
    private function tunedTraits(): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $keys = $wpdb->get_col("SELECT trait_key FROM {$table} WHERE is_tuned = 1");

        return is_array($keys) ? array_map('strval', $keys) : [];
    }
}
