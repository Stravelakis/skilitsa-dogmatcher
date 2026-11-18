<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use InvalidArgumentException;

/**
 * A trait and how badly a mismatch in each direction counts.
 *
 * penaltyOver  applies when the breed's value EXCEEDS what the user wants.
 * penaltyUnder applies when the breed's value FALLS SHORT of what they want.
 *
 * Example: shedding is (1.0, 0.0) - a breed that sheds more than you tolerate
 * is a daily problem; one that sheds less costs you nothing.
 */
final class TraitDefinition
{
    public const MIN_VALUE    = 1.0;
    public const MAX_VALUE    = 5.0;
    public const MAX_DISTANCE = self::MAX_VALUE - self::MIN_VALUE;

    public function __construct(
        public readonly string $key,
        public readonly float $penaltyOver,
        public readonly float $penaltyUnder,
        public readonly float $globalWeight = 1.0,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('Trait key cannot be empty.');
        }
        if ($penaltyOver < 0.0 || $penaltyUnder < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Trait "%s": penalties cannot be negative.', $key)
            );
        }
        if ($globalWeight < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Trait "%s": global weight cannot be negative.', $key)
            );
        }
    }

    /**
     * The largest raw cost this trait can produce, used to normalise it to 0..1
     * so that gamma applies uniformly across traits with different multipliers.
     *
     * Returns 0.0 when the trait can never contribute; callers must treat that
     * as "skip" rather than dividing by it.
     */
    public function maxRawCost(): float
    {
        return self::MAX_DISTANCE * max($this->penaltyOver, $this->penaltyUnder);
    }
}
