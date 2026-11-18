<?php
declare(strict_types=1);

namespace Skilitsa\Core\WordPress;

/**
 * Finds the posts the migration should touch.
 *
 * Driven by an explicit, reviewed list of category term IDs. The analysis query
 * used during planning OR'd category slugs and pulled in the 151
 * "Διάσημα σκυλιά" posts, returning 421 instead of 258. That query must not be
 * reused, which is why the term IDs live here as a named constant rather than
 * being rediscovered each run.
 */
final class BreedPostFinder
{
    /** Breed-group categories on skilitsa.com. Reviewed 2026-08-21. */
    public const BREED_CATEGORY_IDS = [47, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91];

    /** Διάσημα σκυλιά - famous dogs, not breeds. Always excluded. */
    public const EXCLUDED_CATEGORY_IDS = [48];

    /**
     * Posts that are in a breed category but are not themselves a breed.
     * Reviewed by hand against the sheet: 258 posts = 257 breeds + this index
     * article.
     */
    public const EXCLUDED_POST_IDS = [7314];

    /**
     * @param list<int>|null $categoryIds Defaults to the reviewed list.
     * @return list<array<string,mixed>> ID, post_name, post_type, post_title
     */
    public function find(?array $categoryIds = null): array
    {
        global $wpdb;

        $ids = $categoryIds ?? self::BREED_CATEGORY_IDS;
        if ($ids === []) {
            return [];
        }

        $in       = implode(',', array_map('intval', $ids));
        $excluded = implode(',', array_map('intval', self::EXCLUDED_CATEGORY_IDS));

        $rows = $wpdb->get_results(
            "SELECT DISTINCT p.ID, p.post_name, p.post_type, p.post_title
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             WHERE p.post_status = 'publish'
               AND tt.taxonomy = 'category'
               AND tt.term_id IN ({$in})
               AND p.ID NOT IN (
                   SELECT tr2.object_id
                   FROM {$wpdb->term_relationships} tr2
                   INNER JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
                   WHERE tt2.term_id IN ({$excluded})
               )
             ORDER BY p.post_name",
            ARRAY_A
        );

        $excludedPosts = array_fill_keys(self::EXCLUDED_POST_IDS, true);

        $out = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (isset($excludedPosts[(int) $row['ID']])) {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}
