<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai;

/**
 * One AI task and everything needed to run it.
 *
 * `apiKey` is the only field that must never be rendered. Everything that
 * displays a task goes through TaskRegistry::forDisplay(), which does not carry
 * it - see the test that greps the rendered output for the key itself.
 */
final class TaskDefinition
{
    /** A task with no key is reported, not thrown: the admin has to say so. */
    public readonly bool $isAvailable;

    public function __construct(
        public readonly string $key,
        public readonly string $description,
        public readonly string $baseUrl,
        public readonly string $model,
        public readonly float $temperature,
        public readonly int $timeout,
        public readonly bool $isBatch,
        /** 'openai' for anything speaking the OpenAI chat format, or 'anthropic'. */
        public readonly string $provider,
        public readonly int $maxTokens,
        public readonly string $apiKey,
    ) {
        // A key alone is not enough: with no endpoint and no model there is
        // nothing to call and nothing to call it with.
        $this->isAvailable = trim($apiKey) !== ''
            && trim($baseUrl) !== ''
            && trim($model) !== '';
    }
}
