<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Wpdb;

use Skilitsa\Core\Data\Schema;
use Skilitsa\Core\Import\ImportExecutor;
use Skilitsa\Core\WordPress\BreedPostType;

/**
 * Reads and writes what the breed admin screens need.
 *
 * A shim: it moves rows, it decides nothing.
 */
final class BreedAdminRepository
{
    /** @return list<array<string,mixed>> ID, post_title, post_name, sheet_name */
    public function posts(): array
    {
        global $wpdb;

        $meta = ImportExecutor::DISPLAY_NAME_META;
        $type = BreedPostType::POST_TYPE;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID, p.post_title, p.post_name, COALESCE(m.meta_value, '') AS sheet_name
                 FROM {$wpdb->posts} p
                 LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
                 WHERE p.post_type = %s AND p.post_status = 'publish'
                 ORDER BY p.post_title",
                $meta,
                $type
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /** @return array<int,list<string>> Trait keys that carry a value, per post ID. */
    public function valuesByPost(): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['breed_traits'];

        $rows = $wpdb->get_results("SELECT breed_id, trait_key FROM {$table}", ARRAY_A);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(int) $row['breed_id']][] = (string) $row['trait_key'];
        }

        return $out;
    }

    /** @return array<string,string> trait_key => Greek label, in the owner's reading order. */
    public function traitLabels(): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['trait_definitions'];

        $rows = $wpdb->get_results(
            "SELECT trait_key, label FROM {$table} ORDER BY sort_order, trait_key",
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(string) $row['trait_key']] = (string) $row['label'];
        }

        return $out;
    }

    /** @return array<string,float> trait_key => value, for one breed. */
    public function valuesFor(int $postId): array
    {
        global $wpdb;

        $table = Schema::tableNames($wpdb->prefix)['breed_traits'];

        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT trait_key, value FROM {$table} WHERE breed_id = %d", $postId),
            ARRAY_A
        );

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $out[(string) $row['trait_key']] = (float) $row['value'];
        }

        return $out;
    }

    /**
     * @param  array<string,float> $values
     * @return int Rows written.
     */
    public function saveValues(int $postId, array $values): int
    {
        global $wpdb;

        $table   = Schema::tableNames($wpdb->prefix)['breed_traits'];
        $written = 0;

        foreach ($values as $traitKey => $value) {
            $wpdb->query(
                $wpdb->prepare(
                    "INSERT INTO {$table} (breed_id, trait_key, value) VALUES (%d, %s, %f)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    $postId,
                    $traitKey,
                    $value
                )
            );
            $written++;
        }

        return $written;
    }
}
