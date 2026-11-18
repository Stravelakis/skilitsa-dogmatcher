<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

/** One row on the settings screen, fully decided. The template only echoes. */
final class SettingsField
{
    /** @param array<string,string> $choices value => Greek label, for type 'choice' */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $help,
        public readonly string $value,
        public readonly string $default,
        public readonly bool $isChanged,
        public readonly ?string $error = null,
        public readonly string $type = 'number',
        public readonly array $choices = [],
        public readonly ?float $min = null,
        public readonly ?float $max = null,
        public readonly ?float $step = null,
    ) {
    }
}
