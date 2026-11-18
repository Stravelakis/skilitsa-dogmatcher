<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai;

use InvalidArgumentException;

/**
 * The six AI tasks and where each one gets its key, model and endpoint.
 *
 * Configuration comes from `wp-config.php` constants and **never** from the
 * database: a key stored in an option travels in every backup and in every copy
 * pulled down to staging, which is how a production key ends up on a laptop.
 *
 * The constants are read through an injected reader rather than `defined()`
 * directly, so the whole class is testable without WordPress and without ever
 * defining a real key in a test process.
 *
 * Resolution order, per setting: the task-specific constant, then the shared
 * one, then the built-in default. That lets reasoning work and translation sit
 * on different accounts, and lets one task be switched off by revoking its key
 * without darkening the rest.
 */
final class TaskRegistry
{
    private const SHARED_KEY      = 'SKILITSA_AI_KEY';
    private const SHARED_BASE_URL = 'SKILITSA_AI_BASE_URL';
    private const SHARED_MODEL    = 'SKILITSA_AI_MODEL';
    private const SHARED_PROVIDER = 'SKILITSA_AI_PROVIDER';

    /**
     * No vendor, no model, no endpoint by default.
     *
     * An earlier version shipped with a particular company's models written
     * into it. Nobody chose those, and a default that quietly spends someone
     * else's money is not a default. Every task now reports itself
     * unconfigured until wp-config.php says otherwise, and the admin says so
     * plainly.
     */
    private const DEFAULT_BASE_URL = '';
    private const DEFAULT_MODEL    = '';
    private const DEFAULT_PROVIDER = 'openai';

    /**
     * Spec section 12. Order is the order the admin lists them in.
     *
     * @var array<string,array{description: string, timeout: int, batch: bool, max_tokens: int}>
     */
    private const TASKS = [
        'matrix_qa' => [
            'description' => 'Read the whole trait matrix and report outliers and contradictions.',
            'timeout'     => 120,
            'batch'       => true,
            'max_tokens'  => 8192,
        ],
        'draft_breed_traits' => [
            'description' => 'Draft the 24 trait values for a breed that has none, for review.',
            'timeout'     => 60,
            'batch'       => false,
            'max_tokens'  => 2048,
        ],
        'check_penalties' => [
            'description' => 'Argue with the drafted penalty_over / penalty_under defaults.',
            'timeout'     => 120,
            'batch'       => true,
            'max_tokens'  => 8192,
        ],
        'translate' => [
            'description' => 'Translation assist for copy that already exists in one language.',
            'timeout'     => 60,
            'batch'       => true,
            'max_tokens'  => 4096,
        ],
        'alt_text' => [
            'description' => 'Draft alt text for breed images, for review.',
            'timeout'     => 60,
            'batch'       => true,
            'max_tokens'  => 2048,
        ],
        'question_audit' => [
            'description' => 'Audit the quiz questions for ambiguity and overlap.',
            'timeout'     => 120,
            'batch'       => true,
            'max_tokens'  => 8192,
        ],
    ];

    /** @var callable(string): ?string */
    private $constant;

    /**
     * @param (callable(string): ?string)|null $constantReader Defaults to wp-config constants.
     */
    public function __construct(?callable $constantReader = null)
    {
        $this->constant = $constantReader ?? static fn (string $name): ?string
            => defined($name) && is_string(constant($name)) ? constant($name) : null;
    }

    /** @return array<string,TaskDefinition> */
    public function all(): array
    {
        $out = [];
        foreach (array_keys(self::TASKS) as $key) {
            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function get(string $taskKey): TaskDefinition
    {
        if (!isset(self::TASKS[$taskKey])) {
            throw new InvalidArgumentException(sprintf('Unknown AI task "%s".', $taskKey));
        }

        $task   = self::TASKS[$taskKey];
        $suffix = strtoupper($taskKey);

        return new TaskDefinition(
            key: $taskKey,
            description: $task['description'],
            baseUrl: $this->setting('SKILITSA_AI_BASE_URL_' . $suffix, self::SHARED_BASE_URL, self::DEFAULT_BASE_URL),
            model: $this->setting('SKILITSA_AI_MODEL_' . $suffix, self::SHARED_MODEL, self::DEFAULT_MODEL),
            // Every one of these proposes something a human will read as fact.
            // Variety is not a feature here.
            temperature: 0.0,
            timeout: $task['timeout'],
            isBatch: $task['batch'],
            provider: strtolower($this->setting(
                'SKILITSA_AI_PROVIDER_' . $suffix,
                self::SHARED_PROVIDER,
                self::DEFAULT_PROVIDER
            )) === 'anthropic' ? 'anthropic' : 'openai',
            maxTokens: $task['max_tokens'],
            apiKey: $this->setting('SKILITSA_AI_KEY_' . $suffix, self::SHARED_KEY, ''),
        );
    }

    /**
     * What an admin screen may show. Carries no key, by construction.
     *
     * @return array<string,array{description: string, model: string, is_batch: bool, is_available: bool}>
     */
    public function forDisplay(): array
    {
        $out = [];
        foreach ($this->all() as $key => $task) {
            $out[$key] = [
                'description'  => $task->description,
                'model'        => $task->model,
                'is_batch'     => $task->isBatch,
                'is_available' => $task->isAvailable,
                'provider'     => $task->provider,
            ];
        }

        return $out;
    }

    private function setting(string $specific, string $shared, string $default): string
    {
        foreach ([$specific, $shared] as $name) {
            $value = ($this->constant)($name);

            // A blank define() is how someone switches a key off. Reading it as
            // configured would send an unauthenticated request on every run.
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return $default;
    }
}
