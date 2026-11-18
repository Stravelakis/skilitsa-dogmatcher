<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\TraitDefaults;

/**
 * The asymmetry defaults are a judgement, not an algorithm, so what is tested
 * here is that the judgement is complete, in range, and still says what it was
 * reviewed as saying. A silent edit to one of these numbers changes every
 * result the site produces.
 */
final class TraitDefaultsTest extends TestCase
{
    public function test_every_one_of_the_24_traits_has_a_default(): void
    {
        self::assertCount(24, TraitDefaults::PENALTIES);
    }

    public function test_every_default_is_in_range_and_carries_a_rationale(): void
    {
        foreach (TraitDefaults::PENALTIES as $key => $row) {
            self::assertGreaterThanOrEqual(0.0, $row['over'], $key);
            self::assertGreaterThanOrEqual(0.0, $row['under'], $key);
            self::assertLessThanOrEqual(1.5, $row['over'], $key);
            self::assertLessThanOrEqual(1.5, $row['under'], $key);
            self::assertNotSame('', trim($row['note']), $key);
        }
    }

    public function test_no_trait_is_free_in_both_directions(): void
    {
        // A trait that costs nothing either way is dead weight in the average
        // and would quietly dilute every score.
        foreach (TraitDefaults::PENALTIES as $key => $row) {
            self::assertGreaterThan(0.0, $row['over'] + $row['under'], $key);
        }
    }

    /**
     * @return list<array{0: string, 1: float, 2: float}>
     */
    public static function reviewedTraits(): array
    {
        return [
            // Welfare and safety: the severe side is 1.5, the other is free.
            ['handler_experience_needed', 1.5, 0.0],
            ['kid_friendly', 0.0, 1.5],
            ['heat_tolerance', 0.0, 1.5],
            ['separation_tolerance', 0.25, 1.5],
            ['energy_outdoor', 1.5, 0.5],
            ['barking', 1.5, 0.0],
            // Pure inconvenience: costly one way, free the other.
            ['shedding', 1.0, 0.0],
            ['grooming', 1.0, 0.0],
        ];
    }

    /** @dataProvider reviewedTraits */
    public function test_reviewed_values_have_not_drifted(string $key, float $over, float $under): void
    {
        self::assertSame(['over' => $over, 'under' => $under], TraitDefaults::forTrait($key));
    }

    public function test_an_unknown_trait_falls_back_to_symmetric(): void
    {
        self::assertSame(['over' => 1.0, 'under' => 1.0], TraitDefaults::forTrait('not_a_trait'));
        self::assertSame('', TraitDefaults::noteFor('not_a_trait'));
    }
}
