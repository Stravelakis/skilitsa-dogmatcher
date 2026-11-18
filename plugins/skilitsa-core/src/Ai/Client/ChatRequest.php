<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Client;

use Skilitsa\Core\Ai\TaskDefinition;

/**
 * One request, in whichever shape the configured provider speaks.
 *
 * Two protocols cover nearly everything worth using: the OpenAI chat format,
 * which Gemini, Groq, OpenRouter, Together, LM Studio and Ollama all accept,
 * and Anthropic's Messages format. Keeping both here means changing provider
 * is a line in wp-config.php rather than a change to this plugin.
 *
 * Pure: it builds a URL, headers and a body, and sends nothing.
 */
final class ChatRequest
{
    public const OPENAI    = 'openai';
    public const ANTHROPIC = 'anthropic';

    private const ANTHROPIC_VERSION = '2023-06-01';

    /** @return array{url: string, headers: array<string,string>, body: string} */
    public static function build(TaskDefinition $task, string $system, string $user): array
    {
        return $task->provider === self::ANTHROPIC
            ? self::anthropic($task, $system, $user)
            : self::openai($task, $system, $user);
    }

    /**
     * Reads the answer back out. Null means the shape was not what we asked
     * for, which the client treats as a failure rather than a partial read.
     *
     * @param array<string,mixed> $envelope
     */
    public static function readText(string $provider, array $envelope): ?string
    {
        if ($provider === self::ANTHROPIC) {
            $text = $envelope['content'][0]['text'] ?? null;

            return is_string($text) ? $text : null;
        }

        $text = $envelope['choices'][0]['message']['content'] ?? null;

        return is_string($text) ? $text : null;
    }

    /** @return array{url: string, headers: array<string,string>, body: string} */
    private static function openai(TaskDefinition $task, string $system, string $user): array
    {
        $body = [
            'model'       => $task->model,
            'temperature' => $task->temperature,
            'max_tokens'  => $task->maxTokens,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            // Providers that support it return JSON and nothing else; those
            // that do not ignore the key, and the fenced-JSON reader catches
            // the difference.
            'response_format' => ['type' => 'json_object'],
        ];

        return [
            'url' => rtrim($task->baseUrl, '/') . '/chat/completions',
            'headers' => [
                'authorization' => 'Bearer ' . $task->apiKey,
                'content-type'  => 'application/json',
            ],
            'body' => self::encode($body),
        ];
    }

    /** @return array{url: string, headers: array<string,string>, body: string} */
    private static function anthropic(TaskDefinition $task, string $system, string $user): array
    {
        $body = [
            'model'       => $task->model,
            'max_tokens'  => $task->maxTokens,
            'temperature' => $task->temperature,
            'system'      => $system,
            'messages'    => [['role' => 'user', 'content' => $user]],
        ];

        return [
            'url' => rtrim($task->baseUrl, '/') . '/messages',
            'headers' => [
                'x-api-key'         => $task->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'content-type'      => 'application/json',
            ],
            'body' => self::encode($body),
        ];
    }

    /** @param array<string,mixed> $body */
    private static function encode(array $body): string
    {
        // PRESERVE_ZERO_FRACTION keeps temperature 0.0 a float rather than an
        // int: an API that type-checks its own schema is entitled to.
        return json_encode(
            $body,
            JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }
}
