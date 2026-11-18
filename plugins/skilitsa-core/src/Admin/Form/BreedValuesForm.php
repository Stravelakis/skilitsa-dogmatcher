<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

/**
 * Reads one breed's 24 trait values. Pure.
 *
 * The scale is 1-5 and means something to a reader of the quiz, so a value
 * outside it is not a rounding problem, it is a wrong answer: rejected, never
 * clamped. A blank box leaves that trait as it was rather than writing a
 * number nobody chose - which is how a breed ends up rated 0 for everything.
 */
final class BreedValuesForm
{
    private const MIN = 1.0;
    private const MAX = 5.0;

    /**
     * @param array<string,mixed> $post
     * @param list<string>        $traitKeys Only these are accepted.
     */
    public static function parse(array $post, array $traitKeys): BreedValuesSubmission
    {
        $submitted = is_array($post['traits'] ?? null) ? $post['traits'] : [];

        $values = [];
        $errors = [];
        $raw    = [];

        foreach ($traitKeys as $key) {
            if (!array_key_exists($key, $submitted)) {
                continue;
            }

            $text      = is_scalar($submitted[$key]) ? (string) $submitted[$key] : '';
            $raw[$key] = $text;
            $trimmed   = trim($text);

            if ($trimmed === '') {
                continue;
            }

            if (!is_numeric($trimmed)) {
                $errors[] = new FieldError($key, 'Enter a number from 1 to 5.');
                continue;
            }

            $number = (float) $trimmed;

            if ($number < self::MIN || $number > self::MAX) {
                $errors[] = new FieldError($key, 'Enter a number from 1 to 5.');
                continue;
            }

            $values[$key] = $number;
        }

        return new BreedValuesSubmission($values, $errors, $raw);
    }
}
