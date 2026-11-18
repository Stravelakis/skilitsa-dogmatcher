<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

/** The result of reading one breed's trait values. */
final class BreedValuesSubmission
{
    /**
     * @param array<string,float>  $values Only the traits that parsed.
     * @param list<FieldError>     $errors
     * @param array<string,string> $raw    What was typed, per trait.
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

    public function errorFor(string $traitKey): ?string
    {
        foreach ($this->errors as $error) {
            if ($error->field === $traitKey) {
                return $error->message;
            }
        }

        return null;
    }
}
