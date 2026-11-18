<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use InvalidArgumentException;

final class Breed
{
    /** @var array<string,float> */
    public readonly array $traits;

    /**
     * @param array<string,int|float|string|null> $traits Raw trait values keyed
     *        by trait key. Non-numeric and non-finite values are discarded here
     *        rather than reaching the scorer.
     */
    public function __construct(
        public readonly string $key,
        array $traits,
    ) {
        if ($key === '') {
            throw new InvalidArgumentException('Breed key cannot be empty.');
        }

        $clean = [];
        foreach ($traits as $traitKey => $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $float = (float) $value;
            if (!is_finite($float)) {
                continue;
            }
            $clean[(string) $traitKey] = $float;
        }
        $this->traits = $clean;
    }

    public function traitValue(string $traitKey): ?float
    {
        return $this->traits[$traitKey] ?? null;
    }
}
