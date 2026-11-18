<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\Task\MatrixQaTask;

/**
 * The first real task: read the matrix, report contradictions.
 *
 * Everything here is pure. The model's answer is data arriving from outside the
 * system, and it is treated exactly like the Google Sheet is treated - checked
 * against what actually exists before any of it is kept.
 */
final class MatrixQaTaskTest extends TestCase
{
    /** @return array<string,array<string,string>> */
    private function traits(): array
    {
        return [
            'size'                  => ['label' => 'Μέγεθος', 'scale_low' => 'Πολύ μικρό', 'scale_high' => 'Γίγαντας'],
            'prey_drive_reactivity' => ['label' => 'Κυνηγετικό', 'scale_low' => 'Χαμηλή', 'scale_high' => 'Υψηλή'],
        ];
    }

    /** @return array<string,array{name: string, values: array<string,float>}> */
    private function breeds(): array
    {
        return [
            'akita'     => ['name' => 'Ακίτα', 'values' => ['size' => 4.0, 'prey_drive_reactivity' => 2.0]],
            'greyhound' => ['name' => 'Γκρέιχαουντ', 'values' => ['size' => 4.0, 'prey_drive_reactivity' => 5.0]],
        ];
    }

    private function task(): MatrixQaTask
    {
        return new MatrixQaTask();
    }

    /** @param list<array<string,mixed>> $findings */
    private function read(array $findings): \Skilitsa\Core\Ai\Task\TaskResult
    {
        return $this->task()->readResponse(
            ['findings' => $findings],
            $this->breeds(),
            array_keys($this->traits()),
            'claude-opus-5'
        );
    }

    public function test_the_prompt_carries_the_scale_each_number_means(): void
    {
        // A suggestion is only anchored if the model knows 5 means "giant" and
        // not "good". Without the scale it is guessing at a number line.
        $prompt = $this->task()->userPrompt($this->traits(), $this->breeds());

        self::assertStringContainsString('Πολύ μικρό', $prompt);
        self::assertStringContainsString('Γίγαντας', $prompt);
        self::assertStringContainsString('akita', $prompt);
    }

    public function test_the_prompt_states_the_shape_of_the_answer(): void
    {
        self::assertStringContainsString('findings', $this->task()->systemPrompt());
    }

    public function test_the_system_prompt_forbids_inventing_breeds(): void
    {
        self::assertStringContainsString('only', strtolower($this->task()->systemPrompt()));
    }

    public function test_a_good_finding_becomes_a_draft(): void
    {
        $result = $this->read([
            ['breed' => 'akita', 'trait' => 'size', 'suggested' => 5, 'why' => 'Akitas are large.'],
        ]);

        self::assertTrue($result->ok);
        self::assertCount(1, $result->drafts);

        $draft = $result->drafts[0];
        self::assertSame('akita', $draft->targetKey);
        self::assertSame('size', $draft->field);
        self::assertSame('4', $draft->currentValue);
        self::assertSame('5', $draft->suggestedValue);
        self::assertSame('claude-opus-5', $draft->model);
        self::assertStringContainsString('Akitas are large', $draft->note);
    }

    public function test_a_breed_that_does_not_exist_is_dropped_with_a_warning(): void
    {
        // A model naming a breed nobody has is the clearest sign the answer is
        // improvised, and the last point at which it is cheap to notice.
        $result = $this->read([
            ['breed' => 'direwolf', 'trait' => 'size', 'suggested' => 5, 'why' => 'Very large.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
        self::assertStringContainsString('direwolf', $result->warnings[0]);
    }

    public function test_a_trait_that_does_not_exist_is_dropped_with_a_warning(): void
    {
        $result = $this->read([
            ['breed' => 'akita', 'trait' => 'loyalty', 'suggested' => 5, 'why' => 'Loyal.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_a_suggestion_equal_to_the_current_value_is_dropped(): void
    {
        // Not a finding. It would only ever waste the reviewer's attention.
        $result = $this->read([
            ['breed' => 'akita', 'trait' => 'size', 'suggested' => 4, 'why' => 'Seems right.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertSame([], $result->warnings);
    }

    public function test_a_value_off_the_scale_is_dropped_before_it_can_be_stored(): void
    {
        $result = $this->read([
            ['breed' => 'akita', 'trait' => 'size', 'suggested' => 9, 'why' => 'Huge.'],
            ['breed' => 'akita', 'trait' => 'size', 'suggested' => 0, 'why' => 'Tiny.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(2, $result->warnings);
    }

    public function test_a_finding_without_a_reason_is_dropped(): void
    {
        // An unexplained number is unreviewable, and reviewing is the whole
        // point of the draft.
        $result = $this->read([
            ['breed' => 'akita', 'trait' => 'size', 'suggested' => 5, 'why' => '   '],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_a_response_without_findings_is_a_clean_empty_result(): void
    {
        // "I found nothing" is a legitimate and common answer.
        $result = $this->task()->readResponse([], $this->breeds(), array_keys($this->traits()), 'm');

        self::assertTrue($result->ok);
        self::assertSame([], $result->drafts);
    }

    public function test_a_breed_with_no_value_yet_can_still_be_drafted_for(): void
    {
        $result = $this->task()->readResponse(
            ['findings' => [['breed' => 'akita', 'trait' => 'size', 'suggested' => 3, 'why' => 'Medium.']]],
            ['akita' => ['name' => 'Ακίτα', 'values' => []]],
            ['size'],
            'm'
        );

        self::assertCount(1, $result->drafts);
        self::assertSame('', $result->drafts[0]->currentValue);
    }

    public function test_reading_the_same_answer_twice_gives_the_same_drafts(): void
    {
        // Pure: no clock, no randomness, no ordering surprise.
        $findings = [['breed' => 'akita', 'trait' => 'size', 'suggested' => 5, 'why' => 'Large.']];

        self::assertSame(
            $this->read($findings)->drafts[0]->fingerprint(),
            $this->read($findings)->drafts[0]->fingerprint()
        );
    }

    public function test_the_matrix_is_split_into_runs_that_fit(): void
    {
        // 257 breeds do not fit in one answer. Chunking is part of the task,
        // not something the caller has to remember.
        $chunks = MatrixQaTask::chunk(array_fill_keys(range(1, 10), ['name' => 'x', 'values' => []]), 4);

        self::assertCount(3, $chunks);
        self::assertCount(4, $chunks[0]);
        self::assertCount(2, $chunks[2]);
    }
}
