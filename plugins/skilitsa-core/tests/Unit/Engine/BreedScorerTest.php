<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\BreedScorer;
use Skilitsa\Core\Engine\TraitCostCalculator;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\TraitVerdict;
use Skilitsa\Core\Engine\Value\UserTraitProfile;
use Skilitsa\Core\Engine\VerdictClassifier;

final class BreedScorerTest extends TestCase
{
    private BreedScorer $scorer;

    protected function setUp(): void
    {
        $costs = new TraitCostCalculator();
        $this->scorer = new BreedScorer($costs, new VerdictClassifier($costs));
    }

    /** @return array<string,TraitDefinition> */
    private function traits(int $count): array
    {
        $traits = [];
        for ($i = 1; $i <= $count; $i++) {
            $traits['t' . $i] = new TraitDefinition('t' . $i, 1.0, 1.0);
        }

        return $traits;
    }

    /** @return array{UserTraitProfile,Breed} */
    private function identical(int $count, float $value = 3.0): array
    {
        $values = [];
        for ($i = 1; $i <= $count; $i++) {
            $values['t' . $i] = $value;
        }

        return [new UserTraitProfile($values), new Breed('b', $values)];
    }

    public function test_perfect_match_with_ample_traits_scores_near_one_hundred(): void
    {
        // 24 symmetric traits, user and breed both at 3, so every trait
        // discriminates (max cost for this user is 2 either way) and every
        // cost is 0. Raw 100, shrunk (24*100 + 1*50)/25 = 98
        [$profile, $breed] = $this->identical(24);

        $match = $this->scorer->score(
            $profile, $breed, $this->traits(24), [], [], ScoringConfig::defaults()
        );

        self::assertSame(24, $match->traitsUsed);
        self::assertSame(98, $match->score);
        self::assertFalse($match->excluded);
    }

    public function test_shrinkage_pulls_thin_results_toward_fifty(): void
    {
        // A perfect match on only 2 traits: (2*100 + 1*50)/3 = 83.3 -> 83
        [$profile, $breed] = $this->identical(2);

        $match = $this->scorer->score(
            $profile, $breed, $this->traits(2), [], [], ScoringConfig::defaults()
        );

        self::assertSame(2, $match->traitsUsed);
        self::assertSame(83, $match->score);
    }

    public function test_thin_perfect_match_cannot_beat_broad_perfect_match(): void
    {
        [$thinProfile, $thinBreed]   = $this->identical(3);
        [$broadProfile, $broadBreed] = $this->identical(24);

        $thin = $this->scorer->score(
            $thinProfile, $thinBreed, $this->traits(3), [], [], ScoringConfig::defaults()
        );
        $broad = $this->scorer->score(
            $broadProfile, $broadBreed, $this->traits(24), [], [], ScoringConfig::defaults()
        );

        self::assertLessThan($broad->score, $thin->score);
    }

    public function test_worst_possible_match_scores_low(): void
    {
        $traits   = $this->traits(24);
        $userVals = $breedVals = [];
        for ($i = 1; $i <= 24; $i++) {
            $userVals['t' . $i]  = 1.0;
            $breedVals['t' . $i] = 5.0;
        }

        $match = $this->scorer->score(
            new UserTraitProfile($userVals),
            new Breed('opposite', $breedVals),
            $traits, [], [], ScoringConfig::defaults()
        );

        // User at 1, breed at 5, symmetric trait: the worst this user could
        // possibly suffer, so normalised cost is 1.0 and the raw score is 0.
        // Shrunk toward 50: (24*0 + 1*50)/25 = 2
        self::assertSame(2, $match->score);
    }

    public function test_adding_more_traits_does_not_shift_a_perfect_score(): void
    {
        // v1 summed the penalty while averaging the base, so adding a trait
        // column to the sheet silently moved every score. Here the only thing
        // that changes with trait count is shrinkage, which moves the broader
        // result UP - never the narrower one down for unrelated reasons.
        [$p12, $b12] = $this->identical(12);
        [$p24, $b24] = $this->identical(24);

        $twelve     = $this->scorer->score($p12, $b12, $this->traits(12), [], [], ScoringConfig::defaults());
        $twentyFour = $this->scorer->score($p24, $b24, $this->traits(24), [], [], ScoringConfig::defaults());

        self::assertGreaterThan($twelve->score, $twentyFour->score);
    }

