<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\SettingsForm;
use Skilitsa\Core\Admin\View\SettingsViewModel;
use Skilitsa\Core\Data\Settings;

/**
 * What the settings screen renders, decided without WordPress.
 *
 * The template that consumes this may only echo. Anything resembling a
 * decision - which fields changed, what help to show, which error sits under
 * which box - is settled here where it can be tested.
 */
final class SettingsViewModelTest extends TestCase
{
    public function test_it_renders_every_setting(): void
    {
        $model = SettingsViewModel::build([]);

        self::assertSameSize(Settings::defaults(), $model->fields);
    }

    public function test_a_stored_value_equal_to_the_default_is_not_marked_changed(): void
    {
        $model = SettingsViewModel::build(Settings::defaults());

        self::assertSame(0, $model->changedCount());
    }

    public function test_a_stored_value_differing_from_the_default_is_marked_changed(): void
    {
        // "What did I actually change?" has to be answerable at a glance, or
        // the screen becomes 9 numbers with no story.
        $model = SettingsViewModel::build(['gamma' => 1.4] + Settings::defaults());

        self::assertTrue($model->field('gamma')->isChanged);
        self::assertFalse($model->field('shrinkage_m')->isChanged);
        self::assertSame(1, $model->changedCount());
    }

    public function test_every_field_shows_its_default_alongside_its_value(): void
    {
        $model = SettingsViewModel::build(['gamma' => 1.4] + Settings::defaults());

        self::assertSame('1.4', $model->field('gamma')->value);
        self::assertSame('0.7', $model->field('gamma')->default);
    }

    public function test_gamma_help_states_that_lower_is_stricter(): void
    {
        // The direction is counter-intuitive - a normalised cost below 1 gets
        // SMALLER when raised to a higher power - and it is the single knob
        // most likely to be turned the wrong way.
        $help = SettingsViewModel::build([])->field('gamma')->help;

        self::assertStringContainsString('Lower', $help);
        self::assertStringContainsString('stricter', $help);
    }

    public function test_every_field_has_an_english_label_and_help(): void
    {
        // These settings have no front-end text behind them, so English alone
        // is right. A field shipping without help fails here.
        foreach (SettingsViewModel::build([])->fields as $field) {
            self::assertNotSame('', trim($field->label), $field->key);
            self::assertNotSame('', trim($field->help), $field->key);
            self::assertDoesNotMatchRegularExpression('/\p{Greek}/u', $field->label, $field->key);
            self::assertDoesNotMatchRegularExpression('/\p{Greek}/u', $field->help, $field->key);
        }
    }

    public function test_numeric_fields_carry_the_range_they_accept(): void
    {
        $gamma = SettingsViewModel::build([])->field('gamma');

        self::assertSame(0.1, $gamma->min);
        self::assertSame(5.0, $gamma->max);
    }

    public function test_flow_mode_renders_as_a_choice_not_a_number(): void
    {
        $field = SettingsViewModel::build([])->field('flow_mode');

        self::assertSame('choice', $field->type);
        self::assertSame(Settings::FLOW_MODES, array_keys($field->choices));
        self::assertNull($field->min);
    }

    public function test_a_rejected_submission_repopulates_the_boxes_and_shows_the_error(): void
    {
        $submission = SettingsForm::parse(['gamma' => '50', 'shrinkage_m' => '3']);
        $model      = SettingsViewModel::build(Settings::defaults(), $submission);

        // What the owner typed stays on screen, under its own error.
        self::assertSame('50', $model->field('gamma')->value);
        self::assertNotNull($model->field('gamma')->error);
        self::assertNull($model->field('shrinkage_m')->error);
        self::assertTrue($model->hasErrors());
    }

    public function test_without_a_submission_there_are_no_errors(): void
    {
        self::assertFalse(SettingsViewModel::build([])->hasErrors());
    }
}
