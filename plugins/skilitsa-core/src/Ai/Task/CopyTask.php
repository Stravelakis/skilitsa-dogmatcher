<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Task;

use Skilitsa\Core\Ai\Draft\CopyTarget;
use Skilitsa\Core\Ai\Draft\Draft;

/**
 * The three tasks that propose words rather than numbers.
 *
 * Translation, alt text and the question audit differ only in what they are
 * asked and which fields they may touch; the reading, checking and drafting is
 * identical, and three copies of it would be three places to forget a rule.
 *
 * Every one of them is bounded by CopyTarget: a suggestion addressed at a
 * field that is not on the whitelist is dropped with a warning rather than
 * stored and puzzled over later.
 */
final class CopyTask
{
    public const TRANSLATE      = 'translate';
    public const ALT_TEXT       = 'alt_text';
    public const QUESTION_AUDIT = 'question_audit';

    public function __construct(public readonly string $key)
    {
    }

    public function systemPrompt(): string
    {
        return match ($this->key) {
            self::TRANSLATE => implode("\n", [
                'You translate copy for a Greek dog breed matching quiz.',
                '',
                'The site speaks to one reader, warmly and plainly, and calls the dog',
                'η σκυλίτσα - feminine. Keep that voice. Keep every emoji exactly where',
                'it is. Never translate a placeholder in braces.',
                '',
                'Rules:',
                '- Translate only what you are given. Never improve the meaning.',
                '- If a line is already correct, leave it out of your answer.',
                '',
                'Answer with JSON only:',
                '{"findings":[{"target":"<target>","field":"<field>","text":"<translation>",',
                '"why":"<what was wrong with the original>"}]}',
            ]),

            self::ALT_TEXT => implode("\n", [
                'You write alt text for photographs of dog breeds on a Greek website.',
                '',
                'Alt text describes what is in the picture for someone who cannot see it.',
                'It is not a caption and not a sales line.',
                '',
                'Rules:',
                '- Greek, one sentence, under 125 characters.',
                '- Describe what is visible. Never guess the breed from the file name.',
                '- Do not start with "εικόνα" or "φωτογραφία" - a screen reader already',
                '  says that.',
                '',
                'Answer with JSON only:',
                '{"findings":[{"target":"<target>","field":"alt","text":"<alt text>",',
                '"why":"<why this describes the picture>"}]}',
            ]),

            default => implode("\n", [
                'You are auditing the questions of a Greek dog breed matching quiz.',
                '',
                'Each question is answered on a 1 to 5 scale whose two ends are given.',
                'You are looking for questions that cannot be answered honestly: two',
                'ideas in one question, ends that are not opposites, wording that leads',
                'the reader, or a scale whose middle means nothing.',
                '',
                'Rules:',
                '- Report only questions with a real problem. Most are fine; say nothing',
                '  about those.',
                '- Propose replacement wording, in Greek, in the same voice.',
                '- Never change what a question is asking, only how it asks.',
                '',
                'Answer with JSON only:',
                '{"findings":[{"target":"<target>","field":"<field>","text":"<new wording>",',
                '"why":"<the problem with the current wording>"}]}',
            ]),
        };
    }

    /**
     * @param list<array{target: string, field: string, text: string, context?: string}> $items
     */
    public function userPrompt(array $items): string
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = sprintf('TARGET: %s | FIELD: %s', $item['target'], $item['field']);

            if (($item['context'] ?? '') !== '') {
                $lines[] = 'CONTEXT: ' . $item['context'];
            }

            $lines[] = 'TEXT: ' . $item['text'];
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string,mixed>       $data
     * @param array<string,string>      $current  target address + field => the text it holds now
     * @return TaskResult
     */
    public function readResponse(array $data, array $current, string $model): TaskResult
    {
        $findings = FindingReader::findings($data);

        if ($findings === null) {
            return TaskResult::of([], ['The answer had no usable findings list.']);
        }

        $drafts   = [];
        $warnings = [];

        foreach ($findings as $finding) {
            $address = FindingReader::text($finding, 'target');
            $field   = FindingReader::text($finding, 'field');
            $text    = FindingReader::text($finding, 'text');
            $why     = FindingReader::text($finding, 'why');

            $target = CopyTarget::parse($address);

            if ($target === null) {
                $warnings[] = sprintf('Skipped a finding for unknown target "%s".', $address);
                continue;
            }

            if (!$target->allows($field)) {
                $warnings[] = sprintf('Skipped "%s" on %s: not a field this task may write.', $field, $address);
                continue;
            }

            $key = $address . '|' . $field;

            if (!array_key_exists($key, $current)) {
                // The model answered about something it was not shown. That is
                // the clearest sign an answer was improvised.
                $warnings[] = sprintf('Skipped %s / %s: it was not part of this run.', $address, $field);
                continue;
            }

            $limit = $this->key === self::ALT_TEXT ? 125 : 1200;

            if (!FindingReader::isUsableCopy($text, $limit)) {
                $warnings[] = sprintf('Skipped %s / %s: empty or over %d characters.', $address, $field, $limit);
                continue;
            }

            if ($why === '') {
                $warnings[] = sprintf('Skipped an unexplained rewrite of %s / %s.', $address, $field);
                continue;
            }

            if (trim($current[$key]) === $text) {
                // Identical to what is already there. Not a finding.
                continue;
            }

            $drafts[] = Draft::create(
                task: $this->key,
                targetType: Draft::TARGET_COPY,
                targetKey: $address,
                field: $field,
                currentValue: $current[$key],
                suggestedValue: $text,
                model: $model,
                note: $why,
            );
        }

        return TaskResult::of($drafts, $warnings);
    }
}
