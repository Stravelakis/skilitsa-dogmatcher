<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

use Skilitsa\Core\Data\TraitDefaults;

/**
 * Reads the trait matrix form. Pure: no WordPress, no superglobals, no writes.
 *
 * This class owns the only correct answer to "did a human decide this?".
 * `is_tuned` is derived here, by comparing what was submitted against the code
 * defaults, and set nowhere else. A row that equals its default is deliberately
 * marked untuned so it rejoins the defaults and picks up future corrections to
 * them; a row that differs is protected from the next import.
 */
final class TraitMatrixForm
{
    private const PENALTY_MIN = 0.0;
    private const PENALTY_MAX = 3.0;
    private const WEIGHT_MIN  = 0.0;
    private const WEIGHT_MAX  = 5.0;

    /** Weight has no per-trait default; anything but this is a judgement. */
    private const DEFAULT_WEIGHT = 1.0;

    /** @param array<string,mixed> $post */
    public static function parse(array $post): TraitMatrixSubmission
    {
        $traits = is_array($post['traits'] ?? null) ? $post['traits'] : [];

        $rows      = [];
        $errors    = [];
        $raw       = [];
        $resetKeys = [];

        foreach ($traits as $key => $fields) {
            $key = (string) $key;

            if (!is_array($fields)) {
                continue;
            }

            $defaults = TraitDefaults::PENALTIES[$key] ?? null;
            $fallback = [
                'over'   => $defaults['over'] ?? 1.0,
                'under'  => $defaults['under'] ?? 1.0,
                'weight' => self::DEFAULT_WEIGHT,
            ];

            $values   = [];
            $rowError = false;

            foreach (['over', 'under', 'weight'] as $subField) {
                $submitted        = is_scalar($fields[$subField] ?? null) ? (string) $fields[$subField] : '';
                $raw[$key][$subField] = $submitted;
                $trimmed          = trim($submitted);

                // Blank or absent means "leave it as it was", never zero.
                if ($trimmed === '') {
                    $values[$subField] = $fallback[$subField];
                    continue;
                }

                if (!is_numeric($trimmed)) {
                    $errors[] = new FieldError(
                        TraitMatrixSubmission::fieldName($key, $subField),
                        'Enter a number.'
                    );
                    $rowError = true;
                    continue;
                }

                $number = (float) $trimmed;
                $min    = $subField === 'weight' ? self::WEIGHT_MIN : self::PENALTY_MIN;
                $max    = $subField === 'weight' ? self::WEIGHT_MAX : self::PENALTY_MAX;

                if ($number < $min || $number > $max) {
                    $errors[] = new FieldError(
                        TraitMatrixSubmission::fieldName($key, $subField),
                        sprintf('Enter a value between %s and %s.', self::format($min), self::format($max))
                    );
                    $rowError = true;
                    continue;
                }

                $values[$subField] = $number;
            }

            // A row with any bad box is not saved at all. Writing the half of
            // it that parsed would leave the matrix in a state nobody chose.
            if ($rowError) {
                continue;
            }

            $isTuned = self::isTuned($key, $values, $defaults);

            if (!$isTuned) {
                $resetKeys[] = $key;
            }

            $rows[$key] = [
                'over'     => $values['over'],
                'under'    => $values['under'],
                'weight'   => $values['weight'],
                'is_tuned' => $isTuned,
            ];
        }

        return new TraitMatrixSubmission($rows, $errors, $raw, $resetKeys);
    }

    /**
     * @param array{over: float, under: float, weight: float}     $values
     * @param array{over: float, under: float, note: string}|null $defaults
     */
    private static function isTuned(string $key, array $values, ?array $defaults): bool
    {
        // No code default means nothing to re-seed this row from, so it must
        // never be re-seeded: treat it as a standing human decision.
        if ($defaults === null) {
            return true;
        }

        return $values['over'] !== $defaults['over']
            || $values['under'] !== $defaults['under']
            || $values['weight'] !== self::DEFAULT_WEIGHT;
    }

    private static function format(float $number): string
    {
        return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
    }
}
