<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Mapper;

use Skilitsa\Core\Engine\Value\QuestionTraitLink;

final class LinkMapper
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<QuestionTraitLink>
     */
    public function fromRows(array $rows): array
    {
        $this->skipped = [];
        $links = [];

        foreach ($rows as $row) {
            $questionKey = trim((string) ($row['question_key'] ?? ''));
            $traitKey    = trim((string) ($row['trait_key'] ?? ''));

            if ($questionKey === '' || $traitKey === '') {
                $this->skipped[] = 'Mapping row missing question_key or trait_key.';
                continue;
            }

            $rawDirection = (string) ($row['direction'] ?? '');
            $direction    = QuestionTraitLink::normaliseDirection($rawDirection);
            if ($direction === null) {
                $direction = $rawDirection;
                // Fall back rather than fatal, but record it loudly: a silently
                // ignored direction column is exactly how v1 lost the ability
                // to express a negative correlation.
                $this->skipped[] = sprintf(
                    'Mapping %s -> %s has unrecognised direction "%s"; treated as "same".',
                    $questionKey,
                    $traitKey,
                    $direction
                );
                $direction = QuestionTraitLink::SAME;
            }

            $weight = $row['weight'] ?? 1.0;
            if (!is_numeric($weight)) {
                $this->skipped[] = sprintf('Mapping %s -> %s has a non-numeric weight.', $questionKey, $traitKey);
                continue;
            }

            $links[] = new QuestionTraitLink($questionKey, $traitKey, $direction, (float) $weight);
        }

        return $links;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
