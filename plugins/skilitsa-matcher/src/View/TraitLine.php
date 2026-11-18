<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\View;

/** One explained trait on a breed card. */
final class TraitLine
{
    public function __construct(
        public readonly string $traitKey,
        public readonly string $label,
        public readonly string $tier,
        public readonly string $body,
    ) {
    }
}
