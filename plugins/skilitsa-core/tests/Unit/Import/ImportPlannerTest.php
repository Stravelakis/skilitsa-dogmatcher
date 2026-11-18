<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Import\ImportPlanner;
use Skilitsa\Core\Import\SheetSchema;

final class ImportPlannerTest extends TestCase
{
    private ImportPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new ImportPlanner();
    }

    /**
     * A minimal but complete set of tabs. Individual tests override one tab to
     * isolate the behaviour under test.
     *
     * @param array<string,array{headers:list<string>,rows:list<array<string,string>>}> $overrides
     * @return array<string,array{headers:list<string>,rows:list<array<string,string>>}>
     */
    private function tabs(array $overrides = []): array
    {
        $tabs = [
            'SETTINGS' => [
                'headers' => ['key', 'value'],
                'rows'    => [['key' => 'breed_base_url', 'value' => 'https://skilitsa.com/']],
            ],
            'UI_TEXTS' => [
                'headers' => ['key', 'value'],
                'rows'    => [['key' => 'next_button', 'value' => 'Επόμενο']],
            ],
            'SECTIONS' => [
                'headers' => ['section_key', 'label'],
                'rows'    => [['section_key' => 'lifestyle', 'label' => 'Τρόπος ζωής']],
            ],
            'TRAITS' => [
                'headers' => ['trait_key', 'label'],
                'rows'    => [
                    ['trait_key' => 'energy_outdoor', 'label' => 'Άσκηση'],
                    ['trait_key' => 'shedding', 'label' => 'Τρίχωμα'],
                ],
            ],
            'QUESTIONS' => [
                'headers' => ['question_key', 'label'],
                'rows'    => [
                    ['question_key' => 'Q01', 'label' => 'Ερώτηση 1'],
                    ['question_key' => 'Q02', 'label' => 'Ερώτηση 2'],
                ],
            ],
            'MAPPING' => [
                'headers' => ['question_key', 'trait_key', 'direction', 'weight_override'],
                'rows'    => [
                    ['question_key' => 'Q01', 'trait_key' => 'energy_outdoor', 'direction' => 'same', 'weight_override' => ''],
                    ['question_key' => 'Q02', 'trait_key' => 'shedding', 'direction' => 'inverse', 'weight_override' => '0.6'],
                ],
            ],
            'RESULT_TEMPLATES' => [
                'headers' => ['template_key', 'el_text'],
                'rows'    => [['template_key' => 'pos_shedding', 'el_text' => 'Ταιριάζει']],
            ],
            'BREEDS' => [
                'headers' => ['breed_key', 'breed_en', 'breed_el', 'energy_outdoor', 'shedding'],
                'rows'    => [
                    ['breed_key' => 'greyhound', 'breed_en' => 'Greyhound', 'breed_el' => 'Γκρεϊχάουντ', 'energy_outdoor' => '3', 'shedding' => '1'],
                    ['breed_key' => 'border_collie', 'breed_en' => 'Border Collie', 'breed_el' => 'Μπόρντερ Κόλι', 'energy_outdoor' => '5', 'shedding' => '4'],
                ],
            ],
        ];

        return array_replace($tabs, $overrides);
    }

    public function test_a_complete_sheet_is_safe_to_apply(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertTrue($plan->isSafeToApply());
        self::assertSame([], $plan->errors);
        self::assertSame(2, $plan->counts()['traits']);
        self::assertSame(2, $plan->counts()['breeds']);
        self::assertSame(4, $plan->counts()['trait_values']);
    }

    public function test_a_missing_required_column_is_an_error_not_a_warning(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'TRAITS' => ['headers' => ['trait_key'], 'rows' => [['trait_key' => 'x']]],
        ]));

        self::assertNotEmpty($plan->errors);
        self::assertStringContainsString('label', $plan->errors[0]);
        self::assertFalse($plan->isSafeToApply());
    }

    public function test_a_tab_that_failed_to_fetch_is_an_error(): void
    {
        $tabs = $this->tabs();
        unset($tabs['BREEDS']);

        $plan = $this->planner->plan($tabs);

        self::assertFalse($plan->isSafeToApply());
        self::assertStringContainsString('BREEDS', implode(' ', $plan->errors));
    }

    public function test_an_empty_tab_is_an_error(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'BREEDS' => ['headers' => ['breed_key'], 'rows' => []],
        ]));

        self::assertFalse($plan->isSafeToApply());
    }

    public function test_breeds_does_not_require_a_label_column(): void
    {
        // v1 required `label` on BREEDS. The sheet has never had one, so every
        // sync reported it missing and the display name fell back to nothing.
        self::assertSame([], SheetSchema::validate('BREEDS', ['breed_key', 'breed_en', 'breed_el']));
    }

    public function test_display_names_come_from_the_greek_and_english_columns(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertSame('Γκρεϊχάουντ', $plan->breedMeta['greyhound']['label_el']);
        self::assertSame('Greyhound', $plan->breedMeta['greyhound']['label_en']);
    }

    public function test_a_breed_with_no_name_at_all_warns(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'BREEDS' => [
                'headers' => ['breed_key', 'breed_en', 'breed_el', 'energy_outdoor', 'shedding'],
                'rows'    => [['breed_key' => 'mystery', 'breed_en' => '', 'breed_el' => '', 'energy_outdoor' => '3', 'shedding' => '1']],
            ],
        ]));

        self::assertStringContainsString('mystery', implode(' ', $plan->warnings));
        self::assertTrue($plan->isSafeToApply(), 'a nameless breed is a warning, not a blocker');
    }

    public function test_breed_keys_are_canonicalised_to_match_post_slugs(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertArrayHasKey('border-collie', $plan->breedTraits);
        self::assertArrayNotHasKey('border_collie', $plan->breedTraits);
        self::assertSame('border_collie', $plan->breedMeta['border-collie']['breed_key'], 'the original key is preserved');
    }

    public function test_mapping_reads_direction_and_ignores_answer_value(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertCount(2, $plan->links);
        self::assertSame('inverse', $plan->links[1]['direction']);
        self::assertArrayNotHasKey('answer_value', $plan->links[0]);
    }

    public function test_weight_override_becomes_the_link_weight(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertSame('1', $plan->links[0]['weight'], 'blank override defaults to 1');
        self::assertSame('0.6', $plan->links[1]['weight']);
    }

    public function test_mapping_to_an_unknown_trait_warns_and_is_dropped(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'MAPPING' => [
                'headers' => ['question_key', 'trait_key', 'direction'],
                'rows'    => [['question_key' => 'Q01', 'trait_key' => 'nonsense', 'direction' => 'same']],
            ],
        ]));

        self::assertSame([], $plan->links);
        self::assertStringContainsString('nonsense', implode(' ', $plan->warnings));
        self::assertTrue($plan->isSafeToApply());
    }

    public function test_an_unknown_direction_falls_back_to_same_and_warns(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'MAPPING' => [
                'headers' => ['question_key', 'trait_key', 'direction'],
                'rows'    => [['question_key' => 'Q01', 'trait_key' => 'energy_outdoor', 'direction' => 'sideways']],
            ],
        ]));

        self::assertSame('same', $plan->links[0]['direction']);
        self::assertStringContainsString('sideways', implode(' ', $plan->warnings));
    }

    public function test_out_of_range_trait_values_are_clamped_and_reported(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'BREEDS' => [
                'headers' => ['breed_key', 'breed_el', 'energy_outdoor', 'shedding'],
                'rows'    => [['breed_key' => 'odd', 'breed_el' => 'Παράξενο', 'energy_outdoor' => '9', 'shedding' => '0']],
            ],
        ]));

        self::assertSame(5.0, $plan->breedTraits['odd']['energy_outdoor']);
        self::assertSame(1.0, $plan->breedTraits['odd']['shedding']);
        self::assertStringContainsString('clamped', implode(' ', $plan->warnings));
    }

    public function test_a_non_numeric_trait_value_is_dropped_with_a_warning(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'BREEDS' => [
                'headers' => ['breed_key', 'breed_el', 'energy_outdoor', 'shedding'],
                'rows'    => [['breed_key' => 'x', 'breed_el' => 'Χ', 'energy_outdoor' => 'high', 'shedding' => '2']],
            ],
        ]));

        self::assertArrayNotHasKey('energy_outdoor', $plan->breedTraits['x']);
        self::assertSame(2.0, $plan->breedTraits['x']['shedding']);
        self::assertTrue($plan->isSafeToApply(), 'one bad cell must not block 256 good breeds');
    }

    public function test_settings_and_ui_texts_survive_as_key_value_pairs(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertSame('https://skilitsa.com/', $plan->settings['breed_base_url']);
        self::assertSame('Επόμενο', $plan->uiTexts['next_button']);
    }

    public function test_templates_read_the_greek_body_column(): void
    {
        $plan = $this->planner->plan($this->tabs());

        self::assertSame('Ταιριάζει', $plan->templates['pos_shedding']);
    }

    public function test_the_export_url_carries_the_headers_parameter(): void
    {
        // Without headers=1 the gviz export folds data rows into the header
        // row, which is what hid eleven of the thirteen SETTINGS rows from v1.
        self::assertStringContainsString('headers=1', SheetSchema::EXPORT_URL);
    }

    public function test_the_sheets_invert_spelling_is_understood(): void
    {
        $plan = $this->planner->plan($this->tabs([
            'MAPPING' => [
                'headers' => ['question_key', 'trait_key', 'direction'],
                'rows'    => [['question_key' => 'Q01', 'trait_key' => 'energy_outdoor', 'direction' => 'invert']],
            ],
        ]));

        self::assertSame('inverse', $plan->links[0]['direction']);
        self::assertSame([], $plan->warnings, 'a spelling the sheet uses is not a warning');
    }
}
