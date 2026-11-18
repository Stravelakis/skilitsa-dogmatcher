<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Task;

use Skilitsa\Core\Ai\Draft\Draft;
use Skilitsa\Core\Data\BreedKey;

/**
 * Drafts the 24 trait values for a breed that has none.
 *
 * This is the task with the most obvious value and the most obvious danger. A
 * breed with no values still appears in results and scores on nothing, so
 * filling the gap matters - but a plausible number nobody checked is worse
 * than a visible hole, because the hole is at least honest.
 *
 * Hence: every suggestion carries a reason, values already present are never
 * overwritten, and the whole thing lands as drafts a person approves one at a
 * time through the same form they would have typed into.
 */
final class DraftBreedTraitsTask
{
    public const KEY = 'draft_breed_traits';

    private const SCALE_MIN = 1.0;
    private const SCALE_MAX = 5.0;

    public function systemPrompt(): string
    {
        return implode("\n", [
            'You are filling in a dog breed trait matrix for a Greek breed-matching quiz.',
            'You are given one breed and the traits that have no value yet.',
            '',
            'Rules:',
            '- Score each trait 1 to 5 on the scale given. Never invent a trait key.',
            '- Judge the breed as it typically is, not at its best or its worst.',
            '- Every value needs a short reason a person can check against the breed standard.',
            '- Leave out any trait you cannot justify. A gap someone can see beats a',
            '  number nobody can defend.',
            '',
            'Answer with JSON only:',
            '{"findings":[{"trait":"<trait key>","value":<1-5>,"why":"<short reason>"}]}',
        ]);
    }

    /**
     * @param array<string,array<string,string>> $traits  key => label, scale_low, scale_high
     * @param array{name: string, values: array<string,float>} $breed
     * @param list<string> $missing Traits with no value yet.
     */
    public function userPrompt(array $traits, array $breed, array $missing): string
    {
        $lines = ['BREED: ' . ($breed['name'] ?? '')];

        $known = [];
        foreach ($breed['values'] ?? [] as $key => $value) {
            $known[] = $key . '=' . FindingReader::format((float) $value);
        }

        $lines[] = $known === []
            ? 'Values already recorded: none'
            : 'Values already recorded (do not repeat these): ' . implode(' ', $known);

        $lines[] = '';
        $lines[] = 'TRAITS TO SCORE (key: label, 1 = low end, 5 = high end)';

        foreach ($missing as $key) {
            $trait = $traits[$key] ?? [];
            $lines[] = sprintf(
                '- %s: %s | 1 = %s | 5 = %s',
                $key,
                $trait['label'] ?? $key,
                $trait['scale_low'] ?? '',
                $trait['scale_high'] ?? ''
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed> $data
     * @param array{name: string, values: array<string,float>} $breed
     * @param list<string> $missing
     */
    public function readResponse(
        array $data,
        string $breedKey,
        array $breed,
        array $missing,
        string $model,
    ): TaskResult {
        $findings = FindingReader::findings($data);

        if ($findings === null) {
            return TaskResult::of([], ['The answer had no usable findings list.']);
        }

        $breedKey = BreedKey::canonical($breedKey);
        $drafts   = [];
        $warnings = [];

        foreach ($findings as $finding) {
            $traitKey = FindingReader::text($finding, 'trait');
            $why      = FindingReader::text($finding, 'why');

            if (!in_array($traitKey, $missing, true)) {
                // Either invented, or a trait that already has a value. The
                // second is the dangerous one: this task exists to fill gaps,
                // not to overrule a number somebody already checked.
                $warnings[] = sprintf('Skipped "%s": not one of the traits asked about.', $traitKey);
                continue;
            }

            if ($why === '') {
                $warnings[] = sprintf('Skipped an unexplained value for %s.', $traitKey);
                continue;
            }

            $value = FindingReader::number($finding, 'value', self::SCALE_MIN, self::SCALE_MAX);

            if ($value === null) {
                $warnings[] = sprintf('Skipped a value for %s that was not 1-5.', $traitKey);
                continue;
            }

            $drafts[] = Draft::create(
                task: self::KEY,
                targetType: Draft::TARGET_BREED_TRAIT,
                targetKey: $breedKey,
                field: $traitKey,
                currentValue: '',
                suggestedValue: FindingReader::format($value),
                model: $model,
                note: $why,
            );
        }

        return TaskResult::of($drafts, $warnings);
    }
}
