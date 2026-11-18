<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Screen;

use Skilitsa\Core\Admin\AdminMenu;
use Skilitsa\Core\Ai\Draft\Draft;
use Skilitsa\Core\Ai\Draft\DraftReview;
use Skilitsa\Core\Ai\Draft\DraftStore;
use Skilitsa\Core\Ai\Draft\CopyRepository;
use Skilitsa\Core\Ai\Draft\CopyTarget;
use Skilitsa\Core\Ai\TaskRunner;
use Skilitsa\Core\Ai\TaskRegistry;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Data\Wpdb\BreedAdminRepository;
use Skilitsa\Core\Data\Wpdb\TraitTuningRepository;

/**
 * Review what the AI suggested. A shim.
 *
 * Nothing here decides anything: DraftReview turns an approval into the input a
 * human form would have received, that form validates it, and only then does a
 * repository write. Rejecting writes nothing at all.
 */
final class AiDraftsScreen
{
    private const NONCE = 'skilitsa_ai_drafts';

    public function render(): void
    {
        if (!current_user_can(AdminMenu::CAPABILITY)) {
            wp_die('You do not have permission to view this page.');
        }

        $store    = new DraftStore();
        $registry = new TaskRegistry();
        $notices  = [];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            check_admin_referer(self::NONCE);

            $action = (string) ($_POST['skilitsa_action'] ?? '');

            if (str_starts_with($action, 'run_')) {
                $notices = $this->runTask(substr($action, 4));
            } else {
                $notices = $this->judge($store, $action, (int) ($_POST['draft'] ?? 0));
            }
        }

        $tasks   = $registry->forDisplay();
        $counts  = $store->counts();
        $drafts  = $this->withStaleness($store->pending());

        $nonceField = self::NONCE;

        require dirname(__DIR__) . '/templates/ai-drafts.php';
    }

    /** @return list<array{0: string, 1: string}> type, message */
    private function runTask(string $taskKey): array
    {
        try {
            $result = (new TaskRunner())->run($taskKey);
        } catch (\InvalidArgumentException $e) {
            return [['error', $e->getMessage()]];
        }

        $notices = [];

        foreach ($result['failures'] as $failure) {
            $notices[] = ['error', $failure];
        }

        if ($result['calls'] > 0) {
            $notices[] = ['success', sprintf(
                '%s: %d call(s), %d new draft(s).',
                $taskKey,
                $result['calls'],
                $result['added']
            )];
        }

        // Warnings are the cheapest signal that an answer was improvised, so
        // they are shown rather than logged - but ten is enough to see the
        // pattern without burying the result.
        foreach (array_slice($result['warnings'], 0, 10) as $warning) {
            $notices[] = ['warning', $warning];
        }

        return $notices;
    }

    /** @return list<array{0: string, 1: string}> */
    private function judge(DraftStore $store, string $action, int $id): array
    {
        $draft = $store->find($id);

        if ($draft === null) {
            return [['error', 'That draft no longer exists.']];
        }

        if ($action === 'reject') {
            $outcome = DraftReview::reject($draft);
            $store->saveJudgement($outcome->draft);

            return [['success', 'Draft rejected. Nothing was changed.']];
        }

        if ($action !== 'approve') {
            return [['error', 'Unknown action.']];
        }

        $draft   = $this->markIfStale($draft);
        $outcome = DraftReview::approve($draft, $this->contextFor($draft));

        if (!$outcome->ok) {
            return [['error', $outcome->failure]];
        }

        $this->write($outcome->draft, $outcome->values);
        $store->saveJudgement($outcome->draft);

        return [['success', 'Draft approved and applied.']];
    }

    /** @param array<string,mixed> $values */
    private function write(Draft $draft, array $values): void
    {
        if ($draft->targetType === Draft::TARGET_BREED_TRAIT) {
            $postId = $this->postIdFor($draft->targetKey);

            if ($postId > 0) {
                (new BreedAdminRepository())->saveValues($postId, $values);
            }

            return;
        }

        if ($draft->targetType === Draft::TARGET_TRAIT_PENALTY) {
            (new TraitTuningRepository())->save($values);

            return;
        }

        if ($draft->targetType === Draft::TARGET_COPY) {
            $target = CopyTarget::parse((string) ($values['target'] ?? ''));

            if ($target !== null) {
                (new CopyRepository())->write($target, (string) $values['field'], (string) $values['text']);
            }
        }
    }

    /** @return array<string,mixed> */
    private function contextFor(Draft $draft): array
    {
        if ($draft->targetType === Draft::TARGET_BREED_TRAIT) {
            return ['traitKeys' => array_keys((new BreedAdminRepository())->traitLabels())];
        }

        if ($draft->targetType === Draft::TARGET_COPY) {
            return [];
        }

        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT penalty_over, penalty_under, global_weight FROM {$table} WHERE trait_key = %s",
                $draft->targetKey
            ),
            ARRAY_A
        );

        return is_array($row)
            ? ['row' => [
                'over'   => (float) $row['penalty_over'],
                'under'  => (float) $row['penalty_under'],
                'weight' => (float) $row['global_weight'],
            ]]
            : [];
    }

    /**
     * @param  list<Draft> $drafts
     * @return list<Draft>
     */
    private function withStaleness(array $drafts): array
    {
        return array_map(fn (Draft $draft) => $this->markIfStale($draft), $drafts);
    }

    private function markIfStale(Draft $draft): Draft
    {
        $live = $this->liveValue($draft);

        return $live !== null && $draft->isStaleAgainst($live) ? $draft->markStale() : $draft;
    }

    private function liveValue(Draft $draft): ?string
    {
        global $wpdb;

        $tables = Schema::tableNames($wpdb->prefix);

        if ($draft->targetType === Draft::TARGET_BREED_TRAIT) {
            $postId = $this->postIdFor($draft->targetKey);

            if ($postId === 0) {
                return null;
            }

            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT value FROM {$tables['breed_traits']} WHERE breed_id = %d AND trait_key = %s",
                $postId,
                $draft->field
            ));

            return $value === null ? '' : (string) $value;
        }

        if ($draft->targetType === Draft::TARGET_COPY) {
            $target = CopyTarget::parse($draft->targetKey);

            return $target === null ? null : (new CopyRepository())->read($target, $draft->field);
        }

        if ($draft->targetType === Draft::TARGET_TRAIT_PENALTY) {
            $column = in_array($draft->field, ['penalty_over', 'penalty_under', 'global_weight'], true)
                ? $draft->field
                : null;

            if ($column === null) {
                return null;
            }

            $value = $wpdb->get_var($wpdb->prepare(
                "SELECT {$column} FROM {$tables['trait_definitions']} WHERE trait_key = %s",
                $draft->targetKey
            ));

            return $value === null ? null : (string) $value;
        }

        return null;
    }

    private function postIdFor(string $slug): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_status = 'publish' LIMIT 1",
            $slug
        ));
    }
}
