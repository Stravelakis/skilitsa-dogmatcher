<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine\Value;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;

final class ValueObjectTest extends TestCase
{
    public function test_trait_definition_max_raw_cost_uses_larger_multiplier(): void
    {
        // Distance can be at most 4 (user 1 vs breed 5). Max cost is 4 x the
        // larger of the two multipliers.
        $t = new TraitDefinition('shedding', penaltyOver: 1.0, penaltyUnder: 0.0);
        self::assertSame(4.0, $t->maxRawCost());

        $t2 = new TraitDefinition('energy_outdoor', penaltyOver: 1.5, penaltyUnder: 0.5);
        self::assertSame(6.0, $t2->maxRawCost());
    }

    public function test_trait_definition_rejects_negative_multipliers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TraitDefinition('bad', penaltyOver: -1.0, penaltyUnder: 1.0);
    }

    public function test_trait_with_both_multipliers_zero_has_no_max_cost(): void
    {
        // Legal but inert: the trait can never contribute. maxRawCost() of 0.0
        // is the signal to skip it rather than divide by zero.
        $t = new TraitDefinition('inert', penaltyOver: 0.0, penaltyUnder: 0.0);
        self::assertSame(0.0, $t->maxRawCost());
    }

    public function test_question_trait_link_direction(): void
    {
        $same = new QuestionTraitLink('Q01', 'energy_outdoor', QuestionTraitLink::SAME, 1.0);
        $inv  = new QuestionTraitLink('Q13', 'barking', QuestionTraitLink::INVERSE, 0.6);

        self::assertFalse($same->isInverse());
        self::assertTrue($inv->isInverse());
        self::assertSame(0.6, $inv->weight);
    }

    public function test_question_trait_link_rejects_unknown_direction(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new QuestionTraitLink('Q01', 'energy_outdoor', 'sideways', 1.0);
    }

    public function test_breed_returns_null_for_absent_trait(): void
    {
        $b = new Breed('belgian_malinois', ['energy_outdoor' => 5.0, 'shedding' => 3.0]);

        self::assertSame(5.0, $b->traitValue('energy_outdoor'));
        self::assertNull($b->traitValue('grooming'));
    }

    public function test_breed_ignores_non_finite_values(): void
    {
        // A non-numeric cell in the source data must not poison scoring.
        $b = new Breed('broken', ['energy_outdoor' => NAN, 'shedding' => 3.0]);

        self::assertNull($b->traitValue('energy_outdoor'));
        self::assertSame(3.0, $b->traitValue('shedding'));
    }

    public function test_breed_keeps_legitimate_low_values(): void
    {
        // v1 used falsy checks and silently dropped these.
        $b = new Breed('quiet', ['barking' => 1.0]);
        self::assertSame(1.0, $b->traitValue('barking'));
    }

    public function test_question_defaults_to_weight_one(): void
    {
        self::assertSame(1.0, (new Question('Q01'))->weight);
    }

    public function test_scoring_config_defaults_are_sane(): void
    {
        $c = ScoringConfig::defaults();

        self::assertGreaterThan(0.0, $c->gamma);
        self::assertGreaterThan(0.0, $c->shrinkageM);
        self::assertGreaterThanOrEqual(1.0, $c->salienceMultiplier);
        self::assertLessThan($c->neutralMax, $c->alignedMax);
        self::assertLessThan($c->pitfallMax, $c->neutralMax);
    }

    public function test_scoring_config_rejects_a_zero_dealbreaker_threshold(): void
    {
        // At zero, rawCost >= threshold is true even for a perfect match, so
        // every salient trait counts as violated and every breed is excluded.
        $this->expectException(InvalidArgumentException::class);
        new ScoringConfig(dealbreakerThreshold: 0.0);
    }

    public function test_scoring_config_rejects_a_negative_close_call_window(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScoringConfig(closeCallPoints: -1);
    }

    public function test_scoring_config_rejects_a_zero_evidence_floor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ScoringConfig(minTraitsForResults: 0);
    }

    public function test_direction_accepts_the_spelling_the_sheet_actually_uses(): void
    {
        // The real MAPPING tab writes "invert", not "inverse". Four traits -
        // barking, prey_drive_reactivity, leash_difficulty, heat_tolerance -
        // would otherwise have been scored in the wrong direction.
        self::assertSame(QuestionTraitLink::INVERSE, QuestionTraitLink::normaliseDirection('invert'));
        self::assertSame(QuestionTraitLink::INVERSE, QuestionTraitLink::normaliseDirection('inverse'));
        self::assertSame(QuestionTraitLink::INVERSE, QuestionTraitLink::normaliseDirection('  INVERTED '));
        self::assertSame(QuestionTraitLink::INVERSE, QuestionTraitLink::normaliseDirection('reverse'));
    }

    public function test_direction_treats_blank_and_synonyms_of_same_as_same(): void
    {
        self::assertSame(QuestionTraitLink::SAME, QuestionTraitLink::normaliseDirection(''));
        self::assertSame(QuestionTraitLink::SAME, QuestionTraitLink::normaliseDirection('same'));
        self::assertSame(QuestionTraitLink::SAME, QuestionTraitLink::normaliseDirection('Direct'));
    }

    public function test_direction_returns_null_for_genuine_nonsense(): void
    {
        self::assertNull(QuestionTraitLink::normaliseDirection('sideways'));
    }
}
