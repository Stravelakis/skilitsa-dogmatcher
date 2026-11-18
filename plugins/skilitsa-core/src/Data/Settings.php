<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

use InvalidArgumentException;
use Skilitsa\Core\Engine\Value\ScoringConfig;

/**
 * Plugin settings, and the mapping from stored options to a ScoringConfig.
 *
 * Deliberately pure and deliberately tolerant. In v1 these values lived on a
 * Google Sheet tab that failed its column check, silently produced an empty
 * array, and left every knob running on a hardcoded default for months without
 * anyone noticing. Here, bad input falls back to a known-good value rather than
 * throwing or vanishing.
 */
final class Settings
{
    public const OPTION_NAME = 'skilitsa_core_settings';

    /**
     * The sheet the data is imported from.
     *
     * Kept as a setting rather than a constant so the plugin can be pointed at
     * a copy: someone translating the quiz makes their own sheet, keeps the tab
     * and column names, and changes this. Nothing else about the install has to
     * differ.
     */
    public const DEFAULT_SHEET_ID = '1Du1Lod48C0naHt6NWUc1DP_5BAf9st90cKDDsUdwpY0';

    /** The quiz's one brand colour. Everything else on the page derives from it. */
    public const DEFAULT_ACCENT = '#2f6f4f';

    /** Flow modes the matcher actually implements. `adaptive` is spec'd but unbuilt. */
    public const FLOW_MODES = ['all', 'live', 'tiered'];

    /** Inclusive bounds enforced by sanitize(). */
    private const BOUNDS = [
        'gamma'                  => [0.1, 5.0],
        'shrinkage_m'            => [0.0, 20.0],
        'salience_multiplier'    => [1.0, 5.0],
        'dealbreaker_threshold'  => [0.5, 4.0],
        'close_call_points'      => [0, 25],
        'min_traits_for_results' => [1, 24],
        'results_top_cards'      => [1, 5],
        'results_more_count'     => [0, 50],
    ];

    /**
     * The inclusive bounds, for anything that must show or enforce them.
     *
     * @return array<string,array{0: float|int, 1: float|int}>
     */
    public static function bounds(): array
    {
        return self::BOUNDS;
    }

    /** @return array<string,mixed> */
    public static function defaults(): array
    {
        $engine = ScoringConfig::defaults();

        return [
            'gamma'                  => $engine->gamma,
            'shrinkage_m'            => $engine->shrinkageM,
            'salience_multiplier'    => $engine->salienceMultiplier,
            'dealbreaker_threshold'  => $engine->dealbreakerThreshold,
            'close_call_points'      => $engine->closeCallPoints,
            'min_traits_for_results' => $engine->minTraitsForResults,
            'results_top_cards'      => 3,
            'results_more_count'     => 7,
            'flow_mode'              => 'all',
            'accent_color'           => self::DEFAULT_ACCENT,
            'sheet_id'               => self::DEFAULT_SHEET_ID,
        ];
    }

    /** @param array<string,mixed> $stored */
    public static function scoringConfigFrom(array $stored): ScoringConfig
    {
        $defaults = ScoringConfig::defaults();

        try {
            return new ScoringConfig(
                gamma: self::float($stored, 'gamma', $defaults->gamma),
                shrinkageM: self::float($stored, 'shrinkage_m', $defaults->shrinkageM),
                salienceMultiplier: self::float($stored, 'salience_multiplier', $defaults->salienceMultiplier),
                dealbreakerThreshold: self::float($stored, 'dealbreaker_threshold', $defaults->dealbreakerThreshold),
                closeCallPoints: self::int($stored, 'close_call_points', $defaults->closeCallPoints),
                minTraitsForResults: self::int($stored, 'min_traits_for_results', $defaults->minTraitsForResults),
            );
        } catch (InvalidArgumentException) {
            // A stored combination the engine rejects must never take the site
            // down. Fall back whole rather than piecemeal.
            return $defaults;
        }
    }

    /** @return array<string,mixed> */
    public static function sanitize(mixed $input): array
    {
        if (!is_array($input)) {
            return self::defaults();
        }

        $clean = self::defaults();

        foreach ($clean as $key => $default) {
            if (!array_key_exists($key, $input)) {
                continue;
            }

            $value = $input[$key];

            if ($key === 'sheet_id') {
                $id = self::sheetIdFrom((string) $value);
                $clean[$key] = $id !== '' ? $id : $default;
                continue;
            }

            if ($key === 'accent_color') {
                $clean[$key] = self::isHexColour($value) ? strtolower((string) $value) : $default;
                continue;
            }

            if ($key === 'flow_mode') {
                $clean[$key] = in_array($value, self::FLOW_MODES, true) ? $value : $default;
                continue;
            }

            if (!is_numeric($value)) {
                continue;
            }

            // A key added to defaults() without a matching BOUNDS entry would
            // otherwise destructure null and silently store 0.
            if (!isset(self::BOUNDS[$key])) {
                continue;
            }

            [$min, $max] = self::BOUNDS[$key];
            $clamped     = max($min, min($max, (float) $value));

            $clean[$key] = is_int($default) ? (int) round($clamped) : $clamped;
        }

        return $clean;
    }

    /**
     * The sheet ID out of whatever was pasted.
     *
     * People paste the address bar, not the ID - it is the thing they can see.
     * Pulling the ID out of a full URL is three lines here and saves everyone
     * who ever configures this from finding out what a sheet ID is.
     *
     * Returns '' when there is nothing usable, so the caller keeps what it had.
     */
    public static function sheetIdFrom(string $input): string
    {
        $text = trim($input);

        if ($text === '') {
            return '';
        }

        if (preg_match('#/spreadsheets/d/([A-Za-z0-9_-]{20,})#', $text, $found) === 1) {
            return $found[1];
        }

        return preg_match('/^[A-Za-z0-9_-]{20,}$/', $text) === 1 ? $text : '';
    }

    /** Six-digit hex only: three-digit shorthand and named colours are not worth the branch. */
    public static function isHexColour(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($value)) === 1;
    }

    /** @param array<string,mixed> $stored */
    private static function float(array $stored, string $key, float $default): float
    {
        $value = $stored[$key] ?? null;

        return is_numeric($value) ? (float) $value : $default;
    }

    /** @param array<string,mixed> $stored */
    private static function int(array $stored, string $key, int $default): int
    {
        $value = $stored[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
