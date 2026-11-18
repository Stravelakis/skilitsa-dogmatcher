<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Wpdb;

use Skilitsa\Core\Data\Schema;

/**
 * Reads and writes the tunable columns of trait_definitions.
 *
 * A shim: it moves rows, it decides nothing. What counts as tuned is decided in
 * TraitMatrixForm.
 */
final class TraitTuningRepository
{
    /** @return list<array<string,mixed>> In the owner's reading order. */
    public function all(): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $rows = $wpdb->get_results(
            "SELECT trait_key, label, penalty_over, penalty_under, global_weight, is_tuned
             FROM {$table}
             ORDER BY sort_order, trait_key",
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param  array<string,array{over: float, under: float, weight: float, is_tuned: bool}> $rows
     * @return int How many rows changed.
     */
    public function save(array $rows): int
    {
        global $wpdb;

        $table   = Schema::tableNames($wpdb->prefix)['trait_definitions'];
        $changed = 0;

        foreach ($rows as $key => $row) {
            $result = $wpdb->update(
                $table,
                [
                    'penalty_over'  => $row['over'],
                    'penalty_under' => $row['under'],
                    'global_weight' => $row['weight'],
                    'is_tuned'      => $row['is_tuned'] ? 1 : 0,
                ],
                ['trait_key' => $key],
                ['%f', '%f', '%f', '%d'],
                ['%s']
            );

            if ($result) {
                $changed++;
            }
        }

        return $changed;
    }
}
