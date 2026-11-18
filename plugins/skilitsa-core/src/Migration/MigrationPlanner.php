<?php
declare(strict_types=1);

namespace Skilitsa\Core\Migration;

/**
 * Decides what the migration would do, without doing any of it.
 *
 * Separating the decision from the write means the decision can be unit-tested
 * and printed for review. 258 live URLs is not a thing to work out at write
 * time.
 */
final class MigrationPlanner
{
    public function __construct(
        private readonly SlugNormaliser $slugs = new SlugNormaliser(),
    ) {
    }

    /**
     * @param list<array<string,mixed>> $posts Rows of ID, post_name, post_type, post_title.
     */
    public function plan(array $posts, string $targetType, string $pathPrefix): MigrationPlan
    {
        $warnings = [];
        $valid    = [];

        foreach ($posts as $post) {
            $id   = (int) ($post['ID'] ?? 0);
            $slug = trim((string) ($post['post_name'] ?? ''));

            if ($id <= 0) {
                $warnings[] = sprintf(
                    'Skipped a row with no usable ID (title: %s).',
                    (string) ($post['post_title'] ?? '?')
                );
                continue;
            }
            if ($slug === '') {
                $warnings[] = sprintf('Skipped post %d: it has no slug.', $id);
                continue;
            }

            $valid[] = [
                'id'    => $id,
                'slug'  => $slug,
                'type'  => (string) ($post['post_type'] ?? ''),
                'title' => (string) ($post['post_title'] ?? ''),
            ];
        }

        $slugPlan = $this->slugs->planFor(array_column($valid, 'slug'));
        $warnings = array_merge($warnings, $slugPlan['collisions']);

        $changes   = [];
        $redirects = [];

        foreach ($valid as $post) {
            $newSlug = $slugPlan['changes'][$post['slug']] ?? $post['slug'];

            $change = new PostChange(
                postId: $post['id'],
                oldSlug: $post['slug'],
                newSlug: $newSlug,
                oldType: $post['type'],
                newType: $targetType,
                title: $post['title'],
            );

            if (!$change->changesSlug() && !$change->changesType()) {
                continue;
            }

            $changes[] = $change;

            // Every migrated post moves under the new prefix, so the old URL
            // needs a redirect even when the slug itself is unchanged.
            $redirects[] = sprintf('/%s/ -> /%s/%s/', $post['slug'], $pathPrefix, $newSlug);
        }

        return new MigrationPlan($changes, $redirects, $warnings);
    }
}
