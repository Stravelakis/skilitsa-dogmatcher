<?php
declare(strict_types=1);

namespace Skilitsa\Core\Import;

use RuntimeException;
use Skilitsa\Core\Data\BreedKey;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Data\TemplateDefaults;
use Skilitsa\Core\Data\TraitDefaults;
use Skilitsa\Core\WordPress\BreedPostType;
use Throwable;

/**
 * Writes an ImportPlan to the database. Computes nothing.
 *
 * Refuses outright when the plan is not safe to apply, and wraps the whole
 * write in a transaction so a mid-import failure cannot leave half a matrix
 * behind. v1 had neither guard: it wrote whenever its result array was
 * non-empty, which it always was, so a failed fetch replaced 397 KB of good
 * data with empty arrays.
 */
final class ImportExecutor
{
    public const DISPLAY_NAME_META = '_skilitsa_breed_name';
    public const SHEET_KEY_META    = '_skilitsa_breed_key';

    /**
     * @return array<string,int> rows written per table
     * @throws RuntimeException when the plan is unsafe or the write fails
     */
    public function apply(ImportPlan $plan, bool $dryRun = false): array
    {
        if (!$plan->isSafeToApply()) {
            throw new RuntimeException(
                'Refusing to apply an import plan with errors: ' . implode(' ', $plan->errors)
            );
        }

        if ($dryRun) {
            return [
                'trait_definitions'     => count($plan->traits),
                'questions'             => count($plan->questions),
                'question_trait_map'    => count($plan->links),
                'explanation_templates' => count($plan->templates),
                'breed_traits'          => $plan->counts()['trait_values'],
            ];
        }

        global $wpdb;
        $tables = Schema::tableNames($wpdb->prefix);

        $wpdb->query('START TRANSACTION');

        try {
            $written = [
                'trait_definitions'     => $this->writeTraits($tables['trait_definitions'], $plan),
                'questions'             => $this->writeQuestions($tables['questions'], $plan),
                'question_trait_map'    => $this->writeLinks($tables['question_trait_map'], $plan),
                'explanation_templates' => $this->writeTemplates($tables['explanation_templates'], $plan),
                'breed_traits'          => $this->writeBreedTraits($tables['breed_traits'], $plan),
            ];

            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');

            throw new RuntimeException('Import failed and was rolled back: ' . $e->getMessage(), 0, $e);
        }

        return $written;
    }

    private function writeTraits(string $table, ImportPlan $plan): int
    {
        global $wpdb;

        // The sheet owns the copy; the tuning lives here. Rewriting the table
        // without this snapshot is how an import silently undoes an evening of
        // work in the matrix editor.
        $tuned = [];
        foreach ((array) $wpdb->get_results("SELECT trait_key, penalty_over, penalty_under, global_weight FROM {$table} WHERE is_tuned = 1", ARRAY_A) as $row) {
            $tuned[(string) $row['trait_key']] = $row;
        }

        $wpdb->query("DELETE FROM {$table}");

        $order = 0;
        $n     = 0;

        foreach ($plan->traits as $key => $row) {
            $keep      = $tuned[$key] ?? null;
            $penalties = TraitDefaults::forTrait($key);

            $wpdb->insert($table, [
                'trait_key'        => $key,
                'label'            => (string) ($row['label'] ?? ''),
                'description'      => (string) ($row['description'] ?? ''),
                'scale_low'        => (string) ($row['scale_low'] ?? ''),
                'scale_high'       => (string) ($row['scale_high'] ?? ''),
                'warning_too_low'  => (string) ($row['warning_too_low'] ?? ''),
                'warning_too_high' => (string) ($row['warning_too_high'] ?? ''),
                'penalty_over'     => $keep !== null ? (float) $keep['penalty_over'] : $penalties['over'],
                'penalty_under'    => $keep !== null ? (float) $keep['penalty_under'] : $penalties['under'],
                'global_weight'    => $keep !== null ? (float) $keep['global_weight'] : 1.0,
                'is_tuned'         => $keep !== null ? 1 : 0,
                'sort_order'       => $order++,
            ]);
            $n++;
        }

        return $n;
    }

    /**
     * A sheet checkbox.
     *
     * Google exports a ticked box as TRUE and an unticked one as an EMPTY
     * STRING, not as FALSE. Reading blank as "use the default" therefore turns
     * every unticked box back on, which is exactly what happened the first
     * time. Blank means false; only a missing column falls back to the default.
     *
     * @param array<string,mixed> $row
     */
    private static function flag(array $row, string $key, bool $whenColumnAbsent): bool
    {
        if (!array_key_exists($key, $row)) {
            return $whenColumnAbsent;
        }

        $text = strtolower(trim((string) $row[$key]));

        return in_array($text, ['1', 'true', 'yes', 'y', 'x', 'on'], true);
    }

