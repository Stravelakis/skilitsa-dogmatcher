<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Task;

/**
 * The checks every task runs on a model's answer before keeping any of it.
 *
 * A model's reply is data arriving from outside the system and gets the same
 * treatment as the Google Sheet: nothing is kept before it is checked against
 * what actually exists. Five tasks were about to write these rules five times,
 * and the fifth copy is always the one that forgets a case.
 */
final class FindingReader
{
    /**
     * The findings list, or null when the answer had no usable one.
     *
     * An answer with no findings is legitimate and common - "I found nothing"
     * is a real result - so an empty list and a missing list are different
     * things and only the second is worth a warning.
     *
     * @param  array<string,mixed> $data
     * @return list<array<string,mixed>>|null
     */
    public static function findings(array $data, string $key = 'findings'): ?array
    {
        $found = $data[$key] ?? null;

        if (!is_array($found)) {
            return null;
        }

        $out = [];
        foreach ($found as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** Trimmed text from a finding, or '' when it is missing or not a string. */
    public static function text(array $finding, string $key): string
    {
        $value = $finding[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * A number inside an inclusive range, or null.
     *
     * Null covers both "not a number" and "outside the scale", because the
     * caller does the same thing with either: drop it and say why.
     */
    public static function number(array $finding, string $key, float $min, float $max): ?float
    {
        $value = $finding[$key] ?? null;

        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= $min && $number <= $max ? $number : null;
    }

    /** Formats a value the way the rest of the plugin writes numbers. */
    public static function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * Whether a suggested piece of copy is worth a reviewer's attention.
     *
     * Length is not a quality check; it catches the two failures that waste
     * the most time - an empty answer, and an essay where a sentence was asked
     * for.
     */
    public static function isUsableCopy(string $text, int $maxLength = 1200): bool
    {
        return $text !== '' && mb_strlen($text) <= $maxLength;
    }
}
