<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\TraitVerdict;

/** Spec section 4.9. */
final class VerdictClassifier
{
    public function __construct(
        private readonly TraitCostCalculator $costs,
    ) {
    }

    public function classify(
        TraitDefinition $trait,
        float $userValue,
        float $breedValue,
        ScoringConfig $config,
        bool $dealbreakerViolated = false,
    ): TraitVerdict {
        $rawCost   = $this->costs->rawCost($trait, $userValue, $breedValue);
        $direction = $this->costs->direction($userValue, $breedValue);

        $tier = match (true) {
            $dealbreakerViolated            => TraitVerdict::OPPOSED,
            $rawCost <= $config->alignedMax => TraitVerdict::ALIGNED,
            $rawCost <= $config->neutralMax => TraitVerdict::NEUTRAL,
            $rawCost <= $config->pitfallMax => TraitVerdict::PITFALL,
            default                         => TraitVerdict::OPPOSED,
        };

        return new TraitVerdict($trait->key, $tier, $userValue, $breedValue, $rawCost, $direction);
    }
}
