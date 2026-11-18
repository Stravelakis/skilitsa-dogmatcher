<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Draft;

/**
 * What a review decided, and what - if anything - should now be written.
 *
 * `values` is deliberately in the shape the repositories already take, so the
 * screen that applies it has no decisions left to make.
 */
final class ReviewOutcome
{
    /** @param array<string,mixed> $values */
    private function __construct(
        public readonly bool $ok,
        public readonly Draft $draft,
        public readonly array $values,
        public readonly string $failure,
    ) {
    }

    /** @param array<string,mixed> $values */
    public static function applied(Draft $draft, array $values): self
    {
        return new self(true, $draft, $values, '');
    }

    public static function recorded(Draft $draft): self
    {
        return new self(true, $draft, [], '');
    }

    public static function refused(Draft $draft, string $why): self
    {
        return new self(false, $draft, [], $why);
    }
}
