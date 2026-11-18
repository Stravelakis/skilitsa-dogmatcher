<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\BreedMatch;
use Skilitsa\Core\Engine\Value\TraitVerdict;

/**
 * Explains why one breed outranked another. Spec section 4.10.
 *
 * The per-trait costs are already computed during scoring, so this is pure
 * presentation logic over data we have.
 */
final class MatchComparator
{
    /**
     * Traits where the winner costs less than the loser, biggest gap first.
     *
     * @return list<array{traitKey:string, winnerCost:float, loserCost:float, advantage:float}>
     */
    public function differentiators(BreedMatch $winner, BreedMatch $loser, int $limit = 3): array
    {
        $winnerCosts = $this->costsByTrait($winner);
        $loserCosts  = $this->costsByTrait($loser);

        $diffs = [];
        foreach ($winnerCosts as $traitKey => $winnerCost) {
            if (!array_key_exists($traitKey, $loserCosts)) {
                continue;
            }

            $advantage = $loserCosts[$traitKey] - $winnerCost;
            if ($advantage <= 0.0) {
                continue;
            }

            $diffs[] = [
                'traitKey'   => $traitKey,
                'winnerCost' => $winnerCost,
                'loserCost'  => $loserCosts[$traitKey],
                'advantage'  => $advantage,
            ];
        }

        usort($diffs, static function (array $a, array $b): int {
            return $b['advantage'] <=> $a['advantage'] ?: strcmp($a['traitKey'], $b['traitKey']);
        });

        return array_slice($diffs, 0, $limit);
    }

    /** @return array<string,float> */
    private function costsByTrait(BreedMatch $match): array
    {
        $costs = [];
        foreach ($match->verdicts as $verdict) {
            /** @var TraitVerdict $verdict */
            $costs[$verdict->traitKey] = $verdict->rawCost;
        }

        return $costs;
    }
}
