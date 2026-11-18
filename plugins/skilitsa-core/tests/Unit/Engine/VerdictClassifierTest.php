<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\TraitCostCalculator;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\TraitVerdict;
use Skilitsa\Core\Engine\VerdictClassifier;

final class VerdictClassifierTest extends TestCase
{
    private VerdictClassifier $classifier;
    private ScoringConfig $config;
    private TraitDefinition $symmetric;

    protected function setUp(): void
    {
        $this->classifier = new VerdictClassifier(new TraitCostCalculator());
        $this->config     = ScoringConfig::defaults();
        $this->symmetric  = new TraitDefinition('size', 1.0, 1.0);
    }

    public function test_exact_match_is_aligned(): void
    {
        $v = $this->classifier->classify($this->symmetric, 3.0, 3.0, $this->config);
        self::assertSame(TraitVerdict::ALIGNED, $v->tier);
    }

    public function test_small_gap_is_aligned(): void
    {
        // rawCost 0.5, at the aligned boundary (inclusive)
        $v = $this->classifier->classify($this->symmetric, 3.0, 3.5, $this->config);
        self::assertSame(TraitVerdict::ALIGNED, $v->tier);
    }

    public function test_moderate_gap_is_neutral(): void
    {
        $v = $this->classifier->classify($this->symmetric, 3.0, 4.0, $this->config);
        self::assertSame(TraitVerdict::NEUTRAL, $v->tier);
    }

    public function test_large_gap_is_pitfall(): void
    {
        $v = $this->classifier->classify($this->symmetric, 2.0, 4.0, $this->config);
        self::assertSame(TraitVerdict::PITFALL, $v->tier);
    }

    public function test_very_large_gap_is_opposed(): void
    {
        $v = $this->classifier->classify($this->symmetric, 1.0, 5.0, $this->config);
        self::assertSame(TraitVerdict::OPPOSED, $v->tier);
    }

    public function test_violated_dealbreaker_forces_opposed_regardless_of_cost(): void
    {
        $v = $this->classifier->classify(
            $this->symmetric, 3.0, 3.0, $this->config, dealbreakerViolated: true
        );
        self::assertSame(TraitVerdict::OPPOSED, $v->tier);
    }

    public function test_asymmetric_trait_free_side_stays_aligned_despite_distance(): void
    {
        // shedding (1.0, 0.0): breed sheds far LESS than tolerated. Distance is
        // large but the cost is zero, so the verdict must be aligned.
        $shedding = new TraitDefinition('shedding', penaltyOver: 1.0, penaltyUnder: 0.0);

        $v = $this->classifier->classify($shedding, 5.0, 1.0, $this->config);
        self::assertSame(TraitVerdict::ALIGNED, $v->tier);
        self::assertSame(0.0, $v->rawCost);
    }

    public function test_template_key_for_aligned_uses_pos_prefix(): void
    {
        $v = $this->classifier->classify($this->symmetric, 3.0, 3.0, $this->config);
        self::assertSame('pos_size', $v->templateKey());
    }

    public function test_template_key_for_pitfall_uses_neg_prefix(): void
    {
        $v = $this->classifier->classify($this->symmetric, 2.0, 4.0, $this->config);
        self::assertSame('neg_size', $v->templateKey());
    }

    public function test_template_key_for_neutral_is_empty_because_neutral_is_silent(): void
    {
        $v = $this->classifier->classify($this->symmetric, 3.0, 4.0, $this->config);
        self::assertSame('', $v->templateKey());
    }

    public function test_opposed_selects_directional_warning_when_breed_is_higher(): void
    {
        // Breed exceeds what the user wants -> the user wanted LOW and the breed
        // is HIGH -> warning_too_low, matching the existing column names.
        $v = $this->classifier->classify($this->symmetric, 1.0, 5.0, $this->config);

        self::assertSame(TraitVerdict::OPPOSED, $v->tier);
        self::assertSame(TraitCostCalculator::BREED_HIGHER, $v->direction);
        self::assertSame('warning_too_low', $v->templateKey());
    }

    public function test_opposed_selects_directional_warning_when_breed_is_lower(): void
    {
        $v = $this->classifier->classify($this->symmetric, 5.0, 1.0, $this->config);

        self::assertSame(TraitCostCalculator::BREED_LOWER, $v->direction);
        self::assertSame('warning_too_high', $v->templateKey());
    }
}
