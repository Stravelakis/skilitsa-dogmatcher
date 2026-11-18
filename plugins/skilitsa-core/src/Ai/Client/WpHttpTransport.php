<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Client;

/** wp_remote_post, and nothing else. */
final class WpHttpTransport implements HttpTransport
{
    /**
     * @param  array<string,string> $headers
     * @return array{status: int, body: string, error: ?string}
     */
    public function post(string $url, array $headers, string $body, int $timeout): array
    {
        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => $timeout,
        ]);

        if (is_wp_error($response)) {
            return ['status' => 0, 'body' => '', 'error' => $response->get_error_message()];
        }

        return [
            'status' => (int) wp_remote_retrieve_response_code($response),
            'body'   => (string) wp_remote_retrieve_body($response),
            'error'  => null,
        ];
    }
}
