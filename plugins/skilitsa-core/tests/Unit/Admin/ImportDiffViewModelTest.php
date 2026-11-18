<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Admin;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Admin\View\ImportDiffViewModel;
use Skilitsa\Core\Import\ImportPlan;

/**
 * What an import would do, said out loud before it does it.
 *
 * v1 wrote whenever its result array was non-empty - which it always was - so a
 * failed fetch replaced a working matrix with nothing and reported success. The
 * executor already refuses such a plan; this is the same refusal made visible,
 * because a number that shrank is the one thing nobody notices in a log.
 */
final class ImportDiffViewModelTest extends TestCase
{
    private function plan(array $overrides = []): ImportPlan
    {
        return new ImportPlan(
            traits: $overrides['traits'] ?? ['size' => ['label' => 'Μέγεθος']],
            questions: $overrides['questions'] ?? ['Q01' => ['label' => 'Q']],
            breedTraits: $overrides['breedTraits'] ?? [
                'greyhound'     => ['size' => 4.0],
                'border-collie' => ['size' => 3.0],
            ],
            errors: $overrides['errors'] ?? [],
            warnings: $overrides['warnings'] ?? [],
        );
    }

    public function test_it_counts_what_each_table_would_gain_or_lose(): void
    {
        $model = ImportDiffViewModel::build(
            $this->plan(),
            ['breeds' => 1, 'traits' => 1, 'questions' => 1],
            ['greyhound']
        );

        self::assertSame(2, $model->line('breeds')->after);
        self::assertSame(1, $model->line('breeds')->before);
        self::assertSame(1, $model->line('breeds')->delta);
    }

    public function test_a_table_that_would_shrink_is_flagged(): void
    {
        $model = ImportDiffViewModel::build(
            $this->plan(),
            ['breeds' => 40],
            []
        );

        self::assertTrue($model->line('breeds')->isLoss);
        self::assertTrue($model->hasLosses());
    }

    public function test_breeds_that_would_disappear_are_named(): void
    {
        // "40 breeds fewer" is a number. "Akita, Beagle, ..." is a decision.
        $model = ImportDiffViewModel::build(
            $this->plan(),
            ['breeds' => 3],
            ['greyhound', 'border-collie', 'akita']
        );

        self::assertSame(['akita'], $model->disappearingBreeds);
    }

    public function test_a_breed_present_in_both_is_not_reported_as_lost(): void
    {
        $model = ImportDiffViewModel::build($this->plan(), [], ['greyhound']);

        self::assertSame([], $model->disappearingBreeds);
    }

    public function test_a_plan_with_errors_offers_no_apply_at_all(): void
    {
        // Not a disabled button: a disabled button is one CSS bug away from
        // being live.
        $model = ImportDiffViewModel::build(
            $this->plan(['errors' => ['Tab BREEDS was not fetched.']]),
            [],
            []
        );

        self::assertFalse($model->canApply);
        self::assertSame(['Tab BREEDS was not fetched.'], $model->errors);
    }

    public function test_a_plan_with_nothing_in_it_offers_no_apply(): void
    {
        $model = ImportDiffViewModel::build(
            $this->plan(['breedTraits' => []]),
            [],
            []
        );

        self::assertFalse($model->canApply);
    }

    public function test_a_good_plan_can_be_applied(): void
    {
        self::assertTrue(ImportDiffViewModel::build($this->plan(), [], [])->canApply);
    }

    public function test_warnings_are_shown_without_blocking(): void
    {
        // A breed missing one value is worth saying and must not stop 256 good
        // breeds reaching the site.
        $model = ImportDiffViewModel::build(
            $this->plan(['warnings' => ['Breed "akita" has no post.']]),
            [],
            []
        );

        self::assertTrue($model->canApply);
        self::assertSame(['Breed "akita" has no post.'], $model->warnings);
    }

    public function test_a_plan_that_changes_nothing_says_so(): void
    {
        $model = ImportDiffViewModel::build(
            $this->plan(),
            ['traits' => 1, 'questions' => 1, 'links' => 0, 'templates' => 0,
             'ui_texts' => 0, 'settings' => 0, 'breeds' => 2, 'trait_values' => 2],
            ['greyhound', 'border-collie']
        );

        self::assertTrue($model->isNoOp());
    }

    public function test_a_plan_that_changes_something_is_not_a_no_op(): void
    {
        $model = ImportDiffViewModel::build($this->plan(), ['breeds' => 1], ['greyhound']);

        self::assertFalse($model->isNoOp());
    }

    public function test_tuned_traits_are_listed_as_preserved(): void
    {
        // The screen has to state what it will not touch, or the owner has no
        // reason to believe his tuning survives.
        $model = ImportDiffViewModel::build($this->plan(), [], [], ['barking', 'shedding']);

        self::assertSame(['barking', 'shedding'], $model->preservedTraits);
    }
}
