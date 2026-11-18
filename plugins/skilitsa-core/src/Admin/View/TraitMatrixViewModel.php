<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

use InvalidArgumentException;
use Skilitsa\Core\Admin\Form\TraitMatrixSubmission;
use Skilitsa\Core\Data\TraitDefaults;

/**
 * Everything the trait matrix screen shows, decided without WordPress.
 *
 * Row order is the order the rows arrive in - the table's own sort_order, which
 * is the owner's reading order. Re-sorting alphabetically here would scramble a
 * screen he navigates by position.
 */
final class TraitMatrixViewModel
{
    /** @param list<TraitMatrixRow> $rows */
    private function __construct(
        public readonly array $rows,
        private readonly bool $hasErrors,
    ) {
    }

    /** @param list<array<string,mixed>> $dbRows Rows from trait_definitions, in sort order. */
    public static function build(array $dbRows, ?TraitMatrixSubmission $submission = null): self
    {
        $rows = [];

        foreach ($dbRows as $dbRow) {
            $key      = (string) ($dbRow['trait_key'] ?? '');
            $defaults = TraitDefaults::PENALTIES[$key] ?? null;
            $typed    = $submission->raw[$key] ?? [];

            $isTuned = (int) ($dbRow['is_tuned'] ?? 0) === 1;

            $rows[] = new TraitMatrixRow(
                key: $key,
                label: (string) ($dbRow['label'] ?? $key),
                over: self::pick($typed['over'] ?? null, $dbRow['penalty_over'] ?? null),
                under: self::pick($typed['under'] ?? null, $dbRow['penalty_under'] ?? null),
                weight: self::pick($typed['weight'] ?? null, $dbRow['global_weight'] ?? null),
                defaultOver: $defaults === null ? '' : self::format($defaults['over']),
                defaultUnder: $defaults === null ? '' : self::format($defaults['under']),
                rationale: TraitDefaults::noteFor($key),
                isTuned: $isTuned,
                state: $isTuned ? 'tuned' : 'default',
                errorOver: $submission?->errorFor($key, 'over'),
                errorUnder: $submission?->errorFor($key, 'under'),
                errorWeight: $submission?->errorFor($key, 'weight'),
            );
        }

        return new self($rows, $submission !== null && !$submission->isValid());
    }

    public function row(string $key): TraitMatrixRow
    {
        foreach ($this->rows as $row) {
            if ($row->key === $key) {
                return $row;
            }
        }

        throw new InvalidArgumentException(sprintf('No trait row "%s".', $key));
    }

    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    public function tunedCount(): int
    {
        return count(array_filter($this->rows, static fn (TraitMatrixRow $r) => $r->isTuned));
    }

    /** What was typed wins over what is stored, so a rejected value stays visible. */
    private static function pick(?string $typed, mixed $stored): string
    {
        return $typed ?? self::format($stored);
    }

    private static function format(mixed $value): string
    {
        if (!is_numeric($value)) {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
    }
}
