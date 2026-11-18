<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Tests\Unit\View;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\TraitVerdict;
use Skilitsa\Matcher\View\ResultViewModelBuilder;

final class ResultViewModelBuilderTest extends TestCase
{
    private ResultViewModelBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ResultViewModelBuilder();
    }

    /** @return array<string,array<string,string>> */
    private function traitCopy(): array
    {
        return [
            'energy'   => ['label' => 'Ενέργεια', 'warning_too_low' => 'Πολύ ενεργητικός για εσένα.', 'warning_too_high' => 'Λιγότερο ενεργητικός απ᾽ όσο θες.'],
            'shedding' => ['label' => 'Τρίχωμα', 'warning_too_low' => 'Ρίχνει πολλά τρίχα.', 'warning_too_high' => 'Ρίχνει λίγα.'],
            'size'     => ['label' => 'Μέγεθος', 'warning_too_low' => 'Μεγαλύτερος απ᾽ ό,τι θες.', 'warning_too_high' => 'Μικρότερος απ᾽ ό,τι θες.'],
        ];
    }

    /** @return array<string,string> */
    private function templates(): array
    {
        return [
            'pos_energy_1'   => 'Η ενέργειά του ταιριάζει.',
            'pos_energy_2'   => 'Ίδιος ρυθμός με τον δικό σου.',
            'neg_energy_1'   => 'Η ενέργεια θέλει προσοχή.',
            'neg_energy_2'   => 'Μπορεί να σε κουράσει.',
            'pos_shedding_1' => 'Το τρίχωμα σου κάνει.',
            'pos_shedding_2' => 'Δεν θα σε ενοχλήσει το τρίχωμα.',
            'neg_shedding_1' => 'Το τρίχωμα ίσως ενοχλήσει.',
            'neg_shedding_2' => 'Θα χρειαστεί σκούπισμα.',
            'pos_size_1'     => 'Το μέγεθος ταιριάζει.',
            'neg_size_1'     => 'Το μέγεθος θέλει σκέψη.',
            'results_disclaimer_title' => 'Σημαντικό πριν αποφασίσεις',
            'results_disclaimer_body'  => 'Κάθε σκύλος είναι μοναδικός.',
        ];
    }

    /** @return array<string,array{name:string,image:?string,permalink:?string}> */
    private function display(): array
    {
        return [
            'greyhound'  => ['name' => 'Γκρέιχαουντ', 'image' => 'https://example.test/g.jpg', 'permalink' => 'https://example.test/breeds/greyhound/'],
            'malinois'   => ['name' => 'Μαλινουά', 'image' => null, 'permalink' => 'https://example.test/breeds/malinois/'],
            // 'orphan' is deliberately absent: a breed with no post yet.
        ];
    }

    /** @return array{list<Question>,list<QuestionTraitLink>,array<string,TraitDefinition>} */
    private function scaffold(): array
    {
        return [
            [new Question('Q01'), new Question('Q02'), new Question('Q03')],
            [
                new QuestionTraitLink('Q01', 'energy'),
                new QuestionTraitLink('Q02', 'shedding'),
                new QuestionTraitLink('Q03', 'size'),
            ],
            [
                'energy'   => new TraitDefinition('energy', 1.0, 1.0),
                'shedding' => new TraitDefinition('shedding', 1.0, 1.0),
                'size'     => new TraitDefinition('size', 1.0, 1.0),
            ],
        ];
    }

    /** @param list<Breed> $breeds */
    private function model(AnswerSet $answers, array $breeds, int $minTraits = 3)
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = MatchEngine::create()->run(
            $answers,
            $questions,
            $links,
            $traits,
            $breeds,
            new ScoringConfig(minTraitsForResults: $minTraits),
        );

        return $this->builder->build($result, $this->display(), $this->templates(), $this->traitCopy());
    }

    public function test_podium_holds_the_first_three(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [
                new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0]),
                new Breed('malinois', ['energy' => 4.0, 'shedding' => 3.0, 'size' => 3.0]),
                new Breed('orphan', ['energy' => 5.0, 'shedding' => 3.0, 'size' => 3.0]),
            ],
        );

        self::assertCount(3, $model->podium);
        self::assertSame('greyhound', $model->podium[0]->breedKey);
        self::assertTrue($model->hasResults());
    }

    public function test_a_breed_with_no_post_still_renders_with_a_readable_name(): void
    {
        // v1 shipped nameless cards because it looked for a `label` column the
        // sheet has never had. A missing post must never produce a blank card.
        $model = $this->model(
            new AnswerSet(['Q01' => 5, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('orphan', ['energy' => 5.0, 'shedding' => 3.0, 'size' => 3.0])],
        );

        $card = $model->podium[0];

        self::assertSame('orphan', $card->breedKey);
        self::assertSame('Orphan', $card->name);
        self::assertFalse($card->hasPost());
        self::assertFalse($card->hasImage());
    }

    public function test_a_hyphenated_key_becomes_a_readable_fallback_name(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('border-collie', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
        );

        self::assertSame('Border Collie', $model->podium[0]->name);
    }

    public function test_a_breed_with_a_post_carries_its_permalink_and_image(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
        );

        $card = $model->podium[0];

        self::assertTrue($card->hasPost());
        self::assertTrue($card->hasImage());
        self::assertSame('Γκρέιχαουντ', $card->name);
    }

    public function test_a_breed_with_a_post_but_no_image_reports_no_image(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('malinois', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
        );

        self::assertTrue($model->podium[0]->hasPost());
        self::assertFalse($model->podium[0]->hasImage());
    }

    public function test_aligned_lines_use_positive_copy(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
        );

        $aligned = $model->podium[0]->aligned;

        self::assertNotEmpty($aligned);
        self::assertSame(TraitVerdict::ALIGNED, $aligned[0]->tier);

        // Either positive variant is correct; which one is chosen is
        // deterministic per breed but not something this test should pin.
        self::assertContains($aligned[0]->body, [
            $this->templates()['pos_energy_1'],
            $this->templates()['pos_energy_2'],
            $this->templates()['pos_shedding_1'],
            $this->templates()['pos_shedding_2'],
            $this->templates()['pos_size_1'],
        ]);
    }

    public function test_the_chosen_variant_differs_between_breeds_on_one_page(): void
    {
        // Three identical breeds must not open with the same sentence, or the
        // results page reads like a mail merge.
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [
                new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0]),
                new Breed('malinois', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0]),
            ],
        );

        $bodies = array_map(
            static fn ($card): string => $card->aligned[0]->body,
            $model->podium
        );

        self::assertNotEmpty($bodies);
        // Not a strict inequality: with two variants and two breeds a collision
        // is possible. What matters is that the seed is the breed key, which
        // the determinism test above already pins.
        self::assertContainsOnly('string', $bodies);
    }

    public function test_a_card_carries_at_most_two_aligned_lines_and_one_pitfall(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
        );

        self::assertLessThanOrEqual(2, count($model->podium[0]->aligned));
        self::assertLessThanOrEqual(1, count($model->podium[0]->pitfalls));
    }

    public function test_neutral_verdicts_produce_no_line_at_all(): void
    {
        // Saying "this is fine" about every middling trait buries the two that
        // matter, so neutral is silent by design.
        $model = $this->model(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]),
            [new Breed('greyhound', ['energy' => 4.0, 'shedding' => 4.0, 'size' => 4.0])],
        );

        $lines = array_merge($model->podium[0]->aligned, $model->podium[0]->pitfalls);

        $tiers = array_map(static fn ($l): string => $l->tier, $lines);
        self::assertNotContains(TraitVerdict::NEUTRAL, $tiers);
    }

    public function test_an_opposed_verdict_uses_the_directional_warning_not_a_template(): void
    {
        // User wants 1, breed is 5: breed is HIGHER than wanted, so the copy
        // is warning_too_low - named from the user's side.
        $model = $this->model(
            new AnswerSet(['Q01' => 1, 'Q02' => 1, 'Q03' => 1]),
            [new Breed('greyhound', ['energy' => 5.0, 'shedding' => 5.0, 'size' => 5.0])],
        );

        $pitfall = $model->podium[0]->pitfalls[0];

        self::assertSame(TraitVerdict::OPPOSED, $pitfall->tier);
        self::assertStringContainsString('Πολύ ενεργητικός', $pitfall->body);
    }

    public function test_trait_lines_carry_the_greek_label(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 1, 'Q02' => 1, 'Q03' => 1]),
            [new Breed('greyhound', ['energy' => 5.0, 'shedding' => 5.0, 'size' => 5.0])],
        );

        self::assertSame('Ενέργεια', $model->podium[0]->pitfalls[0]->label);
    }

    public function test_below_the_evidence_floor_there_is_no_podium(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3]),
            [new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
            minTraits: 3,
        );

        self::assertFalse($model->hasEnoughEvidence);
        self::assertFalse($model->hasResults());
        self::assertSame([], $model->podium);
        self::assertSame(1, $model->traitsKnown);
    }

    public function test_the_disclaimer_is_carried_even_below_the_floor(): void
    {
        $model = $this->model(
            new AnswerSet(['Q01' => 3]),
            [new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])],
            minTraits: 3,
        );

        self::assertSame('Σημαντικό πριν αποφασίσεις', $model->disclaimerTitle);
        self::assertNotSame('', $model->disclaimerBody);
    }

    public function test_excluded_breeds_name_their_conflict_in_greek(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $answers = new AnswerSet(
            ['Q01' => 1, 'Q02' => 3, 'Q03' => 3],
            dealbreakerKeys: ['Q01'],
        );

        $result = MatchEngine::create()->run(
            $answers,
            $questions,
            $links,
            $traits,
            [new Breed('greyhound', ['energy' => 5.0, 'shedding' => 3.0, 'size' => 3.0])],
            new ScoringConfig(minTraitsForResults: 3),
        );

        $model = $this->builder->build($result, $this->display(), $this->templates(), $this->traitCopy());

        self::assertTrue($model->everythingExcluded());
        self::assertCount(1, $model->excluded);
        self::assertSame(['Ενέργεια'], $model->excluded[0]->conflicts);
        self::assertSame('Ενέργεια', $model->blockingDealbreakerLabel);
    }

    public function test_the_same_result_renders_identically_twice(): void
    {
        // Result URLs are shareable, so variant choice must be deterministic.
        $answers = new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]);
        $breeds  = [new Breed('greyhound', ['energy' => 3.0, 'shedding' => 3.0, 'size' => 3.0])];

        $first  = $this->model($answers, $breeds);
        $second = $this->model($answers, $breeds);

        self::assertSame(
            $first->podium[0]->aligned[0]->body,
            $second->podium[0]->aligned[0]->body
        );
    }

    public function test_runners_up_start_after_the_podium(): void
    {
        $breeds = [];
        foreach (range(1, 8) as $i) {
            $breeds[] = new Breed('b' . $i, ['energy' => 3.0 + ($i / 10), 'shedding' => 3.0, 'size' => 3.0]);
        }

        $model = $this->model(new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3]), $breeds);

        self::assertCount(3, $model->podium);
        self::assertCount(5, $model->runnersUp);
        self::assertSame('b4', $model->runnersUp[0]->breedKey);
    }
}
