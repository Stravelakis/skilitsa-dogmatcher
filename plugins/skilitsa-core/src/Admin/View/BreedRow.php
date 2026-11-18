<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

/**
 * One breed on the list screen.
 *
 * Carries both names by the plugin's language rule: the Greek the visitor
 * reads, and the English name the sheet keys on.
 */
final class BreedRow
{
    /** @param list<string> $missingTraits */
    public function __construct(
        public readonly int $postId,
        public readonly string $slug,
        public readonly string $greekName,
        public readonly string $englishName,
        public readonly int $valueCount,
        public readonly int $traitCount,
        public readonly array $missingTraits,
        public readonly bool $isComplete,
    ) {
    }
}
