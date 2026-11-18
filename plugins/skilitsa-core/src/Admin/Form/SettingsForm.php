<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

use Skilitsa\Core\Data\Settings;

/**
 * Reads a settings form. Pure: no WordPress, no superglobals, no writes.
 *
 * Deliberately stricter than Settings::sanitize(). That method clamps, because
 * it guards the read path and a bad stored value must never take the site
 * down. Here somebody is watching, and silently turning a typed 50 into 5
 * teaches them the box does nothing.
 */
final class SettingsForm
{
    /** @param array<string,mixed> $post */
    public static function parse(array $post): SettingsSubmission
    {
        $defaults = Settings::defaults();
        $bounds   = Settings::bounds();

        $values = $defaults;
        $errors = [];
        $raw    = [];

        foreach ($defaults as $key => $default) {
            if (!array_key_exists($key, $post)) {
                continue;
            }

            $submitted   = is_scalar($post[$key]) ? (string) $post[$key] : '';
            $raw[$key]   = $submitted;
            $trimmed     = trim($submitted);

            // Blank means "leave it alone", never "zero".
            if ($trimmed === '') {
                continue;
            }

            if ($key === 'sheet_id') {
                $id = Settings::sheetIdFrom($trimmed);

                if ($id === '') {
                    $errors[] = new FieldError(
                        $key,
                        'Paste the sheet address, or its ID. This does not look like either.'
                    );
                    continue;
                }

                $values[$key] = $id;
                continue;
            }

            if ($key === 'accent_color') {
                if (!Settings::isHexColour($trimmed)) {
                    $errors[] = new FieldError($key, 'Enter a colour as six hex digits, like #2f6f4f.');
                    continue;
                }

                $values[$key] = strtolower($trimmed);
                continue;
            }

            if ($key === 'flow_mode') {
                if (!in_array($trimmed, Settings::FLOW_MODES, true)) {
                    $errors[] = new FieldError($key, 'Pick one of the available flows.');
                    continue;
                }

                $values[$key] = $trimmed;
                continue;
            }

            if (!is_numeric($trimmed)) {
                $errors[] = new FieldError($key, 'Enter a number.');
                continue;
            }

            $number = (float) $trimmed;

            if (is_int($default) && floor($number) !== $number) {
                $errors[] = new FieldError($key, 'Enter a whole number, no decimals.');
                continue;
            }

            [$min, $max] = $bounds[$key];

            if ($number < $min || $number > $max) {
                $errors[] = new FieldError(
                    $key,
                    sprintf('Enter a value between %s and %s.', self::format($min), self::format($max))
                );
                continue;
            }

            $values[$key] = is_int($default) ? (int) $number : $number;
        }

        return new SettingsSubmission($values, $errors, $raw);
    }

    private static function format(float|int $number): string
    {
        return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.');
    }
}
