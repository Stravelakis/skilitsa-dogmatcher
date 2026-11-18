<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

final class MatchResult
{
    /**
     * @param list<BreedMatch> $ranked   Best first, exclusions already removed.
     * @param list<BreedMatch> $excluded Breeds that violated a dealbreaker.
     * @param string|null      $blockingDealbreaker Trait key excluding the most
     *        breeds, present only when nothing survived. Drives the
     *        "relax this must-have?" affordance.
     */
    public function __construct(
        public readonly array $ranked,
        public readonly array $excluded,
        public readonly int $traitsKnown,
        public readonly bool $hasEnoughEvidence,
        public readonly bool $isCloseCall,
        public readonly ?string $blockingDealbreaker = null,
    ) {
    }

    /** @return list<BreedMatch> Gold, silver, bronze. */
    public function podium(): array
    {
        return array_slice($this->ranked, 0, 3);
    }

    /** @return list<BreedMatch> Ranks 4 onwards. */
    public function runnersUp(int $limit): array
    {
        return array_slice($this->ranked, 3, $limit);
    }

    /** @return list<BreedMatch> */
    public function top(int $limit): array
    {
        return array_slice($this->ranked, 0, $limit);
    }
}
