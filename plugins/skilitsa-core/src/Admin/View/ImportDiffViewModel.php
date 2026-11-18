<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

use InvalidArgumentException;
use Skilitsa\Core\Data\BreedKey;
use Skilitsa\Core\Import\ImportPlan;

/**
 * What an import would change, decided before anything is written.
 *
 * The executor already refuses an unsafe plan. This is that refusal made
 * visible: v1's failure was not that it wrote badly, it was that nobody could
 * see what it was about to write.
 */
final class ImportDiffViewModel
{
    /**
     * @param list<ImportDiffLine> $lines
     * @param list<string>         $disappearingBreeds
     * @param list<string>         $preservedTraits
     * @param list<string>         $errors
     * @param list<string>         $warnings
     */
    private function __construct(
        public readonly array $lines,
        public readonly array $disappearingBreeds,
        public readonly array $preservedTraits,
        public readonly array $errors,
        public readonly array $warnings,
        public readonly bool $canApply,
    ) {
    }

    /**
     * @param array<string,int> $currentCounts  Rows per table today.
     * @param list<string>      $currentBreeds  Breed keys that have data today.
     * @param list<string>      $tunedTraits    Traits an import will not touch.
     */
    public static function build(
        ImportPlan $plan,
        array $currentCounts,
        array $currentBreeds,
        array $tunedTraits = [],
    ): self {
        $lines = [];
        foreach ($plan->counts() as $table => $after) {
            $lines[] = new ImportDiffLine($table, (int) ($currentCounts[$table] ?? 0), $after);
        }

        $incoming = array_map([BreedKey::class, 'canonical'], array_keys($plan->breedTraits));

        $disappearing = [];
        foreach ($currentBreeds as $key) {
            if (!in_array(BreedKey::canonical($key), $incoming, true)) {
                $disappearing[] = $key;
            }
        }

        return new self(
            lines: $lines,
            disappearingBreeds: $disappearing,
            preservedTraits: array_values($tunedTraits),
            errors: $plan->errors,
            warnings: $plan->warnings,
            canApply: $plan->isSafeToApply(),
        );
    }

    public function line(string $table): ImportDiffLine
    {
        foreach ($this->lines as $line) {
            if ($line->table === $table) {
                return $line;
            }
        }

        throw new InvalidArgumentException(sprintf('No diff line for "%s".', $table));
    }

    public function hasLosses(): bool
    {
        return $this->disappearingBreeds !== []
            || array_filter($this->lines, static fn (ImportDiffLine $l) => $l->isLoss) !== [];
    }

    /** Nothing to do is worth saying plainly rather than offering a button. */
    public function isNoOp(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->delta !== 0) {
                return false;
            }
        }

        return $this->disappearingBreeds === [];
    }
}