    public function test_gamma_below_one_is_stricter_and_above_one_is_more_lenient(): void
    {
        // Normalised cost lives in 0..1, so raising it to a HIGHER power makes
        // it SMALLER and the score higher. Lower gamma is the strict direction.
        // This is counter-intuitive and was wrong in the first draft of the
        // spec; this test exists to stop it drifting back.
        $traits = $this->traits(4);
        $user = $breed = [];
        for ($i = 1; $i <= 4; $i++) {
            $user['t' . $i]  = 1.0;
            $breed['t' . $i] = 3.0;
        }

        $strict = $this->scorer->score(
            new UserTraitProfile($user), new Breed('b', $breed), $traits, [], [],
            new ScoringConfig(gamma: 0.5)
        );
        $linear = $this->scorer->score(
            new UserTraitProfile($user), new Breed('b', $breed), $traits, [], [],
            new ScoringConfig(gamma: 1.0)
        );
        $lenient = $this->scorer->score(
            new UserTraitProfile($user), new Breed('b', $breed), $traits, [], [],
            new ScoringConfig(gamma: 2.0)
        );

        self::assertLessThan($linear->score, $strict->score);
        self::assertGreaterThan($linear->score, $lenient->score);
    }

    public function test_higher_gamma_widens_the_gap_between_good_and_bad_matches(): void
    {
        // The useful property of gamma > 1: it spreads the distribution, so a
        // near-miss pulls away from a bad miss even though both scores rise.
        $traits = $this->traits(4);
        $user = $near = $far = [];
        for ($i = 1; $i <= 4; $i++) {
            $user['t' . $i] = 1.0;
            $near['t' . $i] = 2.0;
            $far['t' . $i]  = 5.0;
        }
        $profile = new UserTraitProfile($user);

        $spread = function (float $gamma) use ($profile, $near, $far, $traits): int {
            $config = new ScoringConfig(gamma: $gamma);

            return $this->scorer->score($profile, new Breed('near', $near), $traits, [], [], $config)->score
                 - $this->scorer->score($profile, new Breed('far', $far), $traits, [], [], $config)->score;
        };

        self::assertGreaterThan($spread(1.0), $spread(2.0));
    }

    public function test_salient_traits_weigh_more(): void
    {
        $traits = [
            'good' => new TraitDefinition('good', 1.0, 1.0),
            'bad'  => new TraitDefinition('bad', 1.0, 1.0),
        ];
        $profile = new UserTraitProfile(['good' => 3.0, 'bad' => 1.0]);
        $breed   = new Breed('b', ['good' => 3.0, 'bad' => 5.0]);

        $neutral = $this->scorer->score(
            $profile, $breed, $traits, [], [], ScoringConfig::defaults()
        );
        $badIsSalient = $this->scorer->score(
            $profile, $breed, $traits, ['bad' => true], [], ScoringConfig::defaults()
        );

        self::assertLessThan(
            $neutral->score,
            $badIsSalient->score,
            'weighting the mismatched trait more heavily must lower the score'
        );
    }

    public function test_marking_a_well_matched_trait_salient_raises_the_score(): void
    {
        $traits = [
            'good' => new TraitDefinition('good', 1.0, 1.0),
            'bad'  => new TraitDefinition('bad', 1.0, 1.0),
        ];
        $profile = new UserTraitProfile(['good' => 3.0, 'bad' => 1.0]);
        $breed   = new Breed('b', ['good' => 3.0, 'bad' => 5.0]);

        $neutral = $this->scorer->score(
            $profile, $breed, $traits, [], [], ScoringConfig::defaults()
        );
        $goodIsSalient = $this->scorer->score(
            $profile, $breed, $traits, ['good' => true], [], ScoringConfig::defaults()
        );

        self::assertGreaterThan($neutral->score, $goodIsSalient->score);
    }

