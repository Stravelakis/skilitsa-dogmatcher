<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Import;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Skilitsa\Core\Import\ImportExecutor;
use Skilitsa\Core\Import\ImportPlan;
use Skilitsa\Core\Import\ImportReporter;
use Skilitsa\Core\Migration\MigrationExecutor;
use Skilitsa\Core\Migration\MigrationPlan;
use Skilitsa\Core\Migration\PostChange;

/**
 * The refusal rules on both executors.
 *
 * These are the guards that stand between a bad plan and 258 live URLs, so
 * they are tested without WordPress: an unsafe plan must be rejected before
 * any database call is reached.
 */
final class ExecutorGuardTest extends TestCase
{
    private function safePlan(): ImportPlan
    {
        return new ImportPlan(
            traits: ['energy' => ['label' => 'E']],
            questions: ['Q01' => ['label' => 'Q']],
            links: [['question_key' => 'Q01', 'trait_key' => 'energy', 'direction' => 'same', 'weight' => '1']],
            templates: ['pos_energy' => 'ok'],
            breedTraits: ['greyhound' => ['energy' => 3.0]],
        );
    }

    public function test_import_refuses_a_plan_with_errors(): void
    {
        // v1 wrote whenever its result array was non-empty - which it always
        // was - so a failed fetch replaced 397 KB of good data with nothing.
        $plan = new ImportPlan(errors: ['Tab BREEDS is missing required columns: breed_key.']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to apply');

        (new ImportExecutor())->apply($plan, dryRun: true);
    }

    public function test_import_refuses_a_plan_with_no_breeds_even_without_errors(): void
    {
        $plan = new ImportPlan(
            traits: ['energy' => ['label' => 'E']],
            questions: ['Q01' => ['label' => 'Q']],
            breedTraits: [],
        );

        self::assertFalse($plan->isSafeToApply());

        $this->expectException(RuntimeException::class);
        (new ImportExecutor())->apply($plan, dryRun: true);
    }

    public function test_import_dry_run_reports_counts_without_touching_the_database(): void
    {
        // No $wpdb exists in this test process, so reaching a query would fatal.
        $written = (new ImportExecutor())->apply($this->safePlan(), dryRun: true);

        self::assertSame(1, $written['trait_definitions']);
        self::assertSame(1, $written['questions']);
        self::assertSame(1, $written['question_trait_map']);
        self::assertSame(1, $written['breed_traits']);
    }

    public function test_migration_refuses_a_plan_with_warnings(): void
    {
        $plan = new MigrationPlan(
            [new PostChange(1, 'a_b', 'a-b', 'post', 'skilitsa_breed', 'T')],
            [],
            ['Renaming "a_b" would collide with existing slug "a-b".'],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unresolved warning');

        (new MigrationExecutor())->apply($plan, dryRun: true);
    }

    public function test_migration_can_be_forced_past_warnings_and_records_it(): void
    {
        $plan = new MigrationPlan(
            [new PostChange(1, 'a_b', 'a-b', 'post', 'skilitsa_breed', 'T')],
            [],
            ['something a human should have looked at'],
        );

        $result = (new MigrationExecutor())->apply($plan, dryRun: true, force: true);

        self::assertSame(1, $result['applied']);
        self::assertStringContainsString('FORCED', $result['log'][0]);
    }

    public function test_migration_dry_run_builds_redirects_without_writing(): void
    {
        $plan = new MigrationPlan([
            new PostChange(1, 'border_collie', 'border-collie', 'post', 'skilitsa_breed', 'T'),
            new PostChange(2, 'greyhound', 'greyhound', 'post', 'skilitsa_breed', 'T'),
        ]);

        $result = (new MigrationExecutor())->apply($plan, dryRun: true);

        self::assertSame(2, $result['applied']);
        self::assertSame(0, $result['skipped']);
        self::assertSame(
            ['from' => '/border_collie/', 'to' => '/breeds/border-collie/'],
            $result['redirects'][0]
        );
        self::assertSame(
            ['from' => '/greyhound/', 'to' => '/breeds/greyhound/'],
            $result['redirects'][1],
            'a post whose slug is unchanged still moves under the new prefix'
        );
    }

    public function test_import_reporter_leads_with_errors_and_refuses(): void
    {
        $out = (new ImportReporter())->render(new ImportPlan(errors: ['BREEDS has no rows.']));

        self::assertStringContainsString('NOTHING WILL BE WRITTEN', $out);
        self::assertStringContainsString('NOT safe to apply', $out);
        self::assertLessThan(strpos($out, 'Would write'), strpos($out, 'ERROR'));
    }

    public function test_import_reporter_confirms_a_safe_plan(): void
    {
        $out = (new ImportReporter())->render($this->safePlan());

        self::assertStringContainsString('Safe to apply.', $out);
        self::assertStringNotContainsString('ERROR', $out);
    }

    public function test_import_reporter_truncates_a_long_warning_list(): void
    {
        $warnings = array_map(static fn (int $i): string => "warning {$i}", range(1, 40));

        $plan = new ImportPlan(
            traits: ['e' => []],
            questions: ['Q' => []],
            breedTraits: ['g' => ['e' => 3.0]],
            warnings: $warnings,
        );

        $out = (new ImportReporter())->render($plan);

        self::assertStringContainsString('40 warning(s)', $out);
        self::assertStringContainsString('and 15 more', $out);
        self::assertStringContainsString('Safe to apply.', $out, 'warnings alone do not block');
    }
}
