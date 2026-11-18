<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

/**
 * The result of reading a settings form: what to save, what to complain about,
 * and what the owner actually typed so the screen can hand it back.
 */
final class SettingsSubmission
{
    /**
     * @param array<string,mixed>  $values Ready to store. Defaults where a field was blank or rejected.
     * @param list<FieldError>     $errors
     * @param array<string,string> $raw    Exactly what was submitted, for repopulating the boxes.
     */
    public function __construct(
        public readonly array $values,
        public readonly array $errors,
        public readonly array $raw,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function errorFor(string $field): ?string
    {
        foreach ($this->errors as $error) {
            if ($error->field === $field) {
                return $error->message;
            }
        }

        return null;
    }
}
