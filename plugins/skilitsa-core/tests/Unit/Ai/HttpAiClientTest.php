<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Ai\Client\HttpAiClient;
use Skilitsa\Core\Ai\Client\HttpTransport;
use Skilitsa\Core\Ai\TaskDefinition;

/**
 * The one place this plugin talks to something it does not control.
 *
 * Every rule here is the importer's rule restated: a call that did not clearly
 * succeed writes nothing. v1's disaster was not a bad write, it was a failed
 * read that looked like a small one.
 */
final class HttpAiClientTest extends TestCase
{
    private function task(string $key = 'sk-test', string $provider = 'anthropic'): TaskDefinition
    {
        return new TaskDefinition(
            key: 'matrix_qa',
            description: 'test task',
            baseUrl: 'https://api.example.test/v1',
            model: 'claude-opus-5',
            temperature: 0.0,
            timeout: 90,
            isBatch: true,
            provider: $provider,
            maxTokens: 4096,
            apiKey: $key,
        );
    }

    /** @param array{status?: int, body?: string, error?: ?string} $response */
    private function transport(array $response): HttpTransport
    {
        return new class ($response) implements HttpTransport {
            public array $seen = [];

            public function __construct(private array $response)
            {
            }

            public function post(string $url, array $headers, string $body, int $timeout): array
            {
                $this->seen = compact('url', 'headers', 'body', 'timeout');

                return [
                    'status' => $this->response['status'] ?? 200,
                    'body'   => $this->response['body'] ?? '',
                    'error'  => $this->response['error'] ?? null,
                ];
            }
        };
    }

    private function apiBody(string $text): string
    {
        return json_encode(['content' => [['type' => 'text', 'text' => $text]]], JSON_THROW_ON_ERROR);
    }

    public function test_a_good_response_returns_the_decoded_payload(): void
    {
        $transport = $this->transport(['body' => $this->apiBody('{"findings":[{"breed":"akita"}]}')]);

        $response = (new HttpAiClient($transport))->ask($this->task(), 'system', 'user');

        self::assertTrue($response->ok);
        self::assertSame([['breed' => 'akita']], $response->data['findings']);
    }

    public function test_a_transport_error_is_a_failure_and_carries_no_data(): void
    {
        $transport = $this->transport(['error' => 'Connection timed out']);

        $response = (new HttpAiClient($transport))->ask($this->task(), 'system', 'user');

        self::assertFalse($response->ok);
        self::assertSame([], $response->data);
        self::assertStringContainsString('timed out', $response->failure);
    }

    public function test_a_non_200_is_a_failure(): void
    {
        $transport = $this->transport(['status' => 429, 'body' => 'rate limited']);

        $response = (new HttpAiClient($transport))->ask($this->task(), 'system', 'user');

        self::assertFalse($response->ok);
        self::assertStringContainsString('429', $response->failure);
    }

    public function test_a_response_that_is_not_json_is_a_failure_not_a_partial_read(): void
    {
        $transport = $this->transport(['body' => $this->apiBody('Sure! Here are my thoughts:')]);

        $response = (new HttpAiClient($transport))->ask($this->task(), 'system', 'user');

        self::assertFalse($response->ok);
        self::assertSame([], $response->data);
    }

    public function test_a_json_array_where_an_object_was_asked_for_is_a_failure(): void
    {
        $transport = $this->transport(['body' => $this->apiBody('[1, 2, 3]')]);

        self::assertFalse((new HttpAiClient($transport))->ask($this->task(), 's', 'u')->ok);
    }

    public function test_an_api_envelope_without_a_text_block_is_a_failure(): void
    {
        $transport = $this->transport(['body' => json_encode(['error' => ['message' => 'overloaded']])]);

        $response = (new HttpAiClient($transport))->ask($this->task(), 's', 'u');

        self::assertFalse($response->ok);
    }

    public function test_json_wrapped_in_a_fenced_block_is_still_read(): void
    {
        // Models fence JSON even when told not to. Refusing that is pedantry
        // that costs a whole run.
        $transport = $this->transport(['body' => $this->apiBody("```json\n{\"findings\":[]}\n```")]);

        $response = (new HttpAiClient($transport))->ask($this->task(), 's', 'u');

        self::assertTrue($response->ok);
        self::assertSame([], $response->data['findings']);
    }

    public function test_an_unconfigured_task_fails_without_calling_out(): void
    {
        $transport = $this->transport(['body' => $this->apiBody('{}')]);

        $response = (new HttpAiClient($transport))->ask($this->task(''), 's', 'u');

        self::assertFalse($response->ok);
        self::assertSame([], $transport->seen);
    }

    public function test_the_request_carries_the_tasks_own_settings(): void
    {
        $transport = $this->transport(['body' => $this->apiBody('{}')]);

        (new HttpAiClient($transport))->ask($this->task(), 'system prompt', 'user prompt');

        $sent = json_decode($transport->seen['body'], true);

        self::assertSame('https://api.example.test/v1/messages', $transport->seen['url']);
        self::assertSame(90, $transport->seen['timeout']);
        self::assertSame('claude-opus-5', $sent['model']);
        self::assertSame(0.0, $sent['temperature']);
        self::assertSame(4096, $sent['max_tokens']);
        self::assertSame('system prompt', $sent['system']);
        self::assertSame('user prompt', $sent['messages'][0]['content']);
    }

    public function test_the_key_travels_in_the_header(): void
    {
        $transport = $this->transport(['body' => $this->apiBody('{}')]);

        (new HttpAiClient($transport))->ask($this->task('sk-secret'), 's', 'u');

        self::assertSame('sk-secret', $transport->seen['headers']['x-api-key']);
        self::assertArrayHasKey('anthropic-version', $transport->seen['headers']);
    }

    public function test_a_failure_message_can_never_contain_the_key(): void
    {
        // The test that would have caught a key pasted into an admin notice.
        $transport = $this->transport(['status' => 401, 'body' => 'invalid x-api-key: sk-secret']);

        $response = (new HttpAiClient($transport))->ask($this->task('sk-secret'), 's', 'u');

        self::assertFalse($response->ok);
        self::assertStringNotContainsString('sk-secret', $response->failure);
    }
}
