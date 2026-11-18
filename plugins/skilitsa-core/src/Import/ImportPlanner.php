<?php
declare(strict_types=1);

namespace Skilitsa\Core\Import;

use Skilitsa\Core\Data\BreedKey;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;

/**
 * Turns parsed sheet tabs into an ImportPlan, without writing anything.
 *
 * Distinguishes errors (the import must not proceed) from warnings (report it,
 * but 256 good breeds still reach the site).
 */
final class ImportPlanner
{
    private const MIN_TRAIT_VALUE = 1.0;
    private const MAX_TRAIT_VALUE = 5.0;

    /** @param array<string,array{headers:list<string>,rows:list<array<string,string>>}> $tabs */
    public function plan(array $tabs): ImportPlan
    {
        $errors   = [];
        $warnings = [];

        foreach (SheetSchema::REQUIRED_TABS as $tab) {
            if (!isset($tabs[$tab])) {
                $errors[] = sprintf('Tab %s was not fetched.', $tab);
                continue;
            }

            $missing = SheetSchema::validate($tab, $tabs[$tab]['headers']);
            if ($missing !== []) {
                $errors[] = sprintf('Tab %s is missing required columns: %s.', $tab, implode(', ', $missing));
            }

            if ($tabs[$tab]['rows'] === []) {
                $errors[] = sprintf('Tab %s has no rows.', $tab);
            }
        }

        $traits    = $this->indexRows($tabs, 'TRAITS', 'trait_key');
        $questions = $this->indexRows($tabs, 'QUESTIONS', 'question_key');
        $templates = $this->keyValue($tabs, 'RESULT_TEMPLATES', 'template_key', 'body');
        $uiTexts   = $this->keyValue($tabs, 'UI_TEXTS', 'key', 'value');
        $settings  = $this->keyValue($tabs, 'SETTINGS', 'key', 'value');

        $links = $this->buildLinks($tabs, $traits, $questions, $warnings);

        [$breedTraits, $breedMeta] = $this->buildBreeds($tabs, $traits, $warnings);

        return new ImportPlan(
            traits: $traits,
            questions: $questions,
            links: $links,
            templates: $templates,
            uiTexts: $uiTexts,
            settings: $settings,
            breedTraits: $breedTraits,
            breedMeta: $breedMeta,
            warnings: $warnings,
            errors: $errors,
        );
    }

    /**
     * @param array<string,array{headers:list<string>,rows:list<array<string,string>>}> $tabs
     * @return array<string,array<string,string>>
     */
    private function indexRows(array $tabs, string $tab, string $keyField): array
    {
        $indexed = [];

        foreach ($tabs[$tab]['rows'] ?? [] as $row) {
            $row = SheetSchema::applyAliases($row);
            $key = trim((string) ($row[$keyField] ?? ''));
            if ($key === '') {
                continue;
            }
            $indexed[$key] = $row;
        }

        return $indexed;
    }

    /**
     * @param array<string,array{headers:list<string>,rows:list<array<string,string>>}> $tabs
     * @return array<string,string>
     */
    private function keyValue(array $tabs, string $tab, string $keyField, string $valueField): array
    {
        $pairs = [];

        foreach ($tabs[$tab]['rows'] ?? [] as $row) {
            $row = SheetSchema::applyAliases($row);
            $key = trim((string) ($row[$keyField] ?? ''));
            if ($key === '') {
                continue;
            }
            $pairs[$key] = (string) ($row[$valueField] ?? '');
        }

        return $pairs;
    }

