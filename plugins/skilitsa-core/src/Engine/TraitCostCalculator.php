<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\TraitDefinition;

/**
 * Asymmetric mismatch cost for a single trait. Spec section 4.3.
 *
 * Distance in either direction does NOT cost the same. An experienced owner
 * matched with an easy dog is fine; a novice matched with a demanding dog is
 * not. Same distance, opposite meanings.
 */
final class TraitCostCalculator
{
    public const BREED_HIGHER = 'breed_higher';
    public const BREED_LOWER  = 'breed_lower';
    public const LEVEL        = 'level';

    public function rawCost(TraitDefinition $trait, float $userValue, float $breedValue): float
    {
        $over  = max(0.0, $breedValue - $userValue);
        $under = max(0.0, $userValue - $breedValue);

        return $over * $trait->penaltyOver + $under * $trait->penaltyUnder;
    }

    /**
     * The worst cost THIS user could possibly incur on this trait, given the
     * answer they gave.
     *
     * This is not the same as the trait's global worst case. Consider an
     * over-costly trait such as shedding (1.0, 0.0): a user who answered 5
     * tolerates any amount of shedding, so no breed in the 1..5 range can cost
     * them anything. The trait cannot discriminate for them at all.
     *
     * Returns 0.0 for exactly those traits. Callers must skip them entirely
     * rather than counting them - a trait that cannot vary must not sit in the
     * denominator of an average, or it drags every score toward 100.
     */
    public function maxCostForUser(TraitDefinition $trait, float $userValue): float
    {
        $worstAbove = $trait->penaltyOver * (TraitDefinition::MAX_VALUE - $userValue);
        $worstBelow = $trait->penaltyUnder * ($userValue - TraitDefinition::MIN_VALUE);

        return max(0.0, $worstAbove, $worstBelow);
    }

    /**
     * True when this trait can produce a different cost for different breeds,
     * given what the user asked for.
     */
    public function discriminates(TraitDefinition $trait, float $userValue): bool
    {
        return $this->maxCostForUser($trait, $userValue) > 0.0;
    }

    /**
     * Raw cost scaled to 0..1 against the worst this user could have suffered,
     * so a score reads as "how much of the badness available to you did this
     * breed incur".
     *
     * Returns 0.0 for a non-discriminating trait; check discriminates() first
     * and skip, rather than treating that zero as a perfect match.
     */
    public function normalisedCost(TraitDefinition $trait, float $userValue, float $breedValue): float
    {
        $max = $this->maxCostForUser($trait, $userValue);
        if ($max <= 0.0) {
            return 0.0;
        }

        $normalised = $this->rawCost($trait, $userValue, $breedValue) / $max;

        return max(0.0, min(1.0, $normalised));
    }

    public function direction(float $userValue, float $breedValue): string
    {
        if ($breedValue > $userValue) {
            return self::BREED_HIGHER;
        }
        if ($breedValue < $userValue) {
            return self::BREED_LOWER;
        }

        return self::LEVEL;
    }
}
