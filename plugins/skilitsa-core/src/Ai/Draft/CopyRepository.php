<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Draft;

use Skilitsa\Core\Data\Schema;

/**
 * Reads and writes the copy an AI task may propose changes to.
 *
 * A shim, and a narrow one on purpose. Every write goes through CopyTarget's
 * whitelist first, so this class can only ever touch four tables and a fixed
 * set of columns in each - it cannot be talked into writing anywhere else by a
 * cleverly addressed draft.
 *
 * Everything here except media is overwritten by the next sheet import. That is
 * correct: the sheet is the source of truth for copy, and an approved
 * translation is a suggestion for the sheet, not a replacement for it. The
 * review screen says so.
 */
final class CopyRepository
{
    /** @return string|null The text as it stands, or null when the row is gone. */
    public function read(CopyTarget $target, string $field): ?string
    {
        global $wpdb;

        if (!$target->allows($field)) {
            return null;
        }

        if ($target->kind === CopyTarget::MEDIA) {
            $value = get_post_meta((int) $target->key, '_wp_attachment_image_alt', true);

            return is_string($value) ? $value : '';
        }

        [$table, $keyColumn] = $this->tableFor($target->kind);

        $value = $wpdb->get_var(
            $wpdb->prepare("SELECT {$field} FROM {$table} WHERE {$keyColumn} = %s", $target->key)
        );

        return $value === null ? null : (string) $value;
    }

    /** @return bool Whether anything was written. */
    public function write(CopyTarget $target, string $field, string $text): bool
    {
        global $wpdb;

        if (!$target->allows($field)) {
            return false;
        }

        if ($target->kind === CopyTarget::MEDIA) {
            return (bool) update_post_meta((int) $target->key, '_wp_attachment_image_alt', $text);
        }

        [$table, $keyColumn] = $this->tableFor($target->kind);

        $updated = $wpdb->update($table, [$field => $text], [$keyColumn => $target->key], ['%s'], ['%s']);

        return $updated !== false;
    }

    /** @return array{0: string, 1: string} table, key column */
    private function tableFor(string $kind): array
    {
        global $wpdb;

        $tables = Schema::tableNames($wpdb->prefix);

        return match ($kind) {
            CopyTarget::TEMPLATE => [$tables['explanation_templates'], 'template_key'],
            CopyTarget::TRAIT    => [$tables['trait_definitions'], 'trait_key'],
            default              => [$tables['questions'], 'question_key'],
        };
    }
}
