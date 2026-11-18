<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use DomainException;
use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\Draft\Draft;

/**
 * A suggestion, and everything needed to judge it later.
 *
 * The owner's firmest product principle is that no generative output reaches a
 * user. The draft is what makes that structural rather than aspirational: a
 * task can only ever produce one of these, and only a person can move it.
 */
final class DraftTest extends TestCase
{
    private function draft(string $suggested = '4', string $current = '2'): Draft
    {
        return Draft::create(
            task: 'matrix_qa',
            targetType: Draft::TARGET_BREED_TRAIT,
            targetKey: 'italian-greyhound',
            field: 'size',
            currentValue: $current,
            suggestedValue: $suggested,
            model: 'claude-opus-5',
            note: 'Rated 5 for calmness and 5 for reactivity.',
            createdAt: '2026-08-24 10:00:00',
        );
    }

    public function test_a_draft_records_what_it_would_replace(): void
    {
        // A suggestion without the value it displaces is unreviewable: there is
        // no way to tell an improvement from a coin flip.
        $draft = $this->draft();

        self::assertSame('2', $draft->currentValue);
        self::assertSame('4', $draft->suggestedValue);
        self::assertSame('claude-opus-5', $draft->model);
        self::assertSame('2026-08-24 10:00:00', $draft->createdAt);
        self::assertNotSame('', $draft->note);
    }

    public function test_a_new_draft_is_pending(): void
    {
        self::assertSame(Draft::PENDING, $this->draft()->status);
        self::assertNull($this->draft()->reviewedAt);
    }

    public function test_approving_is_the_only_route_to_approved(): void
    {
        $approved = $this->draft()->approve('2026-08-24 11:00:00');

        self::assertSame(Draft::APPROVED, $approved->status);
        self::assertSame('2026-08-24 11:00:00', $approved->reviewedAt);
    }

    public function test_rejecting_records_the_rejection(): void
    {
        $rejected = $this->draft()->reject('2026-08-24 11:00:00');

        self::assertSame(Draft::REJECTED, $rejected->status);
        self::assertSame('2026-08-24 11:00:00', $rejected->reviewedAt);
    }

    public function test_a_draft_can_only_be_judged_once(): void
    {
        // Re-approving a rejected suggestion is how a decision gets quietly
        // reversed by whoever clicked last.
        $rejected = $this->draft()->reject('2026-08-24 11:00:00');

        $this->expectException(DomainException::class);

        $rejected->approve('2026-08-24 12:00:00');
    }

    public function test_a_draft_is_stale_when_the_world_moved_under_it(): void
    {
        // Somebody edited size to 3 after the task ran. Approving would silently
        // overwrite a human's edit with a model's older opinion.
        $draft = $this->draft(current: '2');

        self::assertTrue($draft->isStaleAgainst('3'));
        self::assertFalse($draft->isStaleAgainst('2'));
    }

    public function test_numeric_comparison_survives_formatting(): void
    {
        // 2 and 2.00 are the same value; the database returns the second.
        self::assertFalse($this->draft(current: '2')->isStaleAgainst('2.00'));
    }

    public function test_a_stale_draft_cannot_be_approved(): void
    {
        $draft = $this->draft(current: '2')->markStale();

        $this->expectException(DomainException::class);

        $draft->approve('2026-08-24 12:00:00');
    }

    public function test_a_stale_draft_can_still_be_rejected(): void
    {
        // Clearing it out must always be available; it is the safe direction.
        $draft = $this->draft(current: '2')->markStale();

        self::assertSame(Draft::REJECTED, $draft->reject('2026-08-24 12:00:00')->status);
    }

    public function test_the_same_suggestion_twice_is_one_draft(): void
    {
        // Running matrix QA weekly must not build a pile of identical rows.
        self::assertSame($this->draft()->fingerprint(), $this->draft()->fingerprint());
    }

    public function test_a_different_suggestion_is_a_different_draft(): void
    {
        self::assertNotSame($this->draft('4')->fingerprint(), $this->draft('5')->fingerprint());
    }

    public function test_a_suggestion_identical_to_the_current_value_is_refused(): void
    {
        // Not a finding. Storing it wastes the reviewer's only scarce resource.
        $this->expectException(DomainException::class);

        $this->draft(suggested: '2', current: '2');
    }

    public function test_an_unknown_target_type_is_refused(): void
    {
        $this->expectException(DomainException::class);

        Draft::create(
            task: 'matrix_qa',
            targetType: 'the_home_page',
            targetKey: 'x',
            field: 'y',
            currentValue: '1',
            suggestedValue: '2',
            model: 'm',
        );
    }
}
