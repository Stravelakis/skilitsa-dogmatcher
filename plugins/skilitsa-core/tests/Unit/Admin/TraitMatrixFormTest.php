<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\TraitMatrixForm;

/**
 * The screen that changes what the site recommends.
 *
 * The rule under test: `is_tuned` means "a human decided this". It is set here
 * and nowhere else. Anything that sets it from a script makes the flag a lie
 * and re-opens the bug it exists to close - an import silently replacing an
 * evening of judgement with the code defaults.
 */
final class TraitMatrixFormTest extends TestCase
{
    /** @param array<string,array<string,string>> $traits */
    private function post(array $traits): array
    {
        return ['traits' => $traits];
    }

    public function test_a_row_moved_off_its_default_is_marked_tuned(): void
    {
        // shedding defaults to 1.0 / 0.0.
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '1.5', 'under' => '0', 'weight' => '1'],
        ]));

        self::assertTrue($submission->isValid());
        self::assertSame(1.5, $submission->rows['shedding']['over']);
        self::assertTrue($submission->rows['shedding']['is_tuned']);
    }

    public function test_a_row_matching_its_default_is_not_marked_tuned(): void
    {
        // Rejoining the defaults means picking up future corrections to them.
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '1', 'under' => '0', 'weight' => '1'],
        ]));

        self::assertTrue($submission->isValid());
        self::assertFalse($submission->rows['shedding']['is_tuned']);
        self::assertContains('shedding', $submission->resetKeys);
    }

    public function test_a_changed_weight_alone_tunes_the_row(): void
    {
        // Weight has no per-trait default; anything but 1.0 is a decision.
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '1', 'under' => '0', 'weight' => '2'],
        ]));

        self::assertTrue($submission->rows['shedding']['is_tuned']);
    }

    public function test_only_the_edited_row_is_touched(): void
    {
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '1.5', 'under' => '0', 'weight' => '1'],
            'grooming' => ['over' => '1',   'under' => '0', 'weight' => '1'],
        ]));

        self::assertTrue($submission->rows['shedding']['is_tuned']);
        self::assertFalse($submission->rows['grooming']['is_tuned']);
    }

    public function test_a_penalty_outside_its_range_is_rejected(): void
    {
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '9', 'under' => '0', 'weight' => '1'],
        ]));

        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('shedding', 'over'));
        self::assertNull($submission->errorFor('shedding', 'under'));
    }

    public function test_a_weight_outside_its_range_is_rejected(): void
    {
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '1', 'under' => '0', 'weight' => '9'],
        ]));

        self::assertFalse($submission->isValid());
        self::assertNotNull($submission->errorFor('shedding', 'weight'));
    }

    public function test_a_non_numeric_value_is_rejected_and_echoed_back(): void
    {
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => 'lots', 'under' => '0', 'weight' => '1'],
        ]));

        self::assertFalse($submission->isValid());
        self::assertSame('lots', $submission->raw['shedding']['over']);
    }

    public function test_a_rejected_row_is_not_offered_for_saving(): void
    {
        // Half-saving a row would leave the matrix in a state nobody chose.
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '9', 'under' => '0', 'weight' => '1'],
            'grooming' => ['over' => '1', 'under' => '0', 'weight' => '1'],
        ]));

        self::assertArrayNotHasKey('shedding', $submission->rows);
        self::assertArrayHasKey('grooming', $submission->rows);
    }

    public function test_a_trait_the_code_has_no_default_for_is_always_tuned(): void
    {
        // The sheet can add a trait before the code knows about it. There is
        // nothing to re-seed such a row from, so it must never be re-seeded.
        $submission = TraitMatrixForm::parse($this->post([
            'new_trait_from_the_sheet' => ['over' => '1', 'under' => '1', 'weight' => '1'],
        ]));

        self::assertTrue($submission->isValid());
        self::assertTrue($submission->rows['new_trait_from_the_sheet']['is_tuned']);
    }

    public function test_a_missing_sub_field_falls_back_rather_than_writing_zero(): void
    {
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => '1.5'],
        ]));

        self::assertTrue($submission->isValid());
        self::assertSame(0.0, $submission->rows['shedding']['under']);   // the default
        self::assertSame(1.0, $submission->rows['shedding']['weight']);
    }

    public function test_an_empty_post_changes_nothing(): void
    {
        $submission = TraitMatrixForm::parse([]);

        self::assertTrue($submission->isValid());
        self::assertSame([], $submission->rows);
    }

    public function test_messages_are_english(): void
    {
        $submission = TraitMatrixForm::parse($this->post([
            'shedding' => ['over' => 'x', 'under' => '9', 'weight' => '-1'],
        ]));

        foreach ($submission->errors as $error) {
            self::assertDoesNotMatchRegularExpression('/\p{Greek}/u', $error->message, $error->field);
        }
    }
}
