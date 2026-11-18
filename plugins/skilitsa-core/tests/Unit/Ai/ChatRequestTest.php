<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\Client\ChatRequest;
use Skilitsa\Core\Ai\TaskDefinition;

/**
 * Two wire formats, so the provider is a line in wp-config.php.
 *
 * The OpenAI chat format is the one Gemini, Groq, OpenRouter, Together, LM
 * Studio and Ollama all accept, which makes it the format most likely to be
 * free. Anthropic's is kept because it is the other one worth speaking.
 */
final class ChatRequestTest extends TestCase
{
    private function task(string $provider): TaskDefinition
    {
        return new TaskDefinition(
            key: 'matrix_qa',
            description: 'test',
            baseUrl: 'https://api.example.test/v1',
            model: 'a-model',
            temperature: 0.0,
            timeout: 60,
            isBatch: true,
            provider: $provider,
            maxTokens: 2048,
            apiKey: 'sk-secret',
        );
    }

    public function test_the_openai_format_posts_to_chat_completions(): void
    {
        $r = ChatRequest::build($this->task('openai'), 'system text', 'user text');
        $body = json_decode($r['body'], true);

        self::assertSame('https://api.example.test/v1/chat/completions', $r['url']);
        self::assertSame('Bearer sk-secret', $r['headers']['authorization']);
        self::assertSame('system', $body['messages'][0]['role']);
        self::assertSame('system text', $body['messages'][0]['content']);
        self::assertSame('user text', $body['messages'][1]['content']);
        self::assertSame('json_object', $body['response_format']['type']);
    }

    public function test_the_anthropic_format_posts_to_messages(): void
    {
        $r = ChatRequest::build($this->task('anthropic'), 'system text', 'user text');
        $body = json_decode($r['body'], true);

        self::assertSame('https://api.example.test/v1/messages', $r['url']);
        self::assertSame('sk-secret', $r['headers']['x-api-key']);
        self::assertArrayHasKey('anthropic-version', $r['headers']);
        self::assertSame('system text', $body['system']);
    }

    public function test_temperature_stays_a_float_in_both(): void
    {
        // An API entitled to type-check its own schema should not be handed
        // an integer where it documented a number.
        foreach (['openai', 'anthropic'] as $provider) {
            self::assertStringContainsString('"temperature":0.0', ChatRequest::build(
                $this->task($provider), 's', 'u'
            )['body'], $provider);
        }
    }

    public function test_each_format_is_read_from_its_own_shape(): void
    {
        self::assertSame('hello', ChatRequest::readText('openai', [
            'choices' => [['message' => ['content' => 'hello']]],
        ]));

        self::assertSame('hello', ChatRequest::readText('anthropic', [
            'content' => [['type' => 'text', 'text' => 'hello']],
        ]));
    }

    public function test_the_wrong_shape_reads_as_nothing(): void
    {
        // Null is what makes the client call it a failure rather than reading
        // half an answer.
        self::assertNull(ChatRequest::readText('openai', ['content' => [['text' => 'hi']]]));
        self::assertNull(ChatRequest::readText('anthropic', ['choices' => [['message' => ['content' => 'hi']]]]));
        self::assertNull(ChatRequest::readText('openai', ['error' => ['message' => 'nope']]));
    }
}
