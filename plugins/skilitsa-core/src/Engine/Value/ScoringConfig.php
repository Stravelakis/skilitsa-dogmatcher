<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use InvalidArgumentException;

final class ScoringConfig
{
    public function __construct(
        /**
         * Strictness curve, applied to a normalised cost in 0..1.
         *
         * Because the base is below 1, a HIGHER exponent yields a SMALLER cost:
         *   gamma < 1  stricter  - every score drops
         *   gamma = 1  linear baseline
         *   gamma > 1  more lenient overall, but widens the gap between good
         *              and bad matches
         *
         * Recalibrated 2026-08-23 against the real 257-breed matrix, over six
         * profiles spanning the answer space, WITH the asymmetric penalties in
         * TraitDefaults. Measured averages:
         *
         *   gamma  m    top  median  bottom  top-10 spread
         *   1.6   1.0   87.8   70.8    46.2    4.7
         *   1.3   1.0   85.5   66.3    42.7    5.7
         *   1.0   1.0   82.2   61.0    38.5    7.2
         *   0.7   1.0   77.5   54.2    32.8    8.7   <- chosen
         *
         * Asymmetry removed the cost from every "free" direction, so every
         * score rose: at gamma 1.0 the median moved 55 -> 61 and an ordinary
         * breed started reading as a decent match. 0.7 restores the reviewed
         * distribution and widens separation among the ten breeds actually
         * shown, which is the number that matters on the page.
         *
         * The first calibration (2026-08-21, symmetric penalties) chose 1.0 on
         * the same reasoning applied to a different cost surface.
         */
        public readonly float $gamma = 0.7,
        /**
         * Pulls thin results toward 50. Higher = more sceptical of few traits.
         *
         * This is the dominant control on score SPREAD, far more than gamma.
         * Measured across the golden cases: m=6 gives a mean spread of 21
         * points, m=2 gives 34, m=0 gives 53 but removes all protection
         * against confident scores from two answers.
         *
         * Calibrated 2026-08-21 against the real matrix. At m=1 a 2-trait
         * result is still pulled a third of the way to neutral while a
         * 24-trait result barely moves, and the evidence floor already blocks
         * genuinely thin input from being shown at all.
         */
        public readonly float $shrinkageM = 1.0,
        /** Weight multiplier for traits the user marked as a dealbreaker. */
        public readonly float $salienceMultiplier = 2.0,
        /** Raw cost at or above which a marked dealbreaker excludes the breed. */
        public readonly float $dealbreakerThreshold = 2.0,
        public readonly float $alignedMax = 0.5,
        public readonly float $neutralMax = 1.5,
        public readonly float $pitfallMax = 2.5,
        /**
         * Top-3 within this many points triggers the close-call notice.
         *
         * Raised 4 -> 5 with gamma 0.7. A strictness change moves every gap on
         * the page, and this threshold is expressed in those same points: the
         * measured top-10 spread on the real matrix went 7.2 -> 8.7, so a
         * threshold left at 4 would quietly stop calling genuine ties ties.
         */
        public readonly int $closeCallPoints = 5,
        /** Below this many known traits, results are not shown at all. */
        public readonly int $minTraitsForResults = 8,
    ) {
        if ($gamma <= 0.0) {
            throw new InvalidArgumentException('gamma must be greater than zero.');
        }
        if ($shrinkageM < 0.0) {
            throw new InvalidArgumentException('shrinkageM cannot be negative.');
        }
        if ($salienceMultiplier < 1.0) {
            throw new InvalidArgumentException('salienceMultiplier must be at least 1.0.');
        }
        // A threshold of zero would make every salient trait a violation, even
        // on a perfect match, excluding every breed.
        if ($dealbreakerThreshold <= 0.0) {
            throw new InvalidArgumentException('dealbreakerThreshold must be greater than zero.');
        }
        if ($closeCallPoints < 0) {
            throw new InvalidArgumentException('closeCallPoints cannot be negative.');
        }
        if ($minTraitsForResults < 1) {
            throw new InvalidArgumentException('minTraitsForResults must be at least 1.');
        }
        if (!($alignedMax < $neutralMax && $neutralMax < $pitfallMax)) {
            throw new InvalidArgumentException('Verdict thresholds must increase.');
        }
    }

    public static function defaults(): self
    {
        return new self();
    }
}
