<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\Draft\CopyTarget;
use Skilitsa\Core\Ai\Draft\Draft;
use Skilitsa\Core\Ai\Task\CheckPenaltiesTask;
use Skilitsa\Core\Ai\Task\CopyTask;
use Skilitsa\Core\Ai\Task\DraftBreedTraitsTask;

/**
 * The five tasks beyond matrix QA.
 *
 * Each is tested on the same question: what does it throw away? A task that
 * keeps everything a model says is not a task, it is a pipe.
 */
final class TasksTest extends TestCase
{
    /* ------------------------------------------------ draft breed traits */

    private function breedTask(): DraftBreedTraitsTask
    {
        return new DraftBreedTraitsTask();
    }

    private function draftTraits(array $findings, array $missing = ['size', 'barking']): \Skilitsa\Core\Ai\Task\TaskResult
    {
        return $this->breedTask()->readResponse(
            ['findings' => $findings],
            'akita',
            ['name' => 'Ακίτα', 'values' => ['grooming' => 3.0]],
            $missing,
            'a-model'
        );
    }

    public function test_a_missing_trait_gets_a_draft_with_no_current_value(): void
    {
        $result = $this->draftTraits([
            ['trait' => 'size', 'value' => 4, 'why' => 'Large spitz.'],
        ]);

        self::assertCount(1, $result->drafts);
        self::assertSame('', $result->drafts[0]->currentValue);
        self::assertSame('4', $result->drafts[0]->suggestedValue);
    }

