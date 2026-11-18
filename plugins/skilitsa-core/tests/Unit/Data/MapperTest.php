<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Data;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\Mapper\BreedMapper;
use Skilitsa\Core\Data\Mapper\LinkMapper;
use Skilitsa\Core\Data\Mapper\QuestionMapper;
use Skilitsa\Core\Data\Mapper\TraitMapper;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;

final class MapperTest extends TestCase
{
    public function test_trait_mapper_builds_definitions_keyed_by_trait(): void
    {
        $mapper = new TraitMapper();
        $traits = $mapper->fromRows([
            ['trait_key' => 'shedding', 'penalty_over' => '1.00', 'penalty_under' => '0.00', 'global_weight' => '1.00'],
            ['trait_key' => 'size', 'penalty_over' => '1.00', 'penalty_under' => '1.00', 'global_weight' => '1.50'],
        ]);

        self::assertSame(['shedding', 'size'], array_keys($traits));
        self::assertSame(1.0, $traits['shedding']->penaltyOver);
        self::assertSame(0.0, $traits['shedding']->penaltyUnder);
        self::assertSame(1.5, $traits['size']->globalWeight);
        self::assertSame([], $mapper->skipped());
    }

    public function test_trait_mapper_skips_rows_with_no_key(): void
    {
        $mapper = new TraitMapper();
        $traits = $mapper->fromRows([
            ['trait_key' => '', 'penalty_over' => '1.00', 'penalty_under' => '1.00', 'global_weight' => '1.00'],
        ]);

        self::assertSame([], $traits);
        self::assertCount(1, $mapper->skipped());
    }

    public function test_trait_mapper_skips_rows_with_non_numeric_penalties(): void
    {
        $mapper = new TraitMapper();
        $traits = $mapper->fromRows([
            ['trait_key' => 'bad', 'penalty_over' => 'lots', 'penalty_under' => '1.00', 'global_weight' => '1.00'],
        ]);

        self::assertSame([], $traits);
        self::assertStringContainsString('bad', $mapper->skipped()[0]);
    }

    public function test_trait_mapper_defaults_missing_weight_to_one(): void
    {
        $mapper = new TraitMapper();
        $traits = $mapper->fromRows([
            ['trait_key' => 'x', 'penalty_over' => '1.00', 'penalty_under' => '1.00'],
        ]);

        self::assertSame(1.0, $traits['x']->globalWeight);
    }

    public function test_question_mapper_builds_questions(): void
    {
        $mapper    = new QuestionMapper();
        $questions = $mapper->fromRows([
            ['question_key' => 'Q01', 'weight' => '2.00'],
            ['question_key' => 'Q02', 'weight' => '1.00'],
        ]);

        self::assertCount(2, $questions);
        self::assertSame('Q01', $questions[0]->key);
        self::assertSame(2.0, $questions[0]->weight);
    }

    public function test_question_mapper_skips_empty_keys(): void
    {
        $mapper    = new QuestionMapper();
        $questions = $mapper->fromRows([['question_key' => '', 'weight' => '1.00']]);

        self::assertSame([], $questions);
        self::assertCount(1, $mapper->skipped());
    }

    public function test_link_mapper_reads_direction(): void
    {
        $mapper = new LinkMapper();
        $links  = $mapper->fromRows([
            ['question_key' => 'Q01', 'trait_key' => 'energy', 'direction' => 'same', 'weight' => '1.00'],
            ['question_key' => 'Q13', 'trait_key' => 'barking', 'direction' => 'inverse', 'weight' => '0.60'],
        ]);

        self::assertCount(2, $links);
        self::assertFalse($links[0]->isInverse());
        self::assertTrue($links[1]->isInverse());
        self::assertSame(0.6, $links[1]->weight);
    }

    public function test_link_mapper_defaults_unknown_direction_to_same_and_records_it(): void
    {
        // A typo in the source data must not fatal the whole quiz, but it must
        // be visible - silently guessing is how v1 lost the direction column.
        $mapper = new LinkMapper();
        $links  = $mapper->fromRows([
            ['question_key' => 'Q01', 'trait_key' => 'energy', 'direction' => 'sideways', 'weight' => '1.00'],
        ]);

        self::assertCount(1, $links);
        self::assertSame(QuestionTraitLink::SAME, $links[0]->direction);
        self::assertCount(1, $mapper->skipped());
        self::assertStringContainsString('sideways', $mapper->skipped()[0]);
    }

    public function test_link_mapper_skips_rows_missing_either_key(): void
    {
        $mapper = new LinkMapper();
        $links  = $mapper->fromRows([
            ['question_key' => 'Q01', 'trait_key' => '', 'direction' => 'same', 'weight' => '1.00'],
            ['question_key' => '', 'trait_key' => 'energy', 'direction' => 'same', 'weight' => '1.00'],
        ]);

        self::assertSame([], $links);
        self::assertCount(2, $mapper->skipped());
    }

    public function test_breed_mapper_groups_flat_rows_by_breed(): void
    {
        $mapper = new BreedMapper();
        $breeds = $mapper->fromRows([
            ['breed_key' => 'greyhound', 'trait_key' => 'size', 'value' => '4.00'],
            ['breed_key' => 'greyhound', 'trait_key' => 'barking', 'value' => '1.00'],
            ['breed_key' => 'malinois', 'trait_key' => 'size', 'value' => '4.00'],
        ]);

        self::assertCount(2, $breeds);
        self::assertSame('greyhound', $breeds[0]->key);
        self::assertSame(4.0, $breeds[0]->traitValue('size'));
        self::assertSame(1.0, $breeds[0]->traitValue('barking'));
        self::assertNull($breeds[1]->traitValue('barking'));
    }

    public function test_breed_mapper_skips_non_numeric_values(): void
    {
        $mapper = new BreedMapper();
        $breeds = $mapper->fromRows([
            ['breed_key' => 'greyhound', 'trait_key' => 'size', 'value' => 'big'],
            ['breed_key' => 'greyhound', 'trait_key' => 'barking', 'value' => '1.00'],
        ]);

        self::assertCount(1, $breeds);
        self::assertNull($breeds[0]->traitValue('size'));
        self::assertSame(1.0, $breeds[0]->traitValue('barking'));
        self::assertCount(1, $mapper->skipped());
    }

    public function test_breed_mapper_preserves_row_order(): void
    {
        $mapper = new BreedMapper();
        $breeds = $mapper->fromRows([
            ['breed_key' => 'zulu', 'trait_key' => 'size', 'value' => '1.00'],
            ['breed_key' => 'alpha', 'trait_key' => 'size', 'value' => '1.00'],
        ]);

        // Ordering is the query's job, not the mapper's. Ranking has its own
        // deterministic tiebreak, so the mapper must not reorder silently.
        self::assertSame(['zulu', 'alpha'], array_map(static fn ($b) => $b->key, $breeds));
    }
}
