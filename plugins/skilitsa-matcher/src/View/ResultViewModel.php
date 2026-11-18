<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\View;

/** Exactly what the results page renders. No WordPress, no engine types. */
final class ResultViewModel
{
    /**
     * @param list<BreedCard> $podium    gold, silver, bronze
     * @param list<BreedCard> $runnersUp ranks 4 onwards
     * @param list<BreedCard> $excluded  breeds a dealbreaker removed
     */
    public function __construct(
        public readonly array $podium = [],
        public readonly array $runnersUp = [],
        public readonly array $excluded = [],
        public readonly bool $isCloseCall = false,
        public readonly bool $hasEnoughEvidence = true,
        public readonly ?string $blockingDealbreakerLabel = null,
        public readonly string $disclaimerTitle = '',
        public readonly string $disclaimerBody = '',
        public readonly int $traitsKnown = 0,
    ) {
    }

    public function hasResults(): bool
    {
        return $this->podium !== [];
    }

    public function everythingExcluded(): bool
    {
        return $this->podium === [] && $this->excluded !== [];
    }
}
