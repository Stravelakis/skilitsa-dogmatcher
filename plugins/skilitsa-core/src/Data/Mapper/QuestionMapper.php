<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data\Mapper;

use Skilitsa\Core\Engine\Value\Question;

final class QuestionMapper
{
    /** @var list<string> */
    private array $skipped = [];

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<Question>
     */
    public function fromRows(array $rows): array
    {
        $this->skipped = [];
        $questions = [];

        foreach ($rows as $row) {
            $key = trim((string) ($row['question_key'] ?? ''));
            if ($key === '') {
                $this->skipped[] = 'Question row with empty question_key.';
                continue;
            }

            $weight = $row['weight'] ?? 1.0;
            if (!is_numeric($weight)) {
                $this->skipped[] = sprintf('Question "%s" has a non-numeric weight.', $key);
                continue;
            }

            $questions[] = new Question($key, (float) $weight);
        }

        return $questions;
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }
}
