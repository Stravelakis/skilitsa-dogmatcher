<?php
declare(strict_types=1);

namespace Skilitsa\Core\Migration;

final class PostChange
{
    public function __construct(
        public readonly int $postId,
        public readonly string $oldSlug,
        public readonly string $newSlug,
        public readonly string $oldType,
        public readonly string $newType,
        public readonly string $title,
    ) {
    }

    public function changesSlug(): bool
    {
        return $this->oldSlug !== $this->newSlug;
    }

    public function changesType(): bool
    {
        return $this->oldType !== $this->newType;
    }
}
