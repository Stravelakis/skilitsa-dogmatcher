<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Mapper;

use Skilitsa\Core\Engine\Value\TraitDefinition;

final class TraitMapper
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<string,TraitDefinition>
     */
    public function fromRows(array $rows): array
    {
        $this->skipped = [];
        $traits = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['trait_key'] ?? ''));
            if ($key === '') {
                $this->skipped[] = 'Trait row with empty trait_key.';
                continue;
            }

            $over   = $row['penalty_over'] ?? 1.0;
            $under  = $row['penalty_under'] ?? 1.0;
            $weight = $row['global_weight'] ?? 1.0;

            if (!is_numeric($over) || !is_numeric($under) || !is_numeric($weight)) {
                $this->skipped[] = sprintf('Trait "%s" has a non-numeric penalty or weight.', $key);
                continue;
            }

            $traits[$key] = new TraitDefinition($key, (float) $over, (float) $under, (float) $weight);
        }

        return $traits;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
