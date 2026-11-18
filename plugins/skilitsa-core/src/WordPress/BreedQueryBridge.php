<?php
declare(strict_types=1);

namespace Skilitsa\Core\WordPress;

use WP_Query;

/**
 * Lets breeds answer to category queries, as they did when they were posts.
 *
 * The migration made breeds their own post type, which is right: it turns "is
 * this a breed?" into a fact rather than a guess about category IDs. But the
 * rest of the site had years of things asking for "posts in category X" -
 * theme builder sections, category archives, related posts, search - and every
 * one of them stopped finding breeds on the day of the migration.
 *
 * Rather than teach each of those about a new post type, this teaches the post
 * type to answer when a category is asked about.
 *
 * Two rules keep it from being a blunt instrument:
 *
 *   1. It only acts when the query is actually filtering by a category. A
 *      query with no category is not looking for breeds.
 *   2. It never touches a query that named its own post type. Someone who
 *      wrote post_type => 'page' meant it.
 *
 * And one deliberate exception: the blog feed. 257 breeds ahead of the
 * articles is not what anyone means by "the front page", so the feed stays
 * articles unless INCLUDE_IN_BLOG_FEED is flipped.
 */
final class BreedQueryBridge
{
    /**
     * Whether the main blog feed lists breeds alongside articles.
     *
     * False on purpose: they would outnumber the articles 257 to a handful and
     * bury everything written this year.
     */
    private const INCLUDE_IN_BLOG_FEED = false;

    public function register(): void
    {
        add_action('pre_get_posts', [$this, 'onPreGetPosts']);
    }

    public function onPreGetPosts(WP_Query $query): void
    {
        // The admin's post list must keep showing exactly what it says it is.
        if (is_admin()) {
            return;
        }

        $isBlogFeed = $query->is_main_query() && $query->is_home();

        $decided = self::shouldInclude(
            hasCategoryFilter: $this->filtersByCategory($query),
            postType: $query->get('post_type'),
            isBlogFeed: $isBlogFeed,
            includeInBlogFeed: self::INCLUDE_IN_BLOG_FEED,
        );

        if (!$decided) {
            return;
        }

        $query->set('post_type', ['post', BreedPostType::POST_TYPE]);
    }

    /**
     * The whole decision, with no WordPress in it.
     *
     * @param mixed $postType Whatever the query carries: '', 'post', an array, or a real type.
     */
    public static function shouldInclude(
        bool $hasCategoryFilter,
        mixed $postType,
        bool $isBlogFeed,
        bool $includeInBlogFeed = self::INCLUDE_IN_BLOG_FEED,
    ): bool {
        if (!$hasCategoryFilter) {
            return false;
        }

        if ($isBlogFeed && !$includeInBlogFeed) {
            return false;
        }

        return self::isDefaultPostType($postType);
    }

    /**
     * True when the query did not choose a post type, or chose only 'post'.
     *
     * `any` counts as a choice: it already includes every public type, and
     * narrowing it to two would be a reduction, not an addition.
     */
    private static function isDefaultPostType(mixed $postType): bool
    {
        if ($postType === '' || $postType === null || $postType === []) {
            return true;
        }

        if (is_string($postType)) {
            return $postType === 'post';
        }

        if (is_array($postType)) {
            return array_values(array_unique(array_map('strval', $postType))) === ['post'];
        }

        return false;
    }

    /** Every way a query can say "in this category". */
    private function filtersByCategory(WP_Query $query): bool
    {
        foreach (['cat', 'category_name', 'category__in', 'category__and'] as $key) {
            $value = $query->get($key);

            if (!empty($value)) {
                return true;
            }
        }

        $taxQuery = $query->get('tax_query');

        if (is_array($taxQuery)) {
            foreach ($taxQuery as $clause) {
                if (is_array($clause) && ($clause['taxonomy'] ?? '') === 'category') {
                    return true;
                }
            }
        }

        return $query->is_category();
    }
}
