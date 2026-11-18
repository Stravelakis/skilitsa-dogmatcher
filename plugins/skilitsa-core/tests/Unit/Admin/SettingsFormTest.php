<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\SettingsForm;
use Skilitsa\Core\Data\Settings;

/**
 * What a settings submission means, decided without WordPress.
 *
 * The rule this whole class exists to enforce: bad input is REPORTED, never
 * absorbed. Settings::sanitize() clamps, because it guards the read path and a
 * stored bad value must not take the site down. A form is the opposite
 * situation - somebody is watching - and silently turning a typed 50 into 5
 * teaches the owner that the box does nothing.
 */
final class SettingsFormTest extends TestCase
{
    public function test_a_clean_submission_parses_to_values(): void
    {
        $submission = SettingsForm::parse([
            'gamma'       => '0.9',
            'shrinkage_m' => '2',
            'flow_mode'   => 'tiered',
        ]);

        self::assertTrue($submission->isValid());
        self::assertSame(0.9, $submission->values['gamma']);
        self::assertSame(2.0, $submission->values['shrinkage_m']);
        self::assertSame('tiered', $submission->values['flow_mode']);
    }

    public function test_a_value_outside_its_range_is_rejected_not_clamped(): void
    {
        $submission = SettingsForm::parse(['gamma' => '50']);

        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('gamma'));

        // The stored value stays what it was; nothing is written from an
        // invalid submission.
        self::assertSame(Settings::defaults()['gamma'], $submission->values['gamma']);
    }

    public function test_a_non_numeric_value_is_rejected_and_echoed_back(): void
    {
        // Losing what someone typed is how a form makes them retype a whole
        // screen to find the one field it disliked.
        $submission = SettingsForm::parse(['gamma' => 'very strict please']);

        self::assertFalse($submission->isValid());
        self::assertSame('very strict please', $submission->raw['gamma']);
    }

    public function test_an_empty_field_falls_back_to_the_default(): void
    {
        // 0 is a legal-looking number here. A blank box must never be read as
        // "the strictest possible setting".
        $submission = SettingsForm::parse(['gamma' => '', 'shrinkage_m' => '   ']);

        self::assertTrue($submission->isValid());
        self::assertSame(Settings::defaults()['gamma'], $submission->values['gamma']);
        self::assertSame(Settings::defaults()['shrinkage_m'], $submission->values['shrinkage_m']);
    }

    public function test_a_missing_field_is_left_at_its_default(): void
    {
        $submission = SettingsForm::parse([]);

        self::assertTrue($submission->isValid());
        self::assertSame(Settings::defaults(), $submission->values);
    }

    public function test_an_integer_field_rejects_a_fraction(): void
    {
        // Rounding 4.6 to 5 behind the owner's back is the same lie as clamping.
        $submission = SettingsForm::parse(['close_call_points' => '4.6']);

        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('close_call_points'));
    }

    public function test_an_integer_field_accepts_a_whole_number(): void
    {
        $submission = SettingsForm::parse(['close_call_points' => '6']);

        self::assertTrue($submission->isValid());
        self::assertSame(6, $submission->values['close_call_points']);
    }

    public function test_an_unknown_flow_mode_is_rejected(): void
    {
        $submission = SettingsForm::parse(['flow_mode' => 'adaptive']);

        // adaptive is in the spec and is not built. Accepting it here would
        // ship a quiz that renders nothing.
        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('flow_mode'));
    }

    public function test_errors_name_the_field_they_belong_to(): void
    {
        $submission = SettingsForm::parse(['gamma' => '50', 'flow_mode' => 'nope']);

        self::assertCount(2, $submission->errors);
        self::assertNull($submission->errorFor('shrinkage_m'));

        $fields = array_map(static fn ($e) => $e->field, $submission->errors);
        self::assertContains('gamma', $fields);
        self::assertContains('flow_mode', $fields);
    }

    public function test_messages_are_english(): void
    {
        // Language rule: the admin is English. Only the public site is Greek,
        // and only content quoted from it appears in both.
        $submission = SettingsForm::parse(['gamma' => 'x', 'close_call_points' => '999']);

        foreach ($submission->errors as $error) {
            self::assertDoesNotMatchRegularExpression('/\p{Greek}/u', $error->message, $error->field);
            self::assertNotSame('', trim($error->message), $error->field);
        }
    }

    public function test_a_field_outside_the_known_settings_is_ignored(): void
    {
        $submission = SettingsForm::parse(['gamma' => '0.9', 'drop_database' => '1']);

        self::assertTrue($submission->isValid());
        self::assertArrayNotHasKey('drop_database', $submission->values);
    }
}
