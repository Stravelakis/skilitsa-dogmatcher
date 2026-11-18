<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\MatchComparator;
use Skilitsa\Core\Engine\TraitCostCalculator;
use Skilitsa\Core\Engine\Value\BreedMatch;
use Skilitsa\Core\Engine\Value\TraitVerdict;

final class MatchComparatorTest extends TestCase
{
    private MatchComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new MatchComparator();
    }

    /** @param array<string,float> $costs traitKey => rawCost */
    private function match(string $key, array $costs): BreedMatch
    {
        $verdicts = [];
        foreach ($costs as $traitKey => $cost) {
            $verdicts[] = new TraitVerdict(
                (string) $traitKey,
                TraitVerdict::NEUTRAL,
                3.0,
                3.0,
                $cost,
                TraitCostCalculator::LEVEL,
            );
        }

        return new BreedMatch($key, 80, 0.2, count($costs), $verdicts);
    }

    public function test_reports_the_traits_where_the_winner_is_ahead(): void
    {
        $winner = $this->match('gold',   ['energy' => 0.0, 'shedding' => 1.0, 'size' => 2.0]);
        $loser  = $this->match('silver', ['energy' => 3.0, 'shedding' => 1.5, 'size' => 2.0]);

        $diffs = $this->comparator->differentiators($winner, $loser);

        self::assertSame('energy', $diffs[0]['traitKey']);
        self::assertSame(3.0, $diffs[0]['advantage']);
        self::assertSame('shedding', $diffs[1]['traitKey']);
    }

    public function test_ignores_traits_where_the_winner_is_not_ahead(): void
    {
        $winner = $this->match('gold',   ['energy' => 2.0, 'shedding' => 0.0]);
        $loser  = $this->match('silver', ['energy' => 1.0, 'shedding' => 3.0]);

        $diffs = $this->comparator->differentiators($winner, $loser);

        self::assertCount(1, $diffs);
        self::assertSame('shedding', $diffs[0]['traitKey']);
    }

    public function test_ignores_traits_absent_from_either_side(): void
    {
        $winner = $this->match('gold',   ['energy' => 0.0, 'size' => 0.0]);
        $loser  = $this->match('silver', ['energy' => 2.0]);

        $diffs = $this->comparator->differentiators($winner, $loser);

        self::assertSame(['energy'], array_column($diffs, 'traitKey'));
    }

    public function test_respects_the_limit(): void
    {
        $winner = $this->match('gold',   ['a' => 0.0, 'b' => 0.0, 'c' => 0.0, 'd' => 0.0]);
        $loser  = $this->match('silver', ['a' => 4.0, 'b' => 3.0, 'c' => 2.0, 'd' => 1.0]);

        self::assertCount(2, $this->comparator->differentiators($winner, $loser, 2));
    }

    public function test_equal_matches_have_no_differentiators(): void
    {
        $winner = $this->match('gold',   ['energy' => 1.0]);
        $loser  = $this->match('silver', ['energy' => 1.0]);

        self::assertSame([], $this->comparator->differentiators($winner, $loser));
    }

    public function test_ties_in_advantage_break_on_trait_key(): void
    {
        $winner = $this->match('gold',   ['zulu' => 0.0, 'alpha' => 0.0]);
        $loser  = $this->match('silver', ['zulu' => 2.0, 'alpha' => 2.0]);

        $diffs = $this->comparator->differentiators($winner, $loser);

        self::assertSame(['alpha', 'zulu'], array_column($diffs, 'traitKey'));
    }
}
