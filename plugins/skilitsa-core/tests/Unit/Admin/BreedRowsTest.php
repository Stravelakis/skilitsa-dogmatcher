<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\View\BreedRowBuilder;

/**
 * The breed list, decided without WordPress.
 *
 * The point of this screen is the incomplete rows. Two breeds shipped with zero
 * trait values for a whole session because nothing surfaced them; a list that
 * says "18 of 24" next to a name would have found them in a minute.
 */
final class BreedRowsTest extends TestCase
{
    /** @var list<string> */
    private const TRAITS = ['size', 'barking', 'shedding', 'grooming'];

    /** @return list<array<string,mixed>> */
    private function posts(): array
    {
        return [
            ['ID' => 11, 'post_title' => 'Ιταλικό Λαγωνικό', 'post_name' => 'italian-greyhound', 'sheet_name' => 'Italian Greyhound'],
            ['ID' => 22, 'post_title' => 'Μπόρντερ Κόλεϊ',   'post_name' => 'border-collie',     'sheet_name' => 'Border Collie'],
        ];
    }

    public function test_a_breed_with_every_trait_reads_as_complete(): void
    {
        $rows = BreedRowBuilder::build($this->posts(), [11 => self::TRAITS, 22 => self::TRAITS], self::TRAITS);

        self::assertTrue($rows[0]->isComplete);
        self::assertSame(4, $rows[0]->valueCount);
        self::assertSame([], $rows[0]->missingTraits);
    }

    public function test_a_breed_missing_traits_names_exactly_which(): void
    {
        $rows = BreedRowBuilder::build(
            $this->posts(),
            [11 => ['size', 'barking'], 22 => self::TRAITS],
            self::TRAITS
        );

        self::assertFalse($rows[0]->isComplete);
        self::assertSame(2, $rows[0]->valueCount);
        self::assertSame(['shedding', 'grooming'], $rows[0]->missingTraits);
    }

    public function test_a_breed_with_no_values_at_all_is_still_listed(): void
    {
        // Silently dropping it is exactly how posts 5943 and 6363 stayed hidden.
        $rows = BreedRowBuilder::build($this->posts(), [22 => self::TRAITS], self::TRAITS);

        self::assertSame(0, $rows[0]->valueCount);
        self::assertFalse($rows[0]->isComplete);
    }

    public function test_a_row_carries_both_languages(): void
    {
        $row = BreedRowBuilder::build($this->posts(), [], self::TRAITS)[0];

        self::assertSame('Ιταλικό Λαγωνικό', $row->greekName);
        self::assertSame('Italian Greyhound', $row->englishName);
        self::assertSame('italian-greyhound', $row->slug);
    }

    public function test_a_post_without_a_sheet_name_still_renders(): void
    {
        $posts = [['ID' => 33, 'post_title' => 'Κάτι', 'post_name' => 'something']];

        self::assertSame('', BreedRowBuilder::build($posts, [], self::TRAITS)[0]->englishName);
    }

    public function test_search_matches_the_greek_name(): void
    {
        $rows = BreedRowBuilder::build($this->posts(), [], self::TRAITS, 'κόλεϊ');

        self::assertCount(1, $rows);
        self::assertSame('border-collie', $rows[0]->slug);
    }

    public function test_search_matches_the_english_name_case_insensitively(): void
    {
        $rows = BreedRowBuilder::build($this->posts(), [], self::TRAITS, 'BORDER');

        self::assertCount(1, $rows);
    }

    public function test_search_matches_the_slug_through_breed_key(): void
    {
        // The sheet writes border_collie, the post is border-collie. Comparing
        // them raw is the mistake that cost v1 its whole matrix.
        $rows = BreedRowBuilder::build($this->posts(), [], self::TRAITS, 'border_collie');

        self::assertCount(1, $rows);
    }

    public function test_an_empty_search_returns_everything(): void
    {
        self::assertCount(2, BreedRowBuilder::build($this->posts(), [], self::TRAITS, '   '));
    }

    public function test_incomplete_breeds_are_counted_for_the_summary(): void
    {
        $rows = BreedRowBuilder::build($this->posts(), [11 => ['size']], self::TRAITS);

        self::assertSame(2, BreedRowBuilder::incompleteCount($rows));
    }
}
