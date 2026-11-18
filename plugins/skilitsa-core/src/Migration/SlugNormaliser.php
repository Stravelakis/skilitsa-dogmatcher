<?php
declare(strict_types=1);

namespace Skilitsa\Core\Migration;

use Skilitsa\Core\Data\BreedKey;

/**
 * Converts underscore slugs to hyphens.
 *
 * 193 of the 258 breed posts use underscores. Google treats "_" as a
 * word-joiner and "-" as a separator, so border_collie is indexed as the
 * single token "bordercollie". Renaming is an SEO improvement, but it changes
 * a live URL, so collisions are surfaced rather than resolved.
 */
final class SlugNormaliser
{
    public function normalise(string $slug): string
    {
        // Delegates to BreedKey so slug normalisation and breed-key matching
        // can never drift apart into two subtly different rules.
        return BreedKey::canonical($slug);
    }

    public function needsChange(string $slug): bool
    {
        return $this->normalise($slug) !== $slug;
    }

    /**
     * @param list<string> $slugs Every slug in the set being migrated.
     * @return array{changes: array<string,string>, collisions: list<string>}
     */
    public function planFor(array $slugs): array
    {
        $existing = array_fill_keys($slugs, true);

        $changes    = [];
        $collisions = [];
        $claimed    = [];

        foreach ($slugs as $slug) {
            if (!$this->needsChange($slug)) {
                continue;
            }

            $target = $this->normalise($slug);

            if (isset($existing[$target])) {
                $collisions[] = sprintf(
                    'Renaming "%s" would collide with existing slug "%s".',
                    $slug,
                    $target
                );
                continue;
            }

            // The same slug appearing twice in the input is a duplicate, not a
            // two-source collision; planning it once is correct.
            if (($claimed[$target] ?? null) === $slug) {
                continue;
            }

            if (isset($claimed[$target])) {
                $collisions[] = sprintf(
                    'Both "%s" and "%s" normalise to "%s".',
                    $claimed[$target],
                    $slug,
                    $target
                );
                unset($changes[$claimed[$target]]);
                continue;
            }

            $claimed[$target] = $slug;
            $changes[$slug]   = $target;
        }

        return ['changes' => $changes, 'collisions' => $collisions];
    }
}
