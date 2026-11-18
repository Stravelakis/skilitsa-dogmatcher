<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Task;

use Skilitsa\Core\Ai\Draft\Draft;
use Skilitsa\Core\Data\TraitDefaults;

/**
 * Argues with the asymmetry defaults.
 *
 * These 48 numbers decide what the site recommends more than anything else in
 * it, and they were written in one sitting by one person reasoning from trait
 * semantics. That is exactly the kind of work a second opinion improves and a
 * second opinion cannot be trusted to apply on its own.
 *
 * The task is given the rationale behind each number and asked to disagree
 * with the reasoning rather than to restate it. Anything it proposes lands as
 * a draft against the trait matrix screen, where approving it marks the row
 * tuned - a human decision, protected from the next import.
 */
final class CheckPenaltiesTask
{
    public const KEY = 'check_penalties';

    private const MIN = 0.0;
    private const MAX = 3.0;

    private const FIELDS = ['penalty_over', 'penalty_under'];

    public function systemPrompt(): string
    {
        return implode("\n", [
            'You are reviewing the cost model of a dog breed matching quiz.',
            '',
            'For each trait, distance in either direction is charged separately:',
            '  penalty_over  - the breed has MORE of the trait than the visitor asked for',
            '  penalty_under - the breed has LESS of it than they asked for',
            '',
            '0 means that direction costs nothing at all. 1 is ordinary. 1.5 is reserved',
            'for welfare and safety - the dog suffers, or someone gets hurt - not for',
            'inconvenience or disappointment.',
            '',
            'Rules:',
            '- Disagree with the reasoning, not with the number. If the stated reason',
            '  holds, say nothing about that trait.',
            '- Only propose a value you would defend to the owner.',
            '- Never invent a trait key. Values are 0 to 3.',
            '',
            'Answer with JSON only:',
            '{"findings":[{"trait":"<key>","field":"penalty_over|penalty_under",',
            '"value":<0-3>,"why":"<what the current reasoning gets wrong>"}]}',
        ]);
    }

    /** @param array<string,array<string,string>> $traits key => label, scale_low, scale_high, over, under */
    public function userPrompt(array $traits): string
    {
        $lines = ['TRAITS, with the current values and the reasoning behind them'];

        foreach ($traits as $key => $trait) {
            $lines[] = '';
            $lines[] = sprintf('- %s: %s', $key, $trait['label'] ?? $key);
            $lines[] = sprintf('    1 = %s | 5 = %s', $trait['scale_low'] ?? '', $trait['scale_high'] ?? '');
            $lines[] = sprintf(
                '    penalty_over=%s penalty_under=%s',
                $trait['over'] ?? '?',
                $trait['under'] ?? '?'
            );

            $note = TraitDefaults::noteFor($key);
            if ($note !== '') {
                $lines[] = '    reasoning: ' . $note;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed>                  $data
     * @param array<string,array<string,string>>   $traits Current rows, keyed by trait.
     */
    public function readResponse(array $data, array $traits, string $model): TaskResult
    {
        $findings = FindingReader::findings($data);

        if ($findings === null) {
            return TaskResult::of([], ['The answer had no usable findings list.']);
        }

        $drafts   = [];
        $warnings = [];

        foreach ($findings as $finding) {
            $traitKey = FindingReader::text($finding, 'trait');
            $field    = FindingReader::text($finding, 'field');
            $why      = FindingReader::text($finding, 'why');

            if (!isset($traits[$traitKey])) {
                $warnings[] = sprintf('Skipped a finding for unknown trait "%s".', $traitKey);
                continue;
            }

            if (!in_array($field, self::FIELDS, true)) {
                $warnings[] = sprintf('Skipped "%s" on %s: not a penalty field.', $field, $traitKey);
                continue;
            }

            if ($why === '') {
                $warnings[] = sprintf('Skipped an unexplained change to %s / %s.', $traitKey, $field);
                continue;
            }

            $value = FindingReader::number($finding, 'value', self::MIN, self::MAX);

            if ($value === null) {
                $warnings[] = sprintf('Skipped a value for %s / %s outside 0-3.', $traitKey, $field);
                continue;
            }

            $current = (float) ($traits[$traitKey][$field === 'penalty_over' ? 'over' : 'under'] ?? 1.0);

            // Agreeing with the current number is not a finding.
            if (abs($current - $value) < 0.0001) {
                continue;
            }

            $drafts[] = Draft::create(
                task: self::KEY,
                targetType: Draft::TARGET_TRAIT_PENALTY,
                targetKey: $traitKey,
                field: $field,
                currentValue: FindingReader::format($current),
                suggestedValue: FindingReader::format($value),
                model: $model,
                note: $why,
            );
        }

        return TaskResult::of($drafts, $warnings);
    }
}
