<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

/**
 * The one place that decides what a breed key looks like.
 *
 * Two vocabularies exist and neither is going away: the source sheet keys
 * breeds with underscores (`border_collie`), while WordPress post slugs use
 * hyphens (`border-collie`) - and the migration deliberately converts the
 * remaining underscore slugs to hyphens for SEO.
 *
 * Anything correlating the two must pass through here. Comparing them raw
 * silently drops the breed instead of failing, which is the failure mode that
 * made v1 hard to debug.
 */
final class BreedKey
{
    /**
     * The canonical form used for comparison and lookup: lowercase, hyphens.
     *
     * Percent-encoded slugs are left alone beyond lowercasing - rewriting them
     * would change a live URL for no gain.
     *
     * The exception is a slug whose ASCII head is followed by percent-encoded
     * Greek: WordPress produced those by appending the Greek title to an
     * English base (`italian-greyhound%ce%b9...`). The head is the real key,
     * the tail is a duplicate of the title, and leaving it in place costs the
     * breed every trait value it has because nothing joins to it.
     */
    public static function canonical(string $key): string
    {
        $clean = strtolower(trim($key));

        if (str_contains($clean, '%')) {
            $head = trim(substr($clean, 0, (int) strpos($clean, '%')), '-_');

            // No ASCII head means the whole slug is encoded Greek. That is a
            // legitimate slug, not a mangled one, so it stays untouched.
            if ($head === '') {
                return $clean;
            }

            $clean = $head;
        }

        $clean = str_replace('_', '-', $clean);
        $clean = preg_replace('/-+/', '-', $clean) ?? $clean;

        return trim($clean, '-');
    }

    public static function matches(string $a, string $b): bool
    {
        return self::canonical($a) === self::canonical($b);
    }

    /**
     * Re-keys a map by canonical breed key.
     *
     * @param array<string,mixed> $byKey
     * @return array<string,mixed>
     */
    public static function reindex(array $byKey): array
    {
        $out = [];
        foreach ($byKey as $key => $value) {
            $out[self::canonical((string) $key)] = $value;
        }

        return $out;
    }
}
