<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\TaskRegistry;

/**
 * Which model each task uses, and where its key comes from.
 *
 * The rule this file exists to hold: a key lives in wp-config.php and never in
 * the database, because a key in the database travels in every backup and in
 * every copy pulled down to staging. The registry therefore reads constants and
 * never exposes a key value to anything that renders.
 */
final class TaskRegistryTest extends TestCase
{
    /**
     * @param array<string,string> $constants
     */
    private function registry(array $constants = []): TaskRegistry
    {
        return new TaskRegistry(static fn (string $name): ?string => $constants[$name] ?? null);
    }

    /** Everything a task needs before it can run: endpoint, model, key. */
    private function configured(array $extra = []): TaskRegistry
    {
        return $this->registry(array_merge([
            'SKILITSA_AI_BASE_URL' => 'https://api.example.test/v1',
            'SKILITSA_AI_MODEL'    => 'some-free-model',
            'SKILITSA_AI_KEY'      => 'sk-shared',
        ], $extra));
    }

    public function test_every_task_from_the_spec_has_a_definition(): void
    {
        $keys = array_keys($this->registry()->all());

        self::assertSame([
            'matrix_qa',
            'draft_breed_traits',
            'check_penalties',
            'translate',
            'alt_text',
            'question_audit',
        ], $keys);
    }

    public function test_an_unknown_task_fails_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->registry()->get('write_the_marketing_copy');
    }

    public function test_a_task_without_a_key_is_unavailable_rather_than_fatal(): void
    {
        // The admin has to be able to say "not configured" without breaking.
        $task = $this->registry()->get('matrix_qa');

        self::assertFalse($task->isAvailable);
        self::assertSame('', $task->apiKey);
    }

    public function test_a_shared_configuration_covers_every_task(): void
    {
        $registry = $this->configured();

        self::assertTrue($registry->get('matrix_qa')->isAvailable);
        self::assertTrue($registry->get('translate')->isAvailable);
    }

    public function test_a_key_alone_is_not_enough(): void
    {
        // With no endpoint and no model there is nothing to call and nothing
        // to call it with. Reporting "ready" then would be a lie the admin
        // repeats to the owner.
        $registry = $this->registry(['SKILITSA_AI_KEY' => 'sk-shared']);

        self::assertFalse($registry->get('matrix_qa')->isAvailable);
    }

    public function test_nothing_ships_configured(): void
    {
        // No vendor, no model, no endpoint. A default that quietly spends
        // someone else's money is not a default.
        $task = $this->registry()->get('matrix_qa');

        self::assertSame('', $task->model);
        self::assertSame('', $task->baseUrl);
        self::assertFalse($task->isAvailable);
    }

    public function test_the_provider_defaults_to_the_openai_format(): void
    {
        // Gemini, Groq, OpenRouter, Together, LM Studio and Ollama all speak
        // it, so it is the format most likely to be free.
        self::assertSame('openai', $this->configured()->get('matrix_qa')->provider);
    }

    public function test_the_provider_can_be_switched(): void
    {
        $registry = $this->configured(['SKILITSA_AI_PROVIDER' => 'anthropic']);

        self::assertSame('anthropic', $registry->get('matrix_qa')->provider);
    }

    public function test_a_per_task_key_overrides_the_shared_one(): void
    {
        // Reasoning work and translation can sit on different accounts, and a
        // key can be revoked for one task without darkening the rest.
        $registry = $this->configured(['SKILITSA_AI_KEY_TRANSLATE' => 'sk-translate']);

        self::assertSame('sk-shared', $registry->get('matrix_qa')->apiKey);
        self::assertSame('sk-translate', $registry->get('translate')->apiKey);
    }

    public function test_the_model_can_be_overridden_per_task(): void
    {
        $registry = $this->configured(['SKILITSA_AI_MODEL_TRANSLATE' => 'a-cheaper-model']);

        self::assertSame('a-cheaper-model', $registry->get('translate')->model);
        self::assertNotSame('a-cheaper-model', $registry->get('matrix_qa')->model);
    }

    public function test_the_base_url_can_be_overridden(): void
    {
        $registry = $this->configured(['SKILITSA_AI_BASE_URL' => 'https://example.test/v1']);

        self::assertSame('https://example.test/v1', $registry->get('matrix_qa')->baseUrl);
    }

    public function test_every_task_that_produces_data_runs_at_temperature_zero(): void
    {
        // These tasks propose numbers and translations that a human will read
        // as facts. Variety is not a feature here.
        foreach ($this->registry()->all() as $key => $task) {
            self::assertSame(0.0, $task->temperature, $key);
        }
    }

    public function test_every_task_carries_a_timeout_and_a_description(): void
    {
        foreach ($this->registry()->all() as $key => $task) {
            self::assertGreaterThan(0, $task->timeout, $key);
            self::assertNotSame('', trim($task->description), $key);
        }
    }

    public function test_batch_and_interactive_tasks_are_marked_apart(): void
    {
        // A batch task can be slow and is run deliberately; an interactive one
        // sits behind a button someone is waiting on.
        self::assertTrue($this->registry()->get('matrix_qa')->isBatch);
        self::assertFalse($this->registry()->get('draft_breed_traits')->isBatch);
    }

    public function test_what_the_admin_renders_never_contains_a_key(): void
    {
        // The one test that would have caught a key printed on a settings page.
        $registry = $this->configured(['SKILITSA_AI_KEY' => 'sk-do-not-print-me']);

        $rendered = json_encode($registry->forDisplay());

        self::assertIsString($rendered);
        self::assertStringNotContainsString('sk-do-not-print-me', $rendered);
        self::assertStringContainsString('matrix_qa', $rendered);
    }

    public function test_display_says_which_tasks_are_configured(): void
    {
        $display = $this->registry([
            'SKILITSA_AI_BASE_URL'      => 'https://api.example.test/v1',
            'SKILITSA_AI_MODEL'         => 'some-free-model',
            'SKILITSA_AI_KEY_TRANSLATE' => 'sk-x',
        ])->forDisplay();

        self::assertTrue($display['translate']['is_available']);
        self::assertFalse($display['matrix_qa']['is_available']);
    }

    public function test_a_blank_constant_is_not_a_key(): void
    {
        // An empty define() is a common way to "disable" a key, and reading it
        // as configured would send an unauthenticated request on every run.
        $registry = $this->configured(['SKILITSA_AI_KEY' => '   ']);

        self::assertFalse($registry->get('matrix_qa')->isAvailable);
    }
}