    public function test_traits_the_user_cannot_be_mismatched_on_are_skipped(): void
    {
        // shedding is over-costly only. A user who answered 5 tolerates any
        // amount, so no breed in 1..5 can cost them anything. Counting such a
        // trait would drag every score toward 100 and produce a vacuous
        // "great match on shedding!" for someone who does not care.
        $traits = [
            'shedding' => new TraitDefinition('shedding', penaltyOver: 1.0, penaltyUnder: 0.0),
            'size'     => new TraitDefinition('size', 1.0, 1.0),
        ];

        $match = $this->scorer->score(
            new UserTraitProfile(['shedding' => 5.0, 'size' => 3.0]),
            new Breed('b', ['shedding' => 1.0, 'size' => 3.0]),
            $traits, [], [], ScoringConfig::defaults()
        );

        self::assertSame(1, $match->traitsUsed, 'only size should count');
        self::assertCount(1, $match->verdicts);
        self::assertSame('size', $match->verdicts[0]->traitKey);
    }

    public function test_non_discriminating_traits_do_not_dilute_the_fit_measure(): void
    {
        // Identical mismatch on size, but one breed is judged alongside four
        // traits the user cannot be mismatched on.
        //
        // weightedCost is the pure fit measure, before any confidence
        // adjustment. It must be identical: counting the inert traits in the
        // denominator was the bug that pulled every score toward 100.
        //
        // The final SCORES differ slightly, and correctly so - answering more
        // questions is more information about the user, even when some of the
        // answers turn out not to separate any breeds.
        $inert = new TraitDefinition('inert', penaltyOver: 1.0, penaltyUnder: 0.0);

        $withInert = [
            'size' => new TraitDefinition('size', 1.0, 1.0),
            'i1' => $inert, 'i2' => $inert, 'i3' => $inert, 'i4' => $inert,
        ];
        $withoutInert = ['size' => new TraitDefinition('size', 1.0, 1.0)];

        $profileValues = ['size' => 1.0, 'i1' => 5.0, 'i2' => 5.0, 'i3' => 5.0, 'i4' => 5.0];
        $breedValues   = ['size' => 5.0, 'i1' => 1.0, 'i2' => 1.0, 'i3' => 1.0, 'i4' => 1.0];

        $padded = $this->scorer->score(
            new UserTraitProfile($profileValues),
            new Breed('padded', $breedValues),
            $withInert, [], [], ScoringConfig::defaults()
        );
        $bare = $this->scorer->score(
            new UserTraitProfile(['size' => 1.0]),
            new Breed('bare', ['size' => 5.0]),
            $withoutInert, [], [], ScoringConfig::defaults()
        );

        self::assertSame(1, $padded->traitsUsed, 'only size may be scored');
        self::assertSame(1, $bare->traitsUsed);
        self::assertSame($bare->weightedCost, $padded->weightedCost);

        // Both are the worst possible match on the only trait that matters, so
        // both must land below the neutral prior rather than near it.
        self::assertLessThan(50, $padded->score);
        self::assertLessThan(50, $bare->score);
    }

    public function test_shrinkage_follows_what_the_user_answered_not_what_discriminates(): void
    {
        // A user who answered about nine traits has given full information even
        // if only one of those traits separates any breeds. Keying shrinkage on
        // the discriminating count instead capped their best possible match far
        // below 100 for a reason that has nothing to do with them.
        $inert  = new TraitDefinition('inert', penaltyOver: 1.0, penaltyUnder: 0.0);
        $traits = ['size' => new TraitDefinition('size', 1.0, 1.0)];
        $user   = ['size' => 3.0];
        $breed  = ['size' => 3.0];

        for ($i = 1; $i <= 8; $i++) {
            $traits['i' . $i] = $inert;
            $user['i' . $i]   = 5.0;   // tolerates anything: cannot be mismatched
            $breed['i' . $i]  = 1.0;
        }

        $informed = $this->scorer->score(
            new UserTraitProfile($user), new Breed('b', $breed), $traits, [], [], ScoringConfig::defaults()
        );
        $sparse = $this->scorer->score(
            new UserTraitProfile(['size' => 3.0]),
            new Breed('b', ['size' => 3.0]),
            ['size' => new TraitDefinition('size', 1.0, 1.0)],
            [], [], ScoringConfig::defaults()
        );

        // Same perfect fit on the one trait that can vary, but the informed
        // user gets closer to the ceiling because they told us more.
        self::assertSame($sparse->weightedCost, $informed->weightedCost);
        self::assertGreaterThan($sparse->score, $informed->score);
    }

    public function test_traits_missing_from_the_breed_are_skipped(): void
    {
        $match = $this->scorer->score(
            new UserTraitProfile(['t1' => 3.0, 't2' => 3.0]),
            new Breed('partial', ['t1' => 3.0]),
            $this->traits(2), [], [], ScoringConfig::defaults()
        );

        self::assertSame(1, $match->traitsUsed);
    }

