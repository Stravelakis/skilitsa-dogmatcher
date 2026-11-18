<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

/** One table's before/after on the import screen. */
final class ImportDiffLine
{
    public readonly int $delta;

    /** Anything that would shrink deserves a second look before it runs. */
    public readonly bool $isLoss;

    public function __construct(
        public readonly string $table,
        public readonly int $before,
        public readonly int $after,
    ) {
        $this->delta  = $after - $before;
        $this->isLoss = $after < $before;
    }
}
