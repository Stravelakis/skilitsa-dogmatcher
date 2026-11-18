<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\Draft\Draft;
use Skilitsa\Core\Ai\Draft\DraftReview;

/**
 * Approving a draft, and the wall that makes the whole design hold.
 *
 * Approval does not write. It produces the exact input the human admin form
 * would have received, and hands it to that form to validate. A model therefore
 * cannot write a value a person could not have typed, and every range check and
 * `is_tuned` rule already written applies to a model's suggestion unchanged.
 */
final class DraftReviewTest extends TestCase
{
    private function traitDraft(string $suggested = '5'): Draft
    {
        return Draft::create(
            task: 'matrix_qa',
            targetType: Draft::TARGET_BREED_TRAIT,
            targetKey: 'akita',
            field: 'size',
            currentValue: '4',
            suggestedValue: $suggested,
            model: 'claude-opus-5',
            note: 'Akitas are large.',
        );
    }

    private function penaltyDraft(string $field = 'penalty_over', string $suggested = '1.25'): Draft
    {
        return Draft::create(
            task: 'check_penalties',
            targetType: Draft::TARGET_TRAIT_PENALTY,
            targetKey: 'barking',
            field: $field,
            currentValue: '1.5',
            suggestedValue: $suggested,
            model: 'claude-opus-5',
            note: 'Barking is already charged through alertness.',
        );
    }

    public function test_approving_a_breed_trait_produces_what_the_form_would_have_received(): void
    {
        $outcome = DraftReview::approve($this->traitDraft(), ['traitKeys' => ['size', 'barking']]);

        self::assertTrue($outcome->ok);
        self::assertSame(['size' => 5.0], $outcome->values);
        self::assertSame(Draft::APPROVED, $outcome->draft->status);
    }

    public function test_a_value_the_human_form_would_reject_is_rejected_here_too(): void
    {
        // A hand-edited draft row, or a task that stopped filtering. The form is
        // the authority either way.
        $outcome = DraftReview::approve($this->traitDraft('9'), ['traitKeys' => ['size']]);

        self::assertFalse($outcome->ok);
        self::assertSame([], $outcome->values);
        self::assertNotSame('', $outcome->failure);
    }

    public function test_a_trait_the_matrix_does_not_have_is_refused(): void
    {
        $outcome = DraftReview::approve($this->traitDraft(), ['traitKeys' => ['barking']]);

        self::assertFalse($outcome->ok);
    }

    public function test_approving_a_penalty_marks_the_row_as_tuned(): void
    {
        // A human approved it, so it is a human decision, and the next import
        // must not re-seed it from the code defaults.
        $outcome = DraftReview::approve($this->penaltyDraft(), [
            'row' => ['over' => 1.5, 'under' => 0.25, 'weight' => 1.0],
        ]);

        self::assertTrue($outcome->ok);
        self::assertSame(1.25, $outcome->values['barking']['over']);
        self::assertSame(0.25, $outcome->values['barking']['under']);
        self::assertTrue($outcome->values['barking']['is_tuned']);
    }

    public function test_approving_a_penalty_leaves_the_rest_of_the_row_alone(): void
    {
        $outcome = DraftReview::approve($this->penaltyDraft('penalty_under', '0.5'), [
            'row' => ['over' => 1.5, 'under' => 0.25, 'weight' => 2.0],
        ]);

        self::assertSame(1.5, $outcome->values['barking']['over']);
        self::assertSame(0.5, $outcome->values['barking']['under']);
        self::assertSame(2.0, $outcome->values['barking']['weight']);
    }

    public function test_an_out_of_range_penalty_is_refused_by_the_matrix_form(): void
    {
        $outcome = DraftReview::approve($this->penaltyDraft('penalty_over', '9'), [
            'row' => ['over' => 1.5, 'under' => 0.25, 'weight' => 1.0],
        ]);

        self::assertFalse($outcome->ok);
    }

    public function test_a_stale_draft_cannot_be_approved(): void
    {
        $outcome = DraftReview::approve($this->traitDraft()->markStale(), ['traitKeys' => ['size']]);

        self::assertFalse($outcome->ok);
        self::assertStringContainsString('stale', strtolower($outcome->failure));
    }

    public function test_rejecting_records_the_rejection_and_writes_nothing(): void
    {
        $outcome = DraftReview::reject($this->traitDraft());

        self::assertTrue($outcome->ok);
        self::assertSame([], $outcome->values);
        self::assertSame(Draft::REJECTED, $outcome->draft->status);
    }

    public function test_a_copy_draft_is_not_approvable_yet(): void
    {
        // Translation and alt text land in a later task. Refusing loudly beats
        // writing copy through a path nobody has reviewed.
        $draft = Draft::create(
            task: 'translate',
            targetType: Draft::TARGET_COPY,
            targetKey: 'pos_size_1',
            field: 'body',
            currentValue: 'a',
            suggestedValue: 'b',
            model: 'm',
            note: 'n',
        );

        self::assertFalse(DraftReview::approve($draft, [])->ok);
    }
}
