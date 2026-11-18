<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\View;

/**
 * Everything one result card renders.
 *
 * A breed with no linked post still produces a card: name and explanation come
 * from trait data, which is always present. Suppressing it would mean lying
 * about someone's best match because an article has not been written yet.
 */
final class BreedCard
{
    /**
     * @param list<TraitLine> $aligned
     * @param list<TraitLine> $pitfalls
     * @param list<string>    $conflicts Greek trait labels, for excluded breeds.
     */
    public function __construct(
        public readonly string $breedKey,
        public readonly string $name,
        public readonly int $score,
        public readonly ?string $imageUrl,
        public readonly ?string $permalink,
        public readonly array $aligned = [],
        public readonly array $pitfalls = [],
        public readonly array $conflicts = [],
    ) {
    }

    public function hasPost(): bool
    {
        return $this->permalink !== null;
    }

    public function hasImage(): bool
    {
        return $this->imageUrl !== null && $this->imageUrl !== '';
    }
}
