<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\TraitCostCalculator;
use Skilitsa\Core\Engine\Value\TraitDefinition;

final class TraitCostCalculatorTest extends TestCase
{
    private TraitCostCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new TraitCostCalculator();
    }

    public function test_perfect_match_costs_nothing(): void
    {
        $trait = new TraitDefinition('shedding', 1.0, 1.0);
        self::assertSame(0.0, $this->calc->rawCost($trait, 3.0, 3.0));
    }

    public function test_symmetric_trait_costs_the_same_in_both_directions(): void
    {
        $trait = new TraitDefinition('size', 1.0, 1.0);

        self::assertSame(2.0, $this->calc->rawCost($trait, 2.0, 4.0));
        self::assertSame(2.0, $this->calc->rawCost($trait, 4.0, 2.0));
    }

    public function test_excess_costly_trait_charges_only_when_breed_exceeds_user(): void
    {
        // shedding (1.0, 0.0): a breed that sheds MORE than you tolerate is a
        // daily problem; one that sheds less is free.
        $shedding = new TraitDefinition('shedding', penaltyOver: 1.0, penaltyUnder: 0.0);

        self::assertSame(3.0, $this->calc->rawCost($shedding, 2.0, 5.0), 'breed sheds more');
        self::assertSame(0.0, $this->calc->rawCost($shedding, 5.0, 2.0), 'breed sheds less');
    }

    public function test_shortfall_costly_trait_charges_only_when_breed_falls_short(): void
    {
        // kid_friendly (0.0, 1.0): you need a kid-safe dog and the breed is not.
        // The reverse - a great-with-kids breed for someone without kids - is free.
        $kids = new TraitDefinition('kid_friendly', penaltyOver: 0.0, penaltyUnder: 1.0);

        self::assertSame(3.0, $this->calc->rawCost($kids, 5.0, 2.0), 'breed falls short');
        self::assertSame(0.0, $this->calc->rawCost($kids, 2.0, 5.0), 'breed exceeds need');
    }

    public function test_weighted_asymmetry_charges_both_sides_unequally(): void
    {
        // energy_outdoor (1.5, 0.5): a breed with MORE energy than the owner can
        // give suffers and develops problems - the welfare cost sits on that side.
        $energy = new TraitDefinition('energy_outdoor', penaltyOver: 1.5, penaltyUnder: 0.5);

        self::assertSame(4.5, $this->calc->rawCost($energy, 2.0, 5.0), 'dog needs more than owner gives');
        self::assertSame(1.5, $this->calc->rawCost($energy, 5.0, 2.0), 'owner wanted more than dog gives');
    }

    public function test_normalised_cost_is_measured_against_this_user_s_worst_case(): void
    {
        $energy = new TraitDefinition('energy_outdoor', 1.5, 0.5);

        // User wants 1. Worst available: a breed at 5, costing 4 x 1.5 = 6.
        self::assertSame(1.0, $this->calc->normalisedCost($energy, 1.0, 5.0));
        self::assertSame(0.0, $this->calc->normalisedCost($energy, 3.0, 3.0));

        // User wants 2. Worst available is still a breed at 5, now costing
        // 3 x 1.5 = 4.5 - so a breed at 5 is a FULL mismatch for them, not
        // 0.75 of one. Normalising against the global worst case understated
        // this and dragged every score upward.
        self::assertSame(1.0, $this->calc->normalisedCost($energy, 2.0, 5.0));
    }

    public function test_max_cost_for_user_depends_on_the_answer_given(): void
    {
        $shedding = new TraitDefinition('shedding', penaltyOver: 1.0, penaltyUnder: 0.0);

        // Tolerates none: a breed at 5 costs the full 4.
        self::assertSame(4.0, $this->calc->maxCostForUser($shedding, 1.0));
        // Tolerates everything: nothing can cost them anything.
        self::assertSame(0.0, $this->calc->maxCostForUser($shedding, 5.0));
    }

    public function test_discriminates_reports_whether_a_trait_can_vary_for_this_user(): void
    {
        $shedding = new TraitDefinition('shedding', penaltyOver: 1.0, penaltyUnder: 0.0);
        $kids     = new TraitDefinition('kid_friendly', penaltyOver: 0.0, penaltyUnder: 1.0);
        $size     = new TraitDefinition('size', 1.0, 1.0);

        self::assertTrue($this->calc->discriminates($shedding, 1.0));
        self::assertFalse($this->calc->discriminates($shedding, 5.0), 'tolerates any shedding');

        self::assertTrue($this->calc->discriminates($kids, 5.0));
        self::assertFalse($this->calc->discriminates($kids, 1.0), 'needs no kid-friendliness');

        // A symmetric trait discriminates everywhere except nowhere: even at
        // the extremes one direction is still available.
        self::assertTrue($this->calc->discriminates($size, 1.0));
        self::assertTrue($this->calc->discriminates($size, 3.0));
        self::assertTrue($this->calc->discriminates($size, 5.0));
    }

    public function test_inert_trait_normalises_to_zero_without_dividing_by_zero(): void
    {
        $inert = new TraitDefinition('inert', 0.0, 0.0);
        self::assertSame(0.0, $this->calc->normalisedCost($inert, 1.0, 5.0));
        self::assertFalse($this->calc->discriminates($inert, 3.0));
    }

    public function test_direction_reports_which_side_the_breed_is_on(): void
    {
        self::assertSame(TraitCostCalculator::BREED_HIGHER, $this->calc->direction(2.0, 5.0));
        self::assertSame(TraitCostCalculator::BREED_LOWER,  $this->calc->direction(5.0, 2.0));
        self::assertSame(TraitCostCalculator::LEVEL,        $this->calc->direction(3.0, 3.0));
    }

    public function test_normalised_cost_never_exceeds_one_even_with_odd_inputs(): void
    {
        $trait = new TraitDefinition('t', 1.0, 1.0);
        self::assertLessThanOrEqual(1.0, $this->calc->normalisedCost($trait, 1.0, 5.0));
    }
}
