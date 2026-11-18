<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Task;

use Skilitsa\Core\Ai\Draft\Draft;

/**
 * What one run of a task produced: drafts to review, and what was thrown away.
 *
 * Warnings are not noise. Every one of them is a place the model answered about
 * something that does not exist, which is the cheapest available signal that an
 * answer was improvised.
 */
final class TaskResult
{
    /**
     * @param list<Draft>  $drafts
     * @param list<string> $warnings
     */
    private function __construct(
        public readonly bool $ok,
        public readonly array $drafts,
        public readonly array $warnings,
        public readonly string $failure,
    ) {
    }

    /**
     * @param list<Draft>  $drafts
     * @param list<string> $warnings
     */
    public static function of(array $drafts, array $warnings = []): self
    {
        return new self(true, $drafts, $warnings, '');
    }

    public static function failed(string $why): self
    {
        return new self(false, [], [], $why);
    }
}