    public function test_breed_with_no_shared_traits_scores_the_shrinkage_prior(): void
    {
        $match = $this->scorer->score(
            new UserTraitProfile(['t1' => 3.0]),
            new Breed('unrelated', ['other' => 3.0]),
            $this->traits(1), [], [], ScoringConfig::defaults()
        );

        self::assertSame(0, $match->traitsUsed);
        self::assertSame(50, $match->score);
    }

    public function test_violations_mark_the_breed_excluded(): void
    {
        [$profile, $breed] = $this->identical(24);

        $match = $this->scorer->score(
            $profile, $breed, $this->traits(24), ['t1' => true], ['t1'], ScoringConfig::defaults()
        );

        self::assertTrue($match->excluded);
        self::assertSame(['t1'], $match->exclusionReasons);
    }

    public function test_verdicts_are_produced_for_every_shared_trait(): void
    {
        [$profile, $breed] = $this->identical(3);

        $match = $this->scorer->score(
            $profile, $breed, $this->traits(3), [], [], ScoringConfig::defaults()
        );

        self::assertCount(3, $match->verdicts);
        self::assertCount(3, $match->verdictsInTier(TraitVerdict::ALIGNED));
    }

    public function test_best_aligned_and_worst_problems_are_ordered_by_cost(): void
    {
        $traits = [
            'perfect' => new TraitDefinition('perfect', 1.0, 1.0),
            'close'   => new TraitDefinition('close', 1.0, 1.0),
            'awful'   => new TraitDefinition('awful', 1.0, 1.0),
        ];
        $match = $this->scorer->score(
            new UserTraitProfile(['perfect' => 3.0, 'close' => 3.0, 'awful' => 1.0]),
            new Breed('b', ['perfect' => 3.0, 'close' => 3.2, 'awful' => 5.0]),
            $traits, [], [], ScoringConfig::defaults()
        );

        $best = $match->bestAligned(2);
        self::assertSame('perfect', $best[0]->traitKey);

        $worst = $match->worstProblems(1);
        self::assertSame('awful', $worst[0]->traitKey);
    }

    public function test_score_is_always_within_zero_and_one_hundred(): void
    {
        [$profile, $breed] = $this->identical(24, 5.0);

        $match = $this->scorer->score(
            $profile, $breed, $this->traits(24), [], [],
            new ScoringConfig(gamma: 0.1, shrinkageM: 0.0)
        );

        self::assertGreaterThanOrEqual(0, $match->score);
        self::assertLessThanOrEqual(100, $match->score);
    }

    public function test_zero_weight_traits_do_not_hand_a_bad_match_a_top_score(): void
    {
        // Regression: $used++ ran before the zero-weight skip, so $used was
        // above zero while $weightSum stayed at zero. weightedCost then came
        // out 0 and the worst possible match scored 75 instead of near 0.
        $traits = [
            'a' => new TraitDefinition('a', 1.0, 1.0, 0.0),
            'b' => new TraitDefinition('b', 1.0, 1.0, 0.0),
        ];

        $match = $this->scorer->score(
            new UserTraitProfile(['a' => 1.0, 'b' => 1.0]),
            new Breed('worst', ['a' => 5.0, 'b' => 5.0]),
            $traits, [], [], ScoringConfig::defaults()
        );

        self::assertSame(0, $match->traitsUsed, 'a zero-weight trait is not a scored trait');
        self::assertSame(50, $match->score, 'nothing was measured, so the neutral prior stands');
        self::assertSame([], $match->verdicts);
    }

    public function test_a_single_zero_weight_trait_does_not_distort_the_others(): void
    {
        $traits = [
            'counts' => new TraitDefinition('counts', 1.0, 1.0, 1.0),
            'muted'  => new TraitDefinition('muted', 1.0, 1.0, 0.0),
        ];

        $match = $this->scorer->score(
            new UserTraitProfile(['counts' => 3.0, 'muted' => 1.0]),
            new Breed('b', ['counts' => 3.0, 'muted' => 5.0]),
            $traits, [], [], ScoringConfig::defaults()
        );

        self::assertSame(1, $match->traitsUsed);
        self::assertSame(0.0, $match->weightedCost, 'the muted trait must not contribute cost');
    }
}
