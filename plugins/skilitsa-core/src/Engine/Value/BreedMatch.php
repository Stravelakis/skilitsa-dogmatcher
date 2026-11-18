<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

final class BreedMatch
{
    /**
     * @param list<TraitVerdict> $verdicts
     * @param list<string>       $exclusionReasons Violated trait keys.
     */
    public function __construct(
        public readonly string $breedKey,
        public readonly int $score,
        /** Full-precision weighted cost, 0.0..1.0. Used for tiebreaks. */
        public readonly float $weightedCost,
        public readonly int $traitsUsed,
        public readonly array $verdicts,
        public readonly bool $excluded = false,
        public readonly array $exclusionReasons = [],
    ) {
    }

    /** @return list<TraitVerdict> */
    public function verdictsInTier(string $tier): array
    {
        return array_values(array_filter(
            $this->verdicts,
            static fn (TraitVerdict $v): bool => $v->tier === $tier
        ));
    }

    /** @return list<TraitVerdict> Closest fits first. */
    public function bestAligned(int $limit): array
    {
        $aligned = $this->verdictsInTier(TraitVerdict::ALIGNED);
        usort($aligned, static function (TraitVerdict $a, TraitVerdict $b): int {
            return $a->rawCost <=> $b->rawCost ?: strcmp($a->traitKey, $b->traitKey);
        });

        return array_slice($aligned, 0, $limit);
    }

    /** @return list<TraitVerdict> Worst mismatches first. */
    public function worstProblems(int $limit): array
    {
        $problems = array_values(array_filter(
            $this->verdicts,
            static fn (TraitVerdict $v): bool => $v->isProblem()
        ));
        usort($problems, static function (TraitVerdict $a, TraitVerdict $b): int {
            return $b->rawCost <=> $a->rawCost ?: strcmp($a->traitKey, $b->traitKey);
        });

        return array_slice($problems, 0, $limit);
    }
}
