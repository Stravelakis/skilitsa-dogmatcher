<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai;

use Skilitsa\Core\Ai\Client\AiClient;
use Skilitsa\Core\Ai\Client\HttpAiClient;
use Skilitsa\Core\Ai\Draft\CopyTarget;
use Skilitsa\Core\Ai\Draft\DraftStore;
use Skilitsa\Core\Ai\Task\CheckPenaltiesTask;
use Skilitsa\Core\Ai\Task\CopyTask;
use Skilitsa\Core\Ai\Task\DraftBreedTraitsTask;
use Skilitsa\Core\Ai\Task\MatrixQaTask;
use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Data\Wpdb\BreedAdminRepository;

/**
 * Runs any of the six tasks, and stores what survives.
 *
 * A shim: it gathers the data a task needs, calls out once, and hands the
 * answer back to the task to be checked. Every judgement about what is worth
 * keeping lives in the task classes, which have no WordPress in them and are
 * tested without it.
 *
 * Nothing here can write a trait value or a line of copy. The only thing it
 * writes is drafts.
 */
final class TaskRunner
{
    /** One call's worth of work, so a failure costs a batch rather than a run. */
    private const BREEDS_PER_QA_CALL = 25;
    private const COPY_PER_CALL      = 20;

    public function __construct(
        private readonly TaskRegistry $registry = new TaskRegistry(),
        private readonly AiClient $client = new HttpAiClient(),
        private readonly DraftStore $store = new DraftStore(),
        private readonly BreedAdminRepository $breeds = new BreedAdminRepository(),
    ) {
    }

    /** @return array{added: int, warnings: list<string>, failures: list<string>, calls: int} */
    public function run(string $taskKey): array
    {
        $definition = $this->registry->get($taskKey);

        if (!$definition->isAvailable) {
            return $this->result(0, [], ['This task is not configured. See docs/ai-configuration.md.'], 0);
        }

        return match ($taskKey) {
            MatrixQaTask::KEY          => $this->matrixQa($definition),
            CheckPenaltiesTask::KEY    => $this->checkPenalties($definition),
            DraftBreedTraitsTask::KEY  => $this->draftBreedTraits($definition),
            default                    => $this->copyTask($definition),
        };
    }

    /** @return array{added: int, warnings: list<string>, failures: list<string>, calls: int} */
    private function matrixQa(TaskDefinition $definition): array
    {
        $task   = new MatrixQaTask();
        $traits = $this->traitScales();
        $matrix = $this->matrix();

        $chunk = array_slice($matrix, 0, self::BREEDS_PER_QA_CALL, true);

        if ($chunk === []) {
            return $this->result(0, ['There are no breeds with values yet.'], [], 0);
        }

        $response = $this->client->ask(
            $definition,
            $task->systemPrompt(),
            $task->userPrompt($traits, $chunk)
        );

        if (!$response->ok) {
            return $this->result(0, [], [$response->failure], 1);
        }

        $result = $task->readResponse($response->data, $chunk, array_keys($traits), $definition->model);

        return $this->result($this->store->addAll($result->drafts), $result->warnings, [], 1);
    }

    /** @return array{added: int, warnings: list<string>, failures: list<string>, calls: int} */
    private function checkPenalties(TaskDefinition $definition): array
    {
        global $wpdb;

        $task  = new CheckPenaltiesTask();
        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $rows = $wpdb->get_results(
            "SELECT trait_key, label, scale_low, scale_high, penalty_over, penalty_under
             FROM {$table} ORDER BY sort_order, trait_key",
            ARRAY_A
        );

        $traits = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $traits[(string) $row['trait_key']] = [
                'label'      => (string) $row['label'],
                'scale_low'  => (string) $row['scale_low'],
                'scale_high' => (string) $row['scale_high'],
                'over'       => (string) $row['penalty_over'],
                'under'      => (string) $row['penalty_under'],
            ];
        }

        if ($traits === []) {
            return $this->result(0, ['There are no traits to review. Run an import first.'], [], 0);
        }

        $response = $this->client->ask($definition, $task->systemPrompt(), $task->userPrompt($traits));

        if (!$response->ok) {
            return $this->result(0, [], [$response->failure], 1);
        }

        $result = $task->readResponse($response->data, $traits, $definition->model);

