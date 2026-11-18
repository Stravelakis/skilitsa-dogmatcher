<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Mapper;

use Skilitsa\Core\Data\BreedKey;
use Skilitsa\Core\Engine\Value\Breed;

final class BreedMapper
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @param list<array<string,mixed>> $rows Flat breed_key/trait_key/value triples.
     * @return list<Breed>
     */
    public function fromRows(array $rows): array
    {
        $this->skipped = [];
        $grouped = [];

        foreach ($rows as $row) {
            // Canonicalised so a sheet key (border_collie) and a post slug
            // (border-collie) resolve to the same breed rather than silently
            // becoming two.
            $breedKey = BreedKey::canonical((string) ($row['breed_key'] ?? ''));
            $traitKey = trim((string) ($row['trait_key'] ?? ''));

            if ($breedKey === '') {
                $this->skipped[] = 'Breed trait row with empty breed_key.';
                continue;
            }

            // Register the breed even if this particular trait is unusable, so
            // one bad cell does not remove the whole breed from the results.
            if (!array_key_exists($breedKey, $grouped)) {
                $grouped[$breedKey] = [];
            }

            if ($traitKey === '') {
                $this->skipped[] = sprintf('Breed "%s" has a row with empty trait_key.', $breedKey);
                continue;
            }

            $value = $row['value'] ?? null;
            if (!is_numeric($value)) {
                $this->skipped[] = sprintf('Breed "%s" trait "%s" is not numeric.', $breedKey, $traitKey);
                continue;
            }

            $grouped[$breedKey][$traitKey] = (float) $value;
        }

        $breeds = [];
        foreach ($grouped as $breedKey => $traits) {
            $breeds[] = new Breed((string) $breedKey, $traits);
        }

        return $breeds;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
