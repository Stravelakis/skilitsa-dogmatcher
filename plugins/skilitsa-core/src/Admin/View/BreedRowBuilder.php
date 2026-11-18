<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

use Skilitsa\Core\Data\BreedKey;

/**
 * Turns breed posts and their trait values into list rows. Pure.
 *
 * The reason this screen exists is the incomplete rows: two breeds carried zero
 * trait values for a whole session because nothing ever said so out loud.
 */
final class BreedRowBuilder
{
    /**
     * @param  list<array<string,mixed>>   $posts        ID, post_title, post_name, sheet_name
     * @param  array<int,list<string>>     $valuesByPost Trait keys that have a value, per post ID
     * @param  list<string>                $traitKeys    Every trait the matrix defines
     * @return list<BreedRow>
     */
    public static function build(array $posts, array $valuesByPost, array $traitKeys, string $search = ''): array
    {
        $needle = trim($search);
        $rows   = [];

        foreach ($posts as $post) {
            $postId  = (int) ($post['ID'] ?? 0);
            $slug    = (string) ($post['post_name'] ?? '');
            $greek   = (string) ($post['post_title'] ?? '');
            $english = (string) ($post['sheet_name'] ?? '');

            if ($needle !== '' && !self::matches($needle, $slug, $greek, $english)) {
                continue;
            }

            $present = $valuesByPost[$postId] ?? [];
            $missing = array_values(array_diff($traitKeys, $present));

            $rows[] = new BreedRow(
                postId: $postId,
                slug: $slug,
                greekName: $greek,
                englishName: $english,
                valueCount: count($present),
                traitCount: count($traitKeys),
                missingTraits: $missing,
                isComplete: $missing === [],
            );
        }

        return $rows;
    }

    /** @param list<BreedRow> $rows */
    public static function incompleteCount(array $rows): int
    {
        return count(array_filter($rows, static fn (BreedRow $row) => !$row->isComplete));
    }

    private static function matches(string $needle, string $slug, string $greek, string $english): bool
    {
        // Slugs go through BreedKey so a sheet-style border_collie finds the
        // post-style border-collie. Comparing those raw is the mistake that
        // cost v1 its entire matrix.
        if (str_contains(BreedKey::canonical($slug), BreedKey::canonical($needle))) {
            return true;
        }

        $lowerNeedle = self::lower($needle);

        return str_contains(self::lower($greek), $lowerNeedle)
            || str_contains(self::lower($english), $lowerNeedle);
    }

    private static function lower(string $value): string
    {
        // Greek needs mb_strtolower: strtolower leaves Ά and Κ untouched, so a
        // search for "κόλεϊ" would miss a title written in capitals.
        return function_exists('mb_strtolower')
            ? mb_strtolower($value, 'UTF-8')
            : strtolower($value);
    }
}
