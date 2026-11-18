<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Task;

use Skilitsa\Core\Ai\Draft\Draft;
use Skilitsa\Core\Data\BreedKey;

/**
 * Reads the trait matrix and reports contradictions, as drafts.
 *
 * The deterministic checks already catch missing values and out-of-range
 * numbers. What they cannot catch is a matrix that is internally consistent and
 * wrong: a breed rated 5 for calm and 5 for reactive, a giant rated 1 for food
 * cost, a value that contradicts the article it sits next to.
 *
 * Pure. The model's answer is data arriving from outside the system, and it is
 * treated exactly as the Google Sheet is: nothing is kept before it is checked
 * against what actually exists.
 */
final class MatrixQaTask
{
    public const KEY = 'matrix_qa';

    private const SCALE_MIN = 1.0;
    private const SCALE_MAX = 5.0;

    /** How many breeds go into one call. 257 do not fit in one answer. */
    public const BREEDS_PER_RUN = 25;

    public function systemPrompt(): string
    {
        return implode("\n", [
            'You are auditing a dog breed trait matrix for a Greek breed-matching quiz.',
            'Every trait is scored 1 to 5 on the scale given. Report only values that',
            'are wrong or that contradict another value for the same breed.',
            '',
            'Rules:',
            '- Use only the breed keys and trait keys given to you. Never invent either.',
            '- Suggest a value only when you would defend the change. Silence is a valid answer.',
            '- Every finding needs a short reason a person can check.',
            '- Suggested values are numbers from 1 to 5.',
            '',
            'Answer with JSON only, in this shape:',
            '{"findings":[{"breed":"<breed key>","trait":"<trait key>","suggested":<1-5>,"why":"<short reason>"}]}',
        ]);
    }

    /**
     * @param array<string,array<string,string>>                      $traits Trait key => label, scale_low, scale_high
     * @param array<string,array{name: string, values: array<string,float>}> $breeds
     */
    public function userPrompt(array $traits, array $breeds): string
    {
        $lines = ['TRAITS (key: label, 1 = low end, 5 = high end)'];

        foreach ($traits as $key => $trait) {
            $lines[] = sprintf(
                '- %s: %s | 1 = %s | 5 = %s',
                $key,
                $trait['label'] ?? $key,
                $trait['scale_low'] ?? '',
                $trait['scale_high'] ?? ''
            );
        }

        $lines[] = '';
        $lines[] = 'BREEDS';

        foreach ($breeds as $key => $breed) {
            $pairs = [];
            foreach ($breed['values'] ?? [] as $traitKey => $value) {
                $pairs[] = $traitKey . '=' . $this->format((float) $value);
            }

            $lines[] = sprintf(
                '- %s (%s): %s',
                $key,
                $breed['name'] ?? $key,
                $pairs === [] ? 'no values yet' : implode(' ', $pairs)
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed>                                            $data   Decoded model answer
     * @param array<string,array{name: string, values: array<string,float>}> $breeds What actually exists
     * @param list<string>                                                   $traitKeys
     */
    public function readResponse(array $data, array $breeds, array $traitKeys, string $model): TaskResult
    {
        $findings = $data['findings'] ?? [];

        // "I found nothing" is a legitimate and common answer.
        if (!is_array($findings)) {
            return TaskResult::of([], ['The answer had no usable findings list.']);
        }

        $drafts   = [];
        $warnings = [];

        foreach ($findings as $finding) {
            if (!is_array($finding)) {
                $warnings[] = 'Skipped a finding that was not an object.';
                continue;
            }

            $breedKey = BreedKey::canonical((string) ($finding['breed'] ?? ''));
            $traitKey = trim((string) ($finding['trait'] ?? ''));
            $why      = trim((string) ($finding['why'] ?? ''));

            if (!isset($breeds[$breedKey])) {
                // A model naming a breed nobody has is the clearest sign the
                // answer was improvised, and the cheapest place to notice.
                $warnings[] = sprintf('Skipped a finding for unknown breed "%s".', (string) ($finding['breed'] ?? ''));
                continue;
            }

            if (!in_array($traitKey, $traitKeys, true)) {
                $warnings[] = sprintf('Skipped a finding for unknown trait "%s".', $traitKey);
                continue;
            }

            if ($why === '') {
                $warnings[] = sprintf('Skipped an unexplained suggestion for %s / %s.', $breedKey, $traitKey);
                continue;
            }

            if (!is_numeric($finding['suggested'] ?? null)) {
                $warnings[] = sprintf('Skipped a non-numeric suggestion for %s / %s.', $breedKey, $traitKey);
                continue;
            }

            $suggested = (float) $finding['suggested'];

            if ($suggested < self::SCALE_MIN || $suggested > self::SCALE_MAX) {
                $warnings[] = sprintf(
                    'Skipped %s for %s / %s: outside the 1-5 scale.',
                    $this->format($suggested),
                    $breedKey,
                    $traitKey
                );
                continue;
            }

            $current    = $breeds[$breedKey]['values'][$traitKey] ?? null;
            $currentStr = $current === null ? '' : $this->format((float) $current);

            // Agreeing with the current value is not a finding.
            if ($current !== null && abs((float) $current - $suggested) < 0.0001) {
                continue;
            }

            $drafts[] = Draft::create(
                task: self::KEY,
                targetType: Draft::TARGET_BREED_TRAIT,
                targetKey: $breedKey,
                field: $traitKey,
                currentValue: $currentStr,
                suggestedValue: $this->format($suggested),
                model: $model,
                note: $why,
            );
        }

        return TaskResult::of($drafts, $warnings);
    }

    /**
     * @param  array<string,mixed> $breeds
     * @return list<array<string,mixed>>
     */
    public static function chunk(array $breeds, int $size = self::BREEDS_PER_RUN): array
    {
        return array_values(array_chunk($breeds, max(1, $size), true));
    }

    private function format(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}
