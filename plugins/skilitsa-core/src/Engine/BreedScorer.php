<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\BreedMatch;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\UserTraitProfile;

/**
 * Scores one breed against one user profile. Spec sections 4.4 and 4.5.
 *
 *   cost[t]  = normalisedCost(t) ^ gamma          (0..1)
 *   w[t]     = globalWeight[t] x salienceMultiplier if salient
 *   weighted = sum(cost x w) / sum(w)             (0..1)
 *   raw      = 100 x (1 - weighted)
 *   final    = (n x raw + m x 50) / (n + m)       shrinkage toward neutral
 */
final class BreedScorer
{
    private const NEUTRAL_PRIOR = 50.0;

    public function __construct(
        private readonly TraitCostCalculator $costs,
        private readonly VerdictClassifier $classifier,
    ) {
    }

    /**
     * @param array<string,TraitDefinition> $traits
     * @param array<string,true>            $salientTraitKeys
     * @param list<string>                  $violations
     */
    public function score(
        UserTraitProfile $profile,
        Breed $breed,
        array $traits,
        array $salientTraitKeys,
        array $violations,
        ScoringConfig $config,
    ): BreedMatch {
        $violatedLookup = array_fill_keys($violations, true);

        $costSum   = 0.0;
        $weightSum = 0.0;
        $verdicts  = [];
        $used      = 0;

        foreach ($profile->knownTraitKeys() as $traitKey) {
            $trait = $traits[$traitKey] ?? null;
            if ($trait === null) {
                continue;
            }

            $userValue  = $profile->valueFor($traitKey);
            $breedValue = $breed->traitValue($traitKey);
            if ($userValue === null || $breedValue === null) {
                continue;
            }

            // A trait the user cannot be mismatched on tells us nothing about
            // fit. Counting it would both dilute the average toward 100 and
            // produce a vacuous "great match on shedding!" for someone who
            // simply does not care about shedding.
            if (!$this->costs->discriminates($trait, $userValue)) {
                continue;
            }

            $weight = $trait->globalWeight
                * (isset($salientTraitKeys[$traitKey]) ? $config->salienceMultiplier : 1.0);

            // A zero-weight trait contributes nothing to the score, so it must
            // not count as a scored trait either. Counting it left $used above
            // zero while $weightSum stayed at zero, which made weightedCost 0
            // and handed the worst possible match a raw score of 100.
            if ($weight <= 0.0) {
                continue;
            }

            $used++;

            $verdicts[] = $this->classifier->classify(
                $trait,
                $userValue,
                $breedValue,
                $config,
                isset($violatedLookup[$traitKey]),
            );

            $normalised = $this->costs->normalisedCost($trait, $userValue, $breedValue);

            $costSum   += ($normalised ** $config->gamma) * $weight;
            $weightSum += $weight;
        }

        $weightedCost = $weightSum > 0.0 ? $costSum / $weightSum : 0.0;
        $rawScore     = $used > 0 ? 100.0 * (1.0 - $weightedCost) : self::NEUTRAL_PRIOR;

        // Shrinkage measures how much the USER told us, not how many traits
        // happen to vary across breeds. Someone who answered every question
        // has given full information even if half their traits turn out not to
        // discriminate - that is a property of the breed matrix, not of their
        // input, and punishing them for it caps their best match well below
        // 100 for no reason.
        $evidence = $profile->count();

        $shrunk = $used > 0
            ? ($evidence * $rawScore + $config->shrinkageM * self::NEUTRAL_PRIOR) / ($evidence + $config->shrinkageM)
            : self::NEUTRAL_PRIOR;

        $score = (int) round(max(0.0, min(100.0, $shrunk)));

        return new BreedMatch(
            breedKey: $breed->key,
            score: $score,
            weightedCost: $weightedCost,
            traitsUsed: $used,
            verdicts: $verdicts,
            excluded: $violations !== [],
            exclusionReasons: $violations,
        );
    }
}
