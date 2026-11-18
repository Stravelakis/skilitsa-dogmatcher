<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\UserTraitProfile;

/**
 * Spec section 4.7 (exclusion) and 4.6 (salience).
 *
 * A dealbreaker mark does two jobs: it can exclude a breed outright, and it
 * tells the scorer which traits this user actually cares about - which is how
 * salience is derived without asking a third question per screen.
 */
final class DealbreakerEvaluator
{
    public function __construct(
        private readonly TraitCostCalculator $costs,
    ) {
    }

    /**
     * @param list<QuestionTraitLink> $links
     * @return array<string,true> Trait keys reached by a marked question.
     */
    public function salientTraitKeys(AnswerSet $answers, array $links): array
    {
        $salient = [];
        foreach ($links as $link) {
            if (!$answers->isDealbreaker($link->questionKey)) {
                continue;
            }
            // An ignored question expresses no preference, so it cannot make
            // anything salient even when the dealbreaker box is still ticked.
            if ($answers->answerFor($link->questionKey) === null) {
                continue;
            }
            $salient[$link->traitKey] = true;
        }
        ksort($salient);

        return $salient;
    }

    /**
     * @param array<string,true>            $salientTraitKeys
     * @param array<string,TraitDefinition> $traits
     * @return list<string> Violated trait keys, sorted, each counted once.
     */
    public function violations(
        array $salientTraitKeys,
        UserTraitProfile $profile,
        Breed $breed,
        array $traits,
        ScoringConfig $config,
    ): array {
        $violated = [];

        foreach (array_keys($salientTraitKeys) as $traitKey) {
            $trait = $traits[$traitKey] ?? null;
            if ($trait === null) {
                continue;
            }

            $userValue  = $profile->valueFor($traitKey);
            $breedValue = $breed->traitValue($traitKey);
            if ($userValue === null || $breedValue === null) {
                continue;
            }

            if ($this->costs->rawCost($trait, $userValue, $breedValue) >= $config->dealbreakerThreshold) {
                $violated[] = $traitKey;
            }
        }

        sort($violated);

        return $violated;
    }
}
