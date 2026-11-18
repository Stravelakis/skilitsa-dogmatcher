<?php
declare(strict_types=1);

namespace Skilitsa\Core\Migration;

/**
 * An immutable description of every change the migration would make.
 *
 * Produced by MigrationPlanner, rendered by MigrationReporter, applied by the
 * executor - and reviewed by a human in between. The migration touches 258
 * live URLs; nothing about it should be decided at write time.
 */
final class MigrationPlan
{
    /**
     * @param list<PostChange> $changes
     * @param list<string>     $redirects Human-readable "old -> new" pairs.
     * @param list<string>     $warnings  Things a human must resolve.
     */
    public function __construct(
        public readonly array $changes = [],
        public readonly array $redirects = [],
        public readonly array $warnings = [],
    ) {
    }

    public function count(): int
    {
        return count($this->changes);
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function hasWarnings(): bool
    {
        return $this->warnings !== [];
    }

    /** @return list<PostChange> */
    public function slugChanges(): array
    {
        return array_values(array_filter(
            $this->changes,
            static fn (PostChange $c): bool => $c->changesSlug()
        ));
    }

    /** @return list<PostChange> */
    public function typeChanges(): array
    {
        return array_values(array_filter(
            $this->changes,
            static fn (PostChange $c): bool => $c->changesType()
        ));
    }
}