    /**
     * @param array<string,array{headers:list<string>,rows:list<array<string,string>>}> $tabs
     * @param array<string,array<string,string>> $traits
     * @param array<string,array<string,string>> $questions
     * @param list<string>                       $warnings
     * @return list<array<string,string>>
     */
    private function buildLinks(array $tabs, array $traits, array $questions, array &$warnings): array
    {
        $links = [];

        foreach ($tabs['MAPPING']['rows'] ?? [] as $row) {
            $row = SheetSchema::applyAliases($row);

            $questionKey = trim((string) ($row['question_key'] ?? ''));
            $traitKey    = trim((string) ($row['trait_key'] ?? ''));

            if ($questionKey === '' || $traitKey === '') {
                $warnings[] = 'MAPPING row missing question_key or trait_key.';
                continue;
            }
            if (!isset($questions[$questionKey])) {
                $warnings[] = sprintf('MAPPING references unknown question "%s".', $questionKey);
                continue;
            }
            if (!isset($traits[$traitKey])) {
                $warnings[] = sprintf('MAPPING references unknown trait "%s".', $traitKey);
                continue;
            }

            $rawDirection = (string) ($row['direction'] ?? '');
            $direction    = QuestionTraitLink::normaliseDirection($rawDirection);
            if ($direction === null) {
                $warnings[] = sprintf(
                    'MAPPING %s -> %s has unrecognised direction "%s"; treated as "same".',
                    $questionKey,
                    $traitKey,
                    $rawDirection
                );
                $direction = QuestionTraitLink::SAME;
            }

            $weight = trim((string) ($row['weight'] ?? ''));

            $links[] = [
                'question_key' => $questionKey,
                'trait_key'    => $traitKey,
                'direction'    => $direction,
                'weight'       => is_numeric($weight) ? $weight : '1',
            ];
        }

        return $links;
    }

    /**
     * @param array<string,array{headers:list<string>,rows:list<array<string,string>>}> $tabs
     * @param array<string,array<string,string>> $traits
     * @param list<string>                       $warnings
     * @return array{array<string,array<string,float>>,array<string,array<string,string>>}
     */
    private function buildBreeds(array $tabs, array $traits, array &$warnings): array
    {
        $values = [];
        $meta   = [];
        $clamped = 0;

        foreach ($tabs['BREEDS']['rows'] ?? [] as $row) {
            $rawKey = trim((string) ($row['breed_key'] ?? ''));
            if ($rawKey === '') {
                $warnings[] = 'BREEDS row with empty breed_key.';
                continue;
            }

            $key = BreedKey::canonical($rawKey);

            // The sheet has no `label` column and never has - v1 demanded one
            // and reported it missing on every sync. Display name comes from
            // the Greek name, falling back to English.
            $meta[$key] = [
                'breed_key'          => $rawKey,
                'label_el'           => trim((string) ($row['breed_el'] ?? '')),
                'label_en'           => trim((string) ($row['breed_en'] ?? '')),
                'external_slug'      => trim((string) ($row['external_slug'] ?? '')),
                'external_url'       => trim((string) ($row['external_url'] ?? '')),
                'external_image_url' => trim((string) ($row['external_image_url'] ?? '')),
                'grouping'           => trim((string) ($row['grouping'] ?? '')),
            ];

            if ($meta[$key]['label_el'] === '' && $meta[$key]['label_en'] === '') {
                $warnings[] = sprintf('Breed "%s" has neither a Greek nor an English name.', $rawKey);
            }

            $values[$key] = [];

            foreach ($traits as $traitKey => $_) {
                if (!array_key_exists($traitKey, $row)) {
                    $warnings[] = sprintf('Breed "%s" has no column for trait "%s".', $rawKey, $traitKey);
                    continue;
                }

                $raw = trim((string) $row[$traitKey]);
                if ($raw === '') {
                    $warnings[] = sprintf('Breed "%s" has no value for trait "%s".', $rawKey, $traitKey);
                    continue;
                }
                if (!is_numeric($raw)) {
                    $warnings[] = sprintf('Breed "%s" trait "%s" is not numeric ("%s").', $rawKey, $traitKey, $raw);
                    continue;
                }

                $value = (float) $raw;
                if ($value < self::MIN_TRAIT_VALUE || $value > self::MAX_TRAIT_VALUE) {
                    $clamped++;
                    $value = max(self::MIN_TRAIT_VALUE, min(self::MAX_TRAIT_VALUE, $value));
                }

                $values[$key][$traitKey] = $value;
            }
        }

        if ($clamped > 0) {
            $warnings[] = sprintf('%d trait value(s) fell outside 1-5 and were clamped.', $clamped);
        }

        return [$values, $meta];
    }
}
