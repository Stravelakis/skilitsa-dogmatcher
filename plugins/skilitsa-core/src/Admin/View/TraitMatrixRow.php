<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

/** One trait on the matrix screen, fully decided. The template only echoes. */
final class TraitMatrixRow
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $over,
        public readonly string $under,
        public readonly string $weight,
        public readonly string $defaultOver,
        public readonly string $defaultUnder,
        public readonly string $rationale,
        public readonly bool $isTuned,
        /** 'default' - following the code defaults; 'tuned' - a human decided it. */
        public readonly string $state,
        public readonly ?string $errorOver = null,
        public readonly ?string $errorUnder = null,
        public readonly ?string $errorWeight = null,
    ) {
    }

    public function hasError(): bool
    {
        return $this->errorOver !== null
            || $this->errorUnder !== null
            || $this->errorWeight !== null;
    }
}