    private function writeQuestions(string $table, ImportPlan $plan): int
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$table}");

        $n = 0;

        foreach ($plan->questions as $key => $row) {
            $weight = $row['weight'] ?? '';
            $order  = $row['order'] ?? '';

            // quick_mode marks the questions the owner already chose for a
            // short run, which is exactly the `tiered` flow's core set.
            // The sheet has always carried these two and nothing ever read
            // them. Several questions have a scale end that already means "no
            // preference" - "does barking bother you? not really" - and on
            // those the ignore button is a second way to say the same thing,
            // which makes a reader wonder what the difference is.
            $showDealbreaker = self::flag($row, 'show_dealbreaker', true);
            $showIgnore      = self::flag($row, 'show_ignore', true);

            $quick = strtolower(trim((string) ($row['quick_mode'] ?? '')));
            $tier  = in_array($quick, ['1', 'true', 'yes', 'y', 'x'], true) ? 'core' : 'extended';

            $helper = trim(
                trim((string) ($row['helper_text_1'] ?? '')) . ' ' .
                trim((string) ($row['helper_text_2'] ?? ''))
            );

            $wpdb->insert($table, [
                'question_key' => $key,
                'section_key'  => (string) ($row['section_key'] ?? ''),
                'label'        => (string) ($row['label'] ?? ''),
                'label_low'    => (string) ($row['label_low'] ?? ''),
                'label_high'   => (string) ($row['label_high'] ?? ''),
                'helper_text'  => $helper,
                'image_low'    => (string) ($row['image_low'] ?? ''),
                'image_high'   => (string) ($row['image_high'] ?? ''),
                'weight'       => is_numeric($weight) ? (float) $weight : 1.0,
                'show_dealbreaker' => $showDealbreaker ? 1 : 0,
                'show_ignore'      => $showIgnore ? 1 : 0,
                'tier'         => $tier,
                'sort_order'   => is_numeric($order) ? (int) $order : 0,
            ]);
            $n++;
        }

        return $n;
    }

    private function writeLinks(string $table, ImportPlan $plan): int
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$table}");

        $n = 0;

        foreach ($plan->links as $link) {
            $wpdb->insert($table, [
                'question_key' => $link['question_key'],
                'trait_key'    => $link['trait_key'],
                'direction'    => $link['direction'],
                'weight'       => (float) $link['weight'],
            ]);
            $n++;
        }

        return $n;
    }

    private function writeTemplates(string $table, ImportPlan $plan): int
    {
        global $wpdb;
        $wpdb->query("DELETE FROM {$table}");

        $n = 0;

        foreach (TemplateDefaults::fill($plan->templates) as $key => $body) {
            $wpdb->insert($table, ['template_key' => $key, 'body' => $body]);
            $n++;
        }

        return $n;
    }

    /**
     * Trait values join to breed posts by canonical slug. A breed with no post
     * yet is skipped rather than invented - creating posts is the migration's
     * job, not the importer's.
     */
    private function writeBreedTraits(string $table, ImportPlan $plan): int
    {
        global $wpdb;

        $ids = $this->breedPostIds();
        $wpdb->query("DELETE FROM {$table}");

        $n = 0;

        foreach ($plan->breedTraits as $breedKey => $values) {
            $postId = $ids[$breedKey] ?? null;
            if ($postId === null) {
                continue;
            }

            foreach ($values as $traitKey => $value) {
                $wpdb->insert($table, [
                    'breed_id'  => $postId,
                    'trait_key' => $traitKey,
                    'value'     => $value,
                ]);
                $n++;
            }

            $this->storeDisplayName($postId, $plan->breedMeta[$breedKey] ?? []);
        }

        return $n;
    }

    /**
     * Stores the breed's actual name as post meta.
     *
     * Post titles are article headlines - "Τσιουάουα: Ο Μεξικανός Βασιλιάς σε
     * Συσκευασία Τσέπης" - which read well above an article and badly on a
     * result card. The sheet carries the plain name, so keep it where the
     * matcher can find it.
     *
     * @param array<string,string> $meta
     */
    private function storeDisplayName(int $postId, array $meta): void
    {
        $name = trim((string) ($meta['label_el'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($meta['label_en'] ?? ''));
        }

        if ($name !== '') {
            update_post_meta($postId, self::DISPLAY_NAME_META, $name);
        }

        $key = trim((string) ($meta['breed_key'] ?? ''));
        if ($key !== '') {
            update_post_meta($postId, self::SHEET_KEY_META, $key);
        }
    }

    /** @return array<string,int> canonical breed key => post ID */
    private function breedPostIds(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
                BreedPostType::POST_TYPE
            ),
            ARRAY_A
        );

        $ids = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $ids[BreedKey::canonical((string) $row['post_name'])] = (int) $row['ID'];
        }

        return $ids;
    }
}
