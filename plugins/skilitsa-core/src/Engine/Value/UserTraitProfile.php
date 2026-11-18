<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

final class UserTraitProfile
{
    /** @var array<string,float> */
    public readonly array $values;

    /** @param array<string,float> $values traitKey => 1.0..5.0 */
    public function __construct(array $values)
    {
        ksort($values);
        $this->values = $values;
    }

    public function valueFor(string $traitKey): ?float
    {
        return $this->values[$traitKey] ?? null;
    }

    /** @return list<string> Sorted, so downstream iteration is deterministic. */
    public function knownTraitKeys(): array
    {
        return array_keys($this->values);
    }

    public function count(): int
    {
        return count($this->values);
    }
}
