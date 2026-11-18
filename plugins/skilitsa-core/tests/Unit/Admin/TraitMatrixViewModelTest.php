<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\Form\TraitMatrixForm;
use Skilitsa\Core\Admin\View\TraitMatrixViewModel;
use Skilitsa\Core\Data\TraitDefaults;

final class TraitMatrixViewModelTest extends TestCase
{
    /** @return list<array<string,mixed>> */
    private function dbRows(array $overrides = []): array
    {
        $rows = [
            [
                'trait_key' => 'shedding', 'label' => 'Τρίχες',
                'penalty_over' => '1.00', 'penalty_under' => '0.00',
                'global_weight' => '1.00', 'is_tuned' => '0',
            ],
            [
                'trait_key' => 'barking', 'label' => 'Γάβγισμα',
                'penalty_over' => '1.50', 'penalty_under' => '0.00',
                'global_weight' => '1.00', 'is_tuned' => '0',
            ],
        ];

        return array_merge($rows, $overrides);
    }

    public function test_it_renders_one_row_per_trait(): void
    {
        self::assertCount(2, TraitMatrixViewModel::build($this->dbRows())->rows);
    }

    public function test_an_untouched_row_reads_as_following_the_defaults(): void
    {
        $row = TraitMatrixViewModel::build($this->dbRows())->row('shedding');

        self::assertSame('default', $row->state);
        self::assertFalse($row->isTuned);
    }

    public function test_a_tuned_row_says_so(): void
    {
        $rows    = $this->dbRows();
        $rows[0] = ['trait_key' => 'shedding', 'label' => 'Τρίχες', 'penalty_over' => '2.00',
                    'penalty_under' => '0.00', 'global_weight' => '1.00', 'is_tuned' => '1'];

        $row = TraitMatrixViewModel::build($rows)->row('shedding');

        self::assertSame('tuned', $row->state);
        self::assertTrue($row->isTuned);
    }

    public function test_every_row_shows_the_code_default_beside_the_value(): void
    {
        $row = TraitMatrixViewModel::build($this->dbRows())->row('barking');

        self::assertSame('1.5', $row->over);
        self::assertSame('1.5', $row->defaultOver);
        self::assertSame('0', $row->defaultUnder);
    }

    public function test_the_rationale_is_shown_as_text_not_hidden(): void
    {
        // The owner is dyslexic and reads this screen rarely. The reason a
        // number is what it is has to be on the page, not behind a tooltip.
        $row = TraitMatrixViewModel::build($this->dbRows())->row('barking');

        self::assertSame(TraitDefaults::noteFor('barking'), $row->rationale);
        self::assertNotSame('', $row->rationale);
    }

    public function test_a_trait_the_code_does_not_know_still_renders(): void
    {
        $rows = $this->dbRows([[
            'trait_key' => 'new_trait_from_the_sheet', 'label' => 'Νέο',
            'penalty_over' => '1.00', 'penalty_under' => '1.00',
            'global_weight' => '1.00', 'is_tuned' => '1',
        ]]);

        $row = TraitMatrixViewModel::build($rows)->row('new_trait_from_the_sheet');

        self::assertSame('', $row->defaultOver);
        self::assertSame('', $row->rationale);
        self::assertTrue($row->isTuned);
    }

    public function test_it_counts_how_many_rows_a_human_has_touched(): void
    {
        $rows    = $this->dbRows();
        $rows[0]['is_tuned'] = '1';

        self::assertSame(1, TraitMatrixViewModel::build($rows)->tunedCount());
    }

    public function test_a_rejected_submission_repopulates_and_shows_the_error(): void
    {
        $submission = TraitMatrixForm::parse(['traits' => [
            'shedding' => ['over' => '9', 'under' => '0', 'weight' => '1'],
        ]]);

        $model = TraitMatrixViewModel::build($this->dbRows(), $submission);

        self::assertSame('9', $model->row('shedding')->over);
        self::assertNotNull($model->row('shedding')->errorOver);
        self::assertNull($model->row('barking')->errorOver);
        self::assertTrue($model->hasErrors());
    }

    public function test_without_a_submission_there_are_no_errors(): void
    {
        self::assertFalse(TraitMatrixViewModel::build($this->dbRows())->hasErrors());
    }

    public function test_rows_keep_the_order_they_arrived_in(): void
    {
        // sort_order on the table is the owner's reading order; re-sorting it
        // alphabetically here would scramble a screen he navigates by position.
        $keys = array_map(
            static fn ($r) => $r->key,
            TraitMatrixViewModel::build($this->dbRows())->rows
        );

        self::assertSame(['shedding', 'barking'], $keys);
    }
}
