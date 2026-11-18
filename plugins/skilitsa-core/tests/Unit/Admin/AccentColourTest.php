<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\SettingsForm;
use Skilitsa\Core\Admin\View\SettingsViewModel;
use Skilitsa\Core\Data\Settings;

/**
 * The one setting that is a colour rather than a number.
 *
 * It gets its own file because every rule around it differs from the numeric
 * knobs: no range, a format instead, and a value that reaches the public page.
 */
final class AccentColourTest extends TestCase
{
    public function test_a_valid_hex_colour_is_accepted_and_lowercased(): void
    {
        $submission = SettingsForm::parse(['accent_color' => '#A1B2C3']);

        self::assertTrue($submission->isValid());
        self::assertSame('#a1b2c3', $submission->values['accent_color']);
    }

    public function test_a_value_that_is_not_a_colour_is_rejected(): void
    {
        // Whatever this ends up in - a style attribute, a custom property - a
        // value that is not a colour has no business reaching the page.
        foreach (['red', '#abc', '2f6f4f', '#ggghhh', '#2f6f4f;'] as $bad) {
            $submission = SettingsForm::parse(['accent_color' => $bad]);

            self::assertFalse($submission->isValid(), $bad);
            self::assertNotNull($submission->errorFor('accent_color'), $bad);
        }
    }

    public function test_a_rejected_colour_leaves_the_stored_one_alone(): void
    {
        $submission = SettingsForm::parse(['accent_color' => 'octarine']);

        self::assertSame(Settings::DEFAULT_ACCENT, $submission->values['accent_color']);
    }

    public function test_a_blank_field_falls_back_to_the_default(): void
    {
        $submission = SettingsForm::parse(['accent_color' => '']);

        self::assertTrue($submission->isValid());
        self::assertSame(Settings::DEFAULT_ACCENT, $submission->values['accent_color']);
    }

    public function test_sanitize_refuses_a_bad_stored_value_without_throwing(): void
    {
        // sanitize guards the read path, so it clamps rather than complains.
        $clean = Settings::sanitize(['accent_color' => 'javascript:alert(1)']);

        self::assertSame(Settings::DEFAULT_ACCENT, $clean['accent_color']);
    }

    public function test_it_renders_as_a_colour_field_with_no_range(): void
    {
        $field = SettingsViewModel::build([])->field('accent_color');

        self::assertSame('color', $field->type);
        self::assertNull($field->min);
        self::assertNull($field->max);
        self::assertSame(Settings::DEFAULT_ACCENT, $field->value);
    }

    public function test_a_changed_colour_is_marked_changed(): void
    {
        $model = SettingsViewModel::build(['accent_color' => '#123456'] + Settings::defaults());

        self::assertTrue($model->field('accent_color')->isChanged);
    }
}
