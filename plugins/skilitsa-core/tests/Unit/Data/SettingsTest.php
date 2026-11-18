<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\Settings;
use Skilitsa\Core\Engine\Value\ScoringConfig;

final class SettingsTest extends TestCase
{
    public function test_empty_storage_yields_engine_defaults(): void
    {
        $config   = Settings::scoringConfigFrom([]);
        $expected = ScoringConfig::defaults();

        self::assertSame($expected->gamma, $config->gamma);
        self::assertSame($expected->shrinkageM, $config->shrinkageM);
        self::assertSame($expected->minTraitsForResults, $config->minTraitsForResults);
    }

    public function test_stored_values_are_applied(): void
    {
        $config = Settings::scoringConfigFrom([
            'gamma' => 1.2,
            'shrinkage_m' => 3.0,
            'salience_multiplier' => 2.5,
            'dealbreaker_threshold' => 1.5,
            'close_call_points' => 6,
            'min_traits_for_results' => 10,
        ]);

        self::assertSame(1.2, $config->gamma);
        self::assertSame(3.0, $config->shrinkageM);
        self::assertSame(2.5, $config->salienceMultiplier);
        self::assertSame(1.5, $config->dealbreakerThreshold);
        self::assertSame(6, $config->closeCallPoints);
        self::assertSame(10, $config->minTraitsForResults);
    }

    public function test_numeric_strings_are_accepted(): void
    {
        // Options round-tripped through a form arrive as strings.
        $config = Settings::scoringConfigFrom(['gamma' => '1.2', 'shrinkage_m' => '3']);

        self::assertSame(1.2, $config->gamma);
        self::assertSame(3.0, $config->shrinkageM);
    }

    public function test_junk_values_fall_back_to_defaults_rather_than_throwing(): void
    {
        $config = Settings::scoringConfigFrom([
            'gamma' => 'very strict',
            'shrinkage_m' => null,
            'min_traits_for_results' => [],
        ]);

        $expected = ScoringConfig::defaults();
        self::assertSame($expected->gamma, $config->gamma);
        self::assertSame($expected->shrinkageM, $config->shrinkageM);
        self::assertSame($expected->minTraitsForResults, $config->minTraitsForResults);
    }

    public function test_a_zero_gamma_would_be_invalid_so_it_falls_back(): void
    {
        // ScoringConfig rejects gamma <= 0. Settings must not hand it one.
        $config = Settings::scoringConfigFrom(['gamma' => 0]);

        self::assertSame(ScoringConfig::defaults()->gamma, $config->gamma);
    }

    public function test_sanitize_clamps_gamma_to_a_usable_range(): void
    {
        self::assertSame(0.1, Settings::sanitize(['gamma' => -5])['gamma']);
        self::assertSame(5.0, Settings::sanitize(['gamma' => 99])['gamma']);
        self::assertSame(1.6, Settings::sanitize(['gamma' => 1.6])['gamma']);
    }

    public function test_sanitize_rejects_a_non_array_without_fatal(): void
    {
        // v1 type-hinted this parameter as array and would TypeError on a
        // non-array, white-screening the settings page.
        self::assertSame(Settings::defaults(), Settings::sanitize('nonsense'));
        self::assertSame(Settings::defaults(), Settings::sanitize(null));
    }

    public function test_sanitize_keeps_only_known_keys(): void
    {
        $clean = Settings::sanitize(['gamma' => 1.2, 'evil' => 'rm -rf']);

        self::assertArrayHasKey('gamma', $clean);
        self::assertArrayNotHasKey('evil', $clean);
    }

    public function test_sanitize_validates_flow_mode_against_an_allowlist(): void
    {
        self::assertSame('tiered', Settings::sanitize(['flow_mode' => 'tiered'])['flow_mode']);
        self::assertSame('all', Settings::sanitize(['flow_mode' => 'adaptive'])['flow_mode'], 'not implemented yet');
        self::assertSame('all', Settings::sanitize(['flow_mode' => '<script>'])['flow_mode']);
    }

    public function test_defaults_round_trip_through_sanitize_unchanged(): void
    {
        self::assertSame(Settings::defaults(), Settings::sanitize(Settings::defaults()));
    }

    public function test_defaults_produce_the_engine_default_config(): void
    {
        $config   = Settings::scoringConfigFrom(Settings::defaults());
        $expected = ScoringConfig::defaults();

        self::assertSame($expected->gamma, $config->gamma);
        self::assertSame($expected->shrinkageM, $config->shrinkageM);
        self::assertSame($expected->salienceMultiplier, $config->salienceMultiplier);
        self::assertSame($expected->closeCallPoints, $config->closeCallPoints);
    }

    public function test_a_zero_dealbreaker_threshold_falls_back_rather_than_excluding_everything(): void
    {
        // ScoringConfig now rejects it; Settings must absorb that rejection.
        $config = Settings::scoringConfigFrom(['dealbreaker_threshold' => 0]);

        self::assertSame(ScoringConfig::defaults()->dealbreakerThreshold, $config->dealbreakerThreshold);
    }

    public function test_sanitize_clamps_the_dealbreaker_threshold_above_zero(): void
    {
        self::assertGreaterThan(0.0, Settings::sanitize(['dealbreaker_threshold' => 0])['dealbreaker_threshold']);
        self::assertGreaterThan(0.0, Settings::sanitize(['dealbreaker_threshold' => -3])['dealbreaker_threshold']);
    }
}
