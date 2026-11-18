<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use Skilitsa\Core\Engine\TraitCostCalculator;

final class TraitVerdict
{
    public const ALIGNED = 'aligned';
    public const NEUTRAL = 'neutral';
    public const PITFALL = 'pitfall';
    public const OPPOSED = 'opposed';

    public function __construct(
        public readonly string $traitKey,
        public readonly string $tier,
        public readonly float $userValue,
        public readonly float $breedValue,
        public readonly float $rawCost,
        public readonly string $direction,
    ) {
    }

    /**
     * Which copy this verdict draws on.
     *
     * Aligned and pitfall use the pos_/neg_ template families; the caller picks
     * between the _1 and _2 variants. Opposed uses the directional warning
     * columns on the trait itself. Neutral is deliberately silent, so it
     * returns an empty string.
     *
     * The warning names read from the USER's side: warning_too_low is the full
     * column warning_too_low_(user_wants_low,_breed_is_high), which is why
     * BREED_HIGHER maps to it.
     */
    public function templateKey(): string
    {
        return match ($this->tier) {
            self::ALIGNED => 'pos_' . $this->traitKey,
            self::PITFALL => 'neg_' . $this->traitKey,
            self::OPPOSED => $this->direction === TraitCostCalculator::BREED_HIGHER
                ? 'warning_too_low'
                : 'warning_too_high',
            default => '',
        };
    }

    public function isProblem(): bool
    {
        return $this->tier === self::PITFALL || $this->tier === self::OPPOSED;
    }
}