    public function test_it_refuses_to_touch_a_trait_that_already_has_a_value(): void
    {
        // This task fills gaps. Overruling a number somebody already checked is
        // a different decision and belongs to a different task.
        $result = $this->draftTraits([
            ['trait' => 'grooming', 'value' => 5, 'why' => 'Heavy coat.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_it_drops_values_off_the_scale_and_unexplained_ones(): void
    {
        $result = $this->draftTraits([
            ['trait' => 'size', 'value' => 9, 'why' => 'Huge.'],
            ['trait' => 'barking', 'value' => 3, 'why' => '  '],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(2, $result->warnings);
    }

    public function test_the_prompt_says_which_values_are_already_known(): void
    {
        $prompt = $this->breedTask()->userPrompt(
            ['size' => ['label' => 'Μέγεθος', 'scale_low' => 'μικρό', 'scale_high' => 'γίγαντας']],
            ['name' => 'Ακίτα', 'values' => ['grooming' => 3.0]],
            ['size']
        );

        self::assertStringContainsString('grooming=3', $prompt);
        self::assertStringContainsString('do not repeat', $prompt);
    }

    /* ---------------------------------------------------- check penalties */

    private function penalties(array $findings): \Skilitsa\Core\Ai\Task\TaskResult
    {
        return (new CheckPenaltiesTask())->readResponse(
            ['findings' => $findings],
            ['barking' => ['label' => 'Γάβγισμα', 'over' => '1.5', 'under' => '0']],
            'a-model'
        );
    }

    public function test_a_penalty_change_becomes_a_draft(): void
    {
        $result = $this->penalties([
            ['trait' => 'barking', 'field' => 'penalty_over', 'value' => 1.0,
             'why' => 'Alertness already charges for the same behaviour.'],
        ]);

        self::assertCount(1, $result->drafts);
        self::assertSame(Draft::TARGET_TRAIT_PENALTY, $result->drafts[0]->targetType);
        self::assertSame('1.5', $result->drafts[0]->currentValue);
        self::assertSame('1', $result->drafts[0]->suggestedValue);
    }

    public function test_agreeing_with_the_current_penalty_is_not_a_finding(): void
    {
        $result = $this->penalties([
            ['trait' => 'barking', 'field' => 'penalty_over', 'value' => 1.5, 'why' => 'Correct as is.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertSame([], $result->warnings);
    }

    public function test_it_refuses_a_field_that_is_not_a_penalty(): void
    {
        $result = $this->penalties([
            ['trait' => 'barking', 'field' => 'global_weight', 'value' => 2, 'why' => 'More important.'],
        ]);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_the_penalty_prompt_carries_the_reasoning_to_argue_with(): void
    {
        $prompt = (new CheckPenaltiesTask())->userPrompt([
            'barking' => ['label' => 'Γάβγισμα', 'over' => '1.5', 'under' => '0'],
        ]);

        self::assertStringContainsString('reasoning:', $prompt);
        self::assertStringContainsString('rehoming', $prompt);
    }

    /* --------------------------------------------------------- copy tasks */

    private function copy(string $task, array $findings, array $current): \Skilitsa\Core\Ai\Task\TaskResult
    {
        return (new CopyTask($task))->readResponse(['findings' => $findings], $current, 'a-model');
    }

    public function test_a_translation_becomes_a_copy_draft(): void
    {
        $result = $this->copy(CopyTask::TRANSLATE, [
            ['target' => 'template:pos_size_1', 'field' => 'body', 'text' => 'Νέο κείμενο',
             'why' => 'The original was English.'],
        ], ['template:pos_size_1|body' => 'Old text']);

        self::assertCount(1, $result->drafts);
        self::assertSame(Draft::TARGET_COPY, $result->drafts[0]->targetType);
        self::assertSame('template:pos_size_1', $result->drafts[0]->targetKey);
    }

    public function test_a_field_outside_the_whitelist_is_refused(): void
    {
        // A template has exactly one writable field. Anything else addressed at
        // it is either a mistake or an attempt to reach further.
        $result = $this->copy(CopyTask::TRANSLATE, [
            ['target' => 'template:pos_size_1', 'field' => 'template_key', 'text' => 'x', 'why' => 'y'],
        ], ['template:pos_size_1|body' => 'Old text']);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_a_target_that_was_not_in_this_run_is_refused(): void
    {
        // The clearest sign an answer was improvised: it is about something the
        // model was never shown.
        $result = $this->copy(CopyTask::TRANSLATE, [
            ['target' => 'template:never_sent', 'field' => 'body', 'text' => 'x', 'why' => 'y'],
        ], ['template:pos_size_1|body' => 'Old text']);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_alt_text_over_125_characters_is_refused(): void
    {
        $long = str_repeat('α', 200);

        $result = $this->copy(CopyTask::ALT_TEXT, [
            ['target' => 'media:5943', 'field' => 'alt', 'text' => $long, 'why' => 'Describes it.'],
        ], ['media:5943|alt' => '']);

        self::assertSame([], $result->drafts);
        self::assertCount(1, $result->warnings);
    }

    public function test_alt_text_within_the_limit_is_kept(): void
    {
        $result = $this->copy(CopyTask::ALT_TEXT, [
            ['target' => 'media:5943', 'field' => 'alt', 'text' => 'Μια σκυλίτσα σε παραλία.',
             'why' => 'That is what the photo shows.'],
        ], ['media:5943|alt' => '']);

        self::assertCount(1, $result->drafts);
    }

    public function test_text_identical_to_the_original_is_not_a_finding(): void
    {
        $result = $this->copy(CopyTask::QUESTION_AUDIT, [
            ['target' => 'question:Q01', 'field' => 'label', 'text' => 'Same', 'why' => 'Fine.'],
        ], ['question:Q01|label' => 'Same']);

        self::assertSame([], $result->drafts);
        self::assertSame([], $result->warnings);
    }

    public function test_each_task_carries_its_own_instructions(): void
    {
        self::assertStringContainsString('σκυλίτσα', (new CopyTask(CopyTask::TRANSLATE))->systemPrompt());
        self::assertStringContainsString('125', (new CopyTask(CopyTask::ALT_TEXT))->systemPrompt());
        self::assertStringContainsString('1 to 5', (new CopyTask(CopyTask::QUESTION_AUDIT))->systemPrompt());
    }

    /* -------------------------------------------------------- copy target */

    public function test_a_copy_target_round_trips(): void
    {
        $target = CopyTarget::of(CopyTarget::TRAIT, 'barking');

        self::assertSame('trait:barking', $target->address());
        self::assertSame('barking', CopyTarget::parse('trait:barking')->key);
    }

    public function test_an_unknown_kind_is_not_a_target(): void
    {
        self::assertNull(CopyTarget::parse('wp_users:1'));
        self::assertNull(CopyTarget::parse('no-colon'));
        self::assertNull(CopyTarget::parse('trait:'));
    }

    public function test_each_kind_allows_only_its_own_fields(): void
    {
        self::assertTrue(CopyTarget::of(CopyTarget::TRAIT, 'x')->allows('warning_too_low'));
        self::assertFalse(CopyTarget::of(CopyTarget::TRAIT, 'x')->allows('penalty_over'));
        self::assertFalse(CopyTarget::of(CopyTarget::MEDIA, '1')->allows('body'));
    }
}
