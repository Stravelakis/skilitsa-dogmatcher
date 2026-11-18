<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Client;

use Skilitsa\Core\Ai\TaskDefinition;

/**
 * Calls the Messages API and returns one decoded JSON object, or nothing.
 *
 * Three rules, each with a test:
 *
 * 1. Anything short of a clean 200 with the expected shape is a failure that
 *    carries no data. There is no partial read - that is exactly how v1
 *    replaced a working matrix with empty arrays and logged success.
 * 2. The key travels in a header and is scrubbed from every message on the way
 *    out. An API that echoes the key back in a 401 body must not put it in an
 *    admin notice.
 * 3. Every setting comes from the task, so a cheap model on a short timeout and
 *    an expensive one on a long timeout are a config change, not a code change.
 */
final class HttpAiClient implements AiClient
{
    public function __construct(
        private readonly HttpTransport $transport = new WpHttpTransport(),
    ) {
    }

    public function ask(TaskDefinition $task, string $system, string $user): AiResponse
    {
        // Never call out without a key: an unauthenticated request is a
        // guaranteed 401 and, on some gateways, a rate-limit strike.
        if (!$task->isAvailable) {
            return AiResponse::failure(sprintf('Task "%s" has no API key configured.', $task->key));
        }

        $request = ChatRequest::build($task, $system, $user);

        $result = $this->transport->post(
            $request['url'],
            $request['headers'],
            $request['body'],
            $task->timeout
        );

        return $this->read($result, $task->apiKey, $task->provider);
    }

    /** @param array{status: int, body: string, error: ?string} $result */
    private function read(array $result, string $apiKey, string $provider): AiResponse
    {
        if ($result['error'] !== null) {
            return $this->fail('The request failed: ' . $result['error'], $apiKey);
        }

        if ($result['status'] !== 200) {
            return $this->fail(
                sprintf('The API answered %d: %s', $result['status'], $this->trim($result['body'])),
                $apiKey
            );
        }

        $envelope = json_decode($result['body'], true);
        $answer   = is_array($envelope) ? ChatRequest::readText($provider, $envelope) : null;

        if ($answer === null) {
            return $this->fail('The API answered in an unexpected shape.', $apiKey);
        }

        $text = $this->unfence($answer);
        $decoded = json_decode($text, true);

        // A model that answered in prose has not answered. Reading half of it
        // is how a wrong value reaches a review queue looking confident.
        if (!is_array($decoded) || array_is_list($decoded)) {
            return $this->fail('The model did not answer with a JSON object.', $apiKey);
        }

        return AiResponse::success($decoded);
    }

    /** Models fence JSON even when told not to; refusing that costs a whole run. */
    private function unfence(string $text): string
    {
        $trimmed = trim($text);

        if (!str_starts_with($trimmed, '```')) {
            return $trimmed;
        }

        $withoutOpen = preg_replace('/^```[a-zA-Z]*\s*/', '', $trimmed) ?? $trimmed;

        return trim(preg_replace('/```\s*$/', '', $withoutOpen) ?? $withoutOpen);
    }

    private function fail(string $message, string $apiKey): AiResponse
    {
        // Some gateways echo the key back in the error body.
        $safe = $apiKey === '' ? $message : str_replace($apiKey, '[key redacted]', $message);

        return AiResponse::failure($safe);
    }

    private function trim(string $body): string
    {
        $clean = trim($body);

        return strlen($clean) > 300 ? substr($clean, 0, 300) . '…' : $clean;
    }
}
