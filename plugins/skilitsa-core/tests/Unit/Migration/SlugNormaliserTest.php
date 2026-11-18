<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Migration\SlugNormaliser;

final class SlugNormaliserTest extends TestCase
{
    private SlugNormaliser $normaliser;

    protected function setUp(): void
    {
        $this->normaliser = new SlugNormaliser();
    }

    public function test_replaces_underscores_with_hyphens(): void
    {
        self::assertSame('border-collie', $this->normaliser->normalise('border_collie'));
        self::assertSame('rabbit-wire-haired-dachshund', $this->normaliser->normalise('rabbit_wire_haired_dachshund'));
    }

    public function test_leaves_a_correct_slug_alone(): void
    {
        self::assertSame('belgian-malinois', $this->normaliser->normalise('belgian-malinois'));
        self::assertFalse($this->normaliser->needsChange('belgian-malinois'));
    }

    public function test_detects_which_slugs_need_changing(): void
    {
        self::assertTrue($this->normaliser->needsChange('border_collie'));
        self::assertFalse($this->normaliser->needsChange('greyhound'));
    }

    public function test_collapses_repeated_separators(): void
    {
        self::assertSame('a-b', $this->normaliser->normalise('a__b'));
        self::assertSame('a-b', $this->normaliser->normalise('a-_-b'));
    }

    public function test_trims_leading_and_trailing_separators(): void
    {
        self::assertSame('collie', $this->normaliser->normalise('_collie_'));
    }

    public function test_leaves_percent_encoded_greek_slugs_alone(): void
    {
        // Some posts have URL-encoded Greek slugs. Rewriting those would break
        // URLs for no SEO gain, so they are out of scope.
        $greek = '%ce%bc%ce%b1%ce%be%af%ce%bd';

        self::assertSame($greek, $this->normaliser->normalise($greek));
        self::assertFalse($this->normaliser->needsChange($greek));
    }

    public function test_a_mangled_ascii_plus_greek_slug_is_repaired(): void
    {
        $mangled = 'italian-greyhound%ce%b9%cf%84%ce%b1';

        self::assertSame('italian-greyhound', $this->normaliser->normalise($mangled));
        self::assertTrue($this->normaliser->needsChange($mangled));
    }

    public function test_plan_maps_old_slug_to_new(): void
    {
        $plan = $this->normaliser->planFor(['border_collie', 'belgian-malinois', 'rough_collie']);

        self::assertSame(
            ['border_collie' => 'border-collie', 'rough_collie' => 'rough-collie'],
            $plan['changes']
        );
        self::assertSame([], $plan['collisions']);
    }

    public function test_plan_detects_a_collision_rather_than_resolving_it(): void
    {
        // If both exist, renaming the first onto the second would silently
        // destroy a URL. Surface it and let a human decide.
        $plan = $this->normaliser->planFor(['border_collie', 'border-collie']);

        self::assertSame([], $plan['changes']);
        self::assertCount(1, $plan['collisions']);
        self::assertStringContainsString('border-collie', $plan['collisions'][0]);
    }

    public function test_plan_detects_two_sources_normalising_to_the_same_target(): void
    {
        $plan = $this->normaliser->planFor(['a_b', 'a-b_']);

        self::assertSame([], $plan['changes']);
        self::assertCount(1, $plan['collisions']);
    }

    public function test_plan_is_empty_when_nothing_needs_changing(): void
    {
        $plan = $this->normaliser->planFor(['greyhound', 'belgian-malinois']);

        self::assertSame([], $plan['changes']);
        self::assertSame([], $plan['collisions']);
    }

    public function test_the_same_slug_twice_is_a_duplicate_not_a_collision(): void
    {
        // Regression: a repeated source slug was treated as two sources
        // colliding, which unset the already-planned rename and left the post
        // with its underscore slug.
        $plan = $this->normaliser->planFor(['border_collie', 'border_collie']);

        self::assertSame(['border_collie' => 'border-collie'], $plan['changes']);
        self::assertSame([], $plan['collisions']);
    }
}
