<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

/** One rejected field and the Greek sentence explaining why. */
final class FieldError
{
    public function __construct(
        public readonly string $field,
        public readonly string $message,
    ) {
    }
}
