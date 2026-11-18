<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Draft;

use Skilitsa\Core\Data\Schema;

/**
 * Where drafts live. A shim: it moves rows and decides nothing.
 *
 * The unique key on `fingerprint` is what stops a weekly matrix QA run from
 * building a pile of identical pending rows. A repeat suggestion refreshes the
 * existing row rather than adding one, and a suggestion already judged stays
 * judged - re-proposing something the owner rejected does not silently
 * resurrect it as pending.
 */
final class DraftStore
{
    /**
     * @param  list<Draft> $drafts
     * @return int How many were new.
     */
    public function addAll(array $drafts): int
    {
        global $wpdb;

        $table = $this->table();
        $added = 0;

        foreach ($drafts as $draft) {
            $existing = $wpdb->get_var(
                $wpdb->prepare("SELECT id FROM {$table} WHERE fingerprint = %s", $draft->fingerprint())
            );

            if ($existing !== null) {
                continue;
            }

            $wpdb->insert($table, [
                'task'            => $draft->task,
                'target_type'     => $draft->targetType,
                'target_key'      => $draft->targetKey,
                'field'           => $draft->field,
                'current_value'   => $draft->currentValue,
                'suggested_value' => $draft->suggestedValue,
                'model'           => $draft->model,
                'note'            => $draft->note,
                'fingerprint'     => $draft->fingerprint(),
                'status'          => $draft->status,
                'created_at'      => $draft->createdAt,
            ]);

            $added++;
        }

        return $added;
    }

    /** @return list<Draft> */
    public function pending(?string $task = null): array
    {
        global $wpdb;

        $table = $this->table();

        $rows = $task === null
            ? $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY id", Draft::PENDING),
                ARRAY_A
            )
            : $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status = %s AND task = %s ORDER BY id",
                    Draft::PENDING,
                    $task
                ),
                ARRAY_A
            );

        return array_map(
            static fn (array $row) => Draft::fromStorage($row),
            is_array($rows) ? $rows : []
        );
    }

    public function find(int $id): ?Draft
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$this->table()} WHERE id = %d", $id),
            ARRAY_A
        );

        return is_array($row) ? Draft::fromStorage($row) : null;
    }

    /** Persists a judged draft. The transition itself happened on the Draft. */
    public function saveJudgement(Draft $draft): void
    {
        global $wpdb;

        if ($draft->id === null) {
            return;
        }

        $wpdb->update(
            $this->table(),
            ['status' => $draft->status, 'reviewed_at' => $draft->reviewedAt],
            ['id' => $draft->id],
            ['%s', '%s'],
            ['%d']
        );
    }

    /** @return array<string,int> status => count */
    public function counts(): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT status, COUNT(*) AS n FROM {$this->table()} GROUP BY status",
            ARRAY_A
        );

        $out = [Draft::PENDING => 0, Draft::APPROVED => 0, Draft::REJECTED => 0];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(string) $row['status']] = (int) $row['n'];
        }

        return $out;
    }

    private function table(): string
    {
        global $wpdb;

        return Schema::tableNames($wpdb->prefix)['ai_drafts'];
    }
}
