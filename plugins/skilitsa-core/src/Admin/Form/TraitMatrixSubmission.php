<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\Form;

/**
 * The result of reading the trait matrix form.
 *
 * Errors are addressed as trait + sub-field, because one screen carries 72
 * boxes and "a value is out of range" is useless without saying which.
 */
final class TraitMatrixSubmission
{
    /**
     * @param array<string,array{over: float, under: float, weight: float, is_tuned: bool}> $rows Rows safe to save.
     * @param list<FieldError>                        $errors
     * @param array<string,array<string,string>>      $raw       What was typed, per trait per field.
     * @param list<string>                            $resetKeys Rows that returned to the code defaults.
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $errors,
        public readonly array $raw,
        public readonly array $resetKeys,
    ) {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function errorFor(string $traitKey, string $subField): ?string
    {
        $needle = self::fieldName($traitKey, $subField);

        foreach ($this->errors as $error) {
            if ($error->field === $needle) {
                return $error->message;
            }
        }

        return null;
    }

    public static function fieldName(string $traitKey, string $subField): string
    {
        return $traitKey . '.' . $subField;
    }
}
