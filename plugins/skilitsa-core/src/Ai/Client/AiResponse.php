<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Client;

/**
 * The outcome of one call. Either it clearly succeeded, or it produced nothing.
 *
 * There is no middle state on purpose: a half-read response is what let v1
 * replace a working matrix with empty arrays and report success.
 */
final class AiResponse
{
    /** @param array<string,mixed> $data */
    private function __construct(
        public readonly bool $ok,
        public readonly array $data,
        public readonly string $failure,
    ) {
    }

    /** @param array<string,mixed> $data */
    public static function success(array $data): self
    {
        return new self(true, $data, '');
    }

    public static function failure(string $why): self
    {
        return new self(false, [], $why);
    }
}
