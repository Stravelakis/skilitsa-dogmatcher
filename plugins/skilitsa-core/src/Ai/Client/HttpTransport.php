<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Client;

/**
 * The seam between this plugin and the network.
 *
 * It exists so the client's rules - a failure writes nothing, a wrong shape is
 * not a partial read, the key never reaches a message - are testable without
 * WordPress and without a real endpoint.
 */
interface HttpTransport
{
    /**
     * @param  array<string,string> $headers
     * @return array{status: int, body: string, error: ?string}
     */
    public function post(string $url, array $headers, string $body, int $timeout): array;
}
