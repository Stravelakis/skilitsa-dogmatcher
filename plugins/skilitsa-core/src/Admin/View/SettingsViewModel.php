<?php
declare(strict_types=1);

namespace Skilitsa\Core\Admin\View;

use Skilitsa\Core\Admin\Form\SettingsSubmission;
use Skilitsa\Core\Data\Settings;

/**
 * Everything the settings screen shows, decided without WordPress.
 *
 * Help text lives here rather than in the template because it is part of the
 * decision: the owner is dyslexic and reads these screens rarely, so every knob
 * has to say in one plain sentence what moves when you turn it. A test fails if
 * a field ships without one.
 *
 * Language rule for the whole plugin: the admin is English, the public site is
 * Greek, and anything quoting front-end content into the admin shows both.
 * These labels name settings that have no front-end text, so English alone.
 */
final class SettingsViewModel
{
    /** @var array<string,array{label: string, help: string, step?: float}> */
    private const COPY = [
        'gamma' => [
            'label' => 'Scoring strictness',
            'help'  => 'Lower is stricter: every breed scores lower. Higher is more generous. Counter-intuitive, so it is spelled out here.',
            'step'  => 0.1,
        ],
        'shrinkage_m' => [
            'label' => 'Caution with few answers',
            'help'  => 'How hard a thin result is pulled towards 50%. Higher is more sceptical of a score built on few traits.',
            'step'  => 0.5,
        ],
        'salience_multiplier' => [
            'label' => 'Weight of deal-breaker answers',
            'help'  => 'How much more a trait counts when the visitor marked it as decisive.',
            'step'  => 0.5,
        ],
        'dealbreaker_threshold' => [
            'label' => 'Exclusion threshold',
            'help'  => 'How far off a decisive trait must be before the breed is dropped from the results entirely.',
            'step'  => 0.5,
        ],
        'close_call_points' => [
            'label' => 'Points that count as a tie',
            'help'  => 'If the top three breeds sit within this many points, the page says so instead of crowning a winner.',
            'step'  => 1.0,
        ],
        'min_traits_for_results' => [
            'label' => 'Minimum traits for a result',
            'help'  => 'Below this many traits with data, no result is shown at all rather than an unreliable one.',
            'step'  => 1.0,
        ],
        'results_top_cards' => [
            'label' => 'Cards on the podium',
            'help'  => 'How many breeds get a full card with explanations.',
            'step'  => 1.0,
        ],
        'results_more_count' => [
            'label' => 'Further suggestions',
            'help'  => 'How many more breeds are listed under the podium.',
            'step'  => 1.0,
        ],
        'sheet_id' => [
            'label' => 'Source sheet',
            'help'  => 'The Google Sheet the breed data and all the wording is imported from. Paste the sheet address and it will keep the ID. To translate the quiz, copy the sheet, translate the text columns, share your copy as "anyone with the link can view", and put its address here. Keep every tab and column name exactly as they are - the importer looks for them by name.',
        ],
        'accent_color' => [
            'label' => 'Accent colour',
            'help'  => 'The quiz\'s brand colour: buttons, links, the selected answer, the explanation panel. Everything else on the page is derived from it, including the softer background tint, so one value changes the whole feel.',
        ],
        'flow_mode' => [
            'label' => 'Question flow',
            'help'  => 'How the questions are presented to the visitor.',
        ],
    ];

    /** @var array<string,string> */
    private const FLOW_LABELS = [
        'all'    => 'All questions on one page',
        'live'   => 'One at a time, updating live',
        'tiered' => 'Core questions first, the rest after',
    ];

    /** @param list<SettingsField> $fields */
    private function __construct(
        public readonly array $fields,
        public readonly bool $hasErrors,
    ) {
    }

    /** @param array<string,mixed> $stored */
    public static function build(array $stored, ?SettingsSubmission $submission = null): self
    {
        $defaults = Settings::defaults();
        $bounds   = Settings::bounds();
        $fields   = [];

        foreach ($defaults as $key => $default) {
            $current = $stored[$key] ?? $default;

            // A rejected submission wins over the stored value: the owner must
            // see what they typed, sitting under the reason it was refused.
            $typed = $submission->raw[$key] ?? null;
            $value = $typed ?? self::format($current);

            $fields[] = new SettingsField(
                key: $key,
                label: self::COPY[$key]['label'] ?? $key,
                help: self::COPY[$key]['help'] ?? '',
                value: $value,
                default: self::format($default),
                isChanged: self::format($current) !== self::format($default),
                error: $submission?->errorFor($key),
                type: match ($key) {
                    'flow_mode'    => 'choice',
                    'accent_color' => 'color',
                    'sheet_id'     => 'text',
                    default        => 'number',
                },
                choices: $key === 'flow_mode' ? self::FLOW_LABELS : [],
                min: isset($bounds[$key]) ? (float) $bounds[$key][0] : null,
                max: isset($bounds[$key]) ? (float) $bounds[$key][1] : null,
                step: isset(self::COPY[$key]['step']) ? (float) self::COPY[$key]['step'] : null,
            );
        }

        return new self($fields, $submission !== null && !$submission->isValid());
    }

    public function field(string $key): SettingsField
    {
        foreach ($this->fields as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        throw new \InvalidArgumentException(sprintf('No settings field "%s".', $key));
    }

    public function hasErrors(): bool
    {
        return $this->hasErrors;
    }

    public function changedCount(): int
    {
        return count(array_filter($this->fields, static fn (SettingsField $f) => $f->isChanged));
    }

    private static function format(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
        }

        return (string) $value;
    }
}
