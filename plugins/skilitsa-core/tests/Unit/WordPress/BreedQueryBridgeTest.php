<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\WordPress;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\WordPress\BreedQueryBridge;

/**
 * When breeds answer to a category query, and when they keep quiet.
 *
 * The decision is pure so it can be tested without WordPress, which matters
 * here: this hook runs on every front-end query on the site, and getting it
 * wrong is not a broken page, it is a subtly wrong one.
 */
final class BreedQueryBridgeTest extends TestCase
{
    private function decide(
        bool $hasCategory = true,
        mixed $postType = '',
        bool $isBlogFeed = false,
        bool $includeInFeed = false,
    ): bool {
        return BreedQueryBridge::shouldInclude($hasCategory, $postType, $isBlogFeed, $includeInFeed);
    }

    public function test_a_category_query_with_no_post_type_gets_breeds(): void
    {
        // The theme's builder, a category archive, a related-posts widget.
        self::assertTrue($this->decide());
    }

    public function test_a_query_for_posts_in_a_category_gets_breeds(): void
    {
        self::assertTrue($this->decide(postType: 'post'));
        self::assertTrue($this->decide(postType: ['post']));
    }

    public function test_a_query_with_no_category_is_left_alone(): void
    {
        // Nothing about it suggests breeds were wanted.
        self::assertFalse($this->decide(hasCategory: false));
    }

    public function test_a_query_that_named_its_own_post_type_is_left_alone(): void
    {
        // Someone who wrote post_type => 'page' meant it.
        self::assertFalse($this->decide(postType: 'page'));
        self::assertFalse($this->decide(postType: ['page', 'post']));
        self::assertFalse($this->decide(postType: 'skilitsa_breed'));
    }

    public function test_any_counts_as_a_choice(): void
    {
        // 'any' already covers every public type; narrowing to two would
        // remove results rather than add them.
        self::assertFalse($this->decide(postType: 'any'));
    }

    public function test_the_blog_feed_stays_articles(): void
    {
        // 257 breeds ahead of the articles is not what anyone means by a front
        // page.
        self::assertFalse($this->decide(isBlogFeed: true));
    }

    public function test_the_blog_feed_can_be_opened_up_on_purpose(): void
    {
        self::assertTrue($this->decide(isBlogFeed: true, includeInFeed: true));
    }

    public function test_an_empty_array_post_type_counts_as_unset(): void
    {
        self::assertTrue($this->decide(postType: []));
    }

    public function test_a_null_post_type_counts_as_unset(): void
    {
        self::assertTrue($this->decide(postType: null));
    }

    public function test_duplicates_of_post_still_count_as_default(): void
    {
        self::assertTrue($this->decide(postType: ['post', 'post']));
    }
}
