<?php
declare(strict_types=1);

namespace Skilitsa\Core\Import;

/**
 * The contract between the Google Sheet and the importer.
 *
 * Derived from the real sheet, not from assumption. Two v1 mistakes are
 * encoded here deliberately:
 *
 *  - BREEDS has no `label` column and never has. v1 required one, so every
 *    sync reported "Tab BREEDS is missing required columns: label,
 *    section_key" and the display name fell back to nothing.
 *  - MAPPING's `answer_value` column exists but is empty in every row. v1
 *    joined on it, which is why all 257 breeds scored 0%. It is listed as
 *    IGNORED here so nobody wires it back up.
 */
final class SheetSchema
{
    /**
     * The export URL must carry headers=1.
     *
     * Without it, gviz applies a header heuristic and folds several leading
     * data rows into the header row - which swallowed eleven of the thirteen
     * SETTINGS rows and produced v1's phantom "missing key/value" error.
     */
    public const EXPORT_URL = 'https://docs.google.com/spreadsheets/d/%s/gviz/tq?tqx=out:csv&headers=1&sheet=%s';

    public const REQUIRED_TABS = [
        'SETTINGS',
        'UI_TEXTS',
        'SECTIONS',
        'TRAITS',
        'QUESTIONS',
        'MAPPING',
        'RESULT_TEMPLATES',
        'BREEDS',
    ];

    /** Columns without which a tab cannot be imported at all. */
    private const REQUIRED_COLUMNS = [
        'SETTINGS'         => ['key', 'value'],
        'UI_TEXTS'         => ['key', 'value'],
        'SECTIONS'         => ['section_key', 'label'],
        'TRAITS'           => ['trait_key', 'label'],
        'QUESTIONS'        => ['question_key', 'label'],
        'MAPPING'          => ['question_key', 'trait_key'],
        'RESULT_TEMPLATES' => ['template_key'],
        'BREEDS'           => ['breed_key'],
    ];

    /**
     * Sheet column name => schema field.
     *
     * The TRAITS warning columns carry a parenthesised explanation in the
     * sheet, which the CSV parser normalises into the key verbatim.
     */
    private const ALIASES = [
        'warning_too_low_(user_wants_low,_breed_is_high)'  => 'warning_too_low',
        'warning_too_high_(user_wants_high,_breed_is_low)' => 'warning_too_high',
        'description_el'   => 'description',
        'scale_1_el'       => 'scale_low',
        'scale_5_el'       => 'scale_high',
        'label_1'          => 'label_low',
        'label_5'          => 'label_high',
        'img_1_key'        => 'image_low',
        'img_5_key'        => 'image_high',
        'weight_override'  => 'weight',
        'el_text'          => 'body',
    ];

    /**
     * Columns present in the sheet that the importer deliberately ignores.
     *
     * `answer_value` is the v1 landmine; the rest are editorial notes.
     */
    public const IGNORED_COLUMNS = ['answer_value', 'answer_label', 'notes', 'placeholders'];

    /** Non-trait columns on the BREEDS tab. Everything else is a trait value. */
    public const BREED_META_COLUMNS = [
        'breed_key',
        'breed_en',
        'breed_el',
        'external_slug',
        'external_url',
        'external_image_url',
        'grouping',
    ];

    /** @return list<string> */
    public static function columnsFor(string $tab): array
    {
        return self::REQUIRED_COLUMNS[$tab] ?? [];
    }

    /**
     * @param list<string> $headers
     * @return list<string> Missing required columns, empty when the tab is usable.
     */
    public static function validate(string $tab, array $headers): array
    {
        $present = [];
        foreach ($headers as $header) {
            $present[self::alias($header)] = true;
        }

        $missing = [];
        foreach (self::columnsFor($tab) as $column) {
            if (!isset($present[$column])) {
                $missing[] = $column;
            }
        }

        return $missing;
    }

    public static function alias(string $column): string
    {
        return self::ALIASES[$column] ?? $column;
    }

    /**
     * Rewrites a row's keys to schema field names.
     *
     * @param array<string,string> $row
     * @return array<string,string>
     */
    public static function applyAliases(array $row): array
    {
        $out = [];
        foreach ($row as $key => $value) {
            $out[self::alias((string) $key)] = $value;
        }

        return $out;
    }

    public static function isTraitColumn(string $column): bool
    {
        return !in_array($column, self::BREED_META_COLUMNS, true)
            && !in_array($column, self::IGNORED_COLUMNS, true)
            && !str_starts_with($column, 'column_');
    }
}