        return $this->result($this->store->addAll($result->drafts), $result->warnings, [], 1);
    }

    /** @return array{added: int, warnings: list<string>, failures: list<string>, calls: int} */
    private function draftBreedTraits(TaskDefinition $definition): array
    {
        $task      = new DraftBreedTraitsTask();
        $traits    = $this->traitScales();
        $traitKeys = array_keys($traits);
        $matrix    = $this->matrix();

        // The emptiest breed first: it is the one doing the most damage in
        // results right now.
        $worst    = null;
        $worstKey = '';

        foreach ($matrix as $key => $breed) {
            $missing = array_values(array_diff($traitKeys, array_keys($breed['values'])));

            if ($missing === []) {
                continue;
            }

            if ($worst === null || count($missing) > count($worst['missing'])) {
                $worst    = ['breed' => $breed, 'missing' => $missing];
                $worstKey = (string) $key;
            }
        }

        if ($worst === null) {
            return $this->result(0, ['Every breed already has every trait value.'], [], 0);
        }

        $response = $this->client->ask(
            $definition,
            $task->systemPrompt(),
            $task->userPrompt($traits, $worst['breed'], $worst['missing'])
        );

        if (!$response->ok) {
            return $this->result(0, [], [$response->failure], 1);
        }

        $result = $task->readResponse(
            $response->data,
            $worstKey,
            $worst['breed'],
            $worst['missing'],
            $definition->model
        );

        return $this->result(
            $this->store->addAll($result->drafts),
            array_merge([sprintf('Drafted for "%s".', $worstKey)], $result->warnings),
            [],
            1
        );
    }

    /** @return array{added: int, warnings: list<string>, failures: list<string>, calls: int} */
    private function copyTask(TaskDefinition $definition): array
    {
        $task  = new CopyTask($definition->key);
        $items = $this->copyItems($definition->key);

        if ($items === []) {
            return $this->result(0, ['Nothing to review for this task.'], [], 0);
        }

        $items   = array_slice($items, 0, self::COPY_PER_CALL);
        $current = [];

        foreach ($items as $item) {
            $current[$item['target'] . '|' . $item['field']] = $item['text'];
        }

        $response = $this->client->ask($definition, $task->systemPrompt(), $task->userPrompt($items));

        if (!$response->ok) {
            return $this->result(0, [], [$response->failure], 1);
        }

        $result = $task->readResponse($response->data, $current, $definition->model);

        return $this->result($this->store->addAll($result->drafts), $result->warnings, [], 1);
    }

    /** @return list<array{target: string, field: string, text: string, context?: string}> */
    private function copyItems(string $taskKey): array
    {
        global $wpdb;

        if ($taskKey === CopyTask::ALT_TEXT) {
            return $this->breedImagesWithoutAlt();
        }

        $tables = Schema::tableNames($wpdb->prefix);
        $items  = [];

        if ($taskKey === CopyTask::QUESTION_AUDIT) {
            $rows = $wpdb->get_results(
                "SELECT question_key, label, label_low, label_high
                 FROM {$tables['questions']} ORDER BY sort_order",
                ARRAY_A
            );

            foreach (is_array($rows) ? $rows : [] as $row) {
                $items[] = [
                    'target'  => CopyTarget::of(CopyTarget::QUESTION, (string) $row['question_key'])->address(),
                    'field'   => 'label',
                    'text'    => (string) $row['label'],
                    'context' => sprintf('1 = %s | 5 = %s', $row['label_low'], $row['label_high']),
                ];
            }

            return $items;
        }

        // Translation: the explanation templates, which are the longest-lived
        // copy on the site.
        $rows = $wpdb->get_results(
            "SELECT template_key, body FROM {$tables['explanation_templates']} ORDER BY template_key",
            ARRAY_A
        );

        foreach (is_array($rows) ? $rows : [] as $row) {
            $items[] = [
                'target' => CopyTarget::of(CopyTarget::TEMPLATE, (string) $row['template_key'])->address(),
                'field'  => 'body',
                'text'   => (string) $row['body'],
            ];
        }

        return $items;
    }

    /** @return list<array{target: string, field: string, text: string, context?: string}> */
    private function breedImagesWithoutAlt(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT p.ID, p.post_title, t.post_title AS breed
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} m ON m.meta_value = p.ID AND m.meta_key = '_thumbnail_id'
             INNER JOIN {$wpdb->posts} t ON t.ID = m.post_id
             LEFT JOIN {$wpdb->postmeta} alt
                 ON alt.post_id = p.ID AND alt.meta_key = '_wp_attachment_image_alt'
             WHERE p.post_type = 'attachment'
               AND (alt.meta_value IS NULL OR alt.meta_value = '')
             LIMIT 40",
            ARRAY_A
        );

        $items = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $items[] = [
                'target'  => CopyTarget::of(CopyTarget::MEDIA, (string) $row['ID'])->address(),
                'field'   => 'alt',
                'text'    => '',
                'context' => 'Featured image of the breed article: ' . (string) $row['breed'],
            ];
        }

        return $items;
    }

    /** @return array<string,array<string,string>> */
    private function traitScales(): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $rows = $wpdb->get_results(
            "SELECT trait_key, label, scale_low, scale_high FROM {$table} ORDER BY sort_order, trait_key",
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(string) $row['trait_key']] = [
                'label'      => (string) $row['label'],
                'scale_low'  => (string) $row['scale_low'],
                'scale_high' => (string) $row['scale_high'],
            ];
        }

        return $out;
    }

    /** @return array<string,array{name: string, values: array<string,float>}> */
    private function matrix(): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['breed_traits'];

        $values = $wpdb->get_results("SELECT breed_id, trait_key, value FROM {$table}", ARRAY_A);

        $byPost = [];
        foreach (is_array($values) ? $values : [] as $row) {
            $byPost[(int) $row['breed_id']][(string) $row['trait_key']] = (float) $row['value'];
        }

        $out = [];
        foreach ($this->breeds->posts() as $post) {
            $id = (int) $post['ID'];

            $out[(string) $post['post_name']] = [
                'name'   => (string) ($post['sheet_name'] !== '' ? $post['sheet_name'] : $post['post_title']),
                'values' => $byPost[$id] ?? [],
            ];
        }

        return $out;
    }

    /**
     * @param  list<string> $warnings
     * @param  list<string> $failures
     * @return array{added: int, warnings: list<string>, failures: list<string>, calls: int}
     */
    private function result(int $added, array $warnings, array $failures, int $calls): array
    {
        return ['added' => $added, 'warnings' => $warnings, 'failures' => $failures, 'calls' => $calls];
    }
}
