<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Migration\MigrationPlan;
use Skilitsa\Core\Migration\MigrationReporter;
use Skilitsa\Core\Migration\PostChange;

final class MigrationReporterTest extends TestCase
{
    private MigrationReporter $reporter;

    protected function setUp(): void
    {
        $this->reporter = new MigrationReporter();
    }

    public function test_empty_plan_says_nothing_to_do(): void
    {
        $out = $this->reporter->render(new MigrationPlan());

        self::assertStringContainsString('nothing to do', $out);
        self::assertStringNotContainsString('WARNING', $out);
    }

    public function test_states_the_counts(): void
    {
        $plan = new MigrationPlan(
            [new PostChange(1, 'a_b', 'a-b', 'post', 'skilitsa_breed', 'T')],
            ['/a_b/ -> /breeds/a-b/'],
        );

        $out = $this->reporter->render($plan);

        self::assertStringContainsString('1 post(s) to change', $out);
        self::assertStringContainsString('1 type change(s)', $out);
        self::assertStringContainsString('1 slug change(s)', $out);
        self::assertStringContainsString('1 redirect(s)', $out);
    }

    public function test_warnings_appear_before_the_change_list(): void
    {
        // A human skimming 258 lines must see blockers without scrolling.
        $plan = new MigrationPlan(
            [new PostChange(1, 'a_b', 'a-b', 'post', 'skilitsa_breed', 'T')],
            [],
            ['slug collision on a-b'],
        );

        $out = $this->reporter->render($plan);

        self::assertLessThan(
            strpos($out, '#1'),
            strpos($out, 'slug collision'),
            'warnings must be rendered above the changes'
        );
    }

    public function test_a_plan_with_warnings_says_it_must_not_be_applied(): void
    {
        $plan = new MigrationPlan([], [], ['something is wrong']);

        $out = $this->reporter->render($plan);

        self::assertStringContainsString('DO NOT APPLY', $out);
        self::assertStringContainsString('Refusing to apply', $out);
    }

    public function test_lists_both_kinds_of_change_for_one_post(): void
    {
        $plan = new MigrationPlan([new PostChange(42, 'x_y', 'x-y', 'post', 'skilitsa_breed', 'T')]);

        $out = $this->reporter->render($plan);

        self::assertStringContainsString('#42', $out);
        self::assertStringContainsString('post -> skilitsa_breed', $out);
        self::assertStringContainsString('x_y -> x-y', $out);
    }

    public function test_a_type_only_change_does_not_claim_a_slug_change(): void
    {
        $plan = new MigrationPlan([new PostChange(7, 'same', 'same', 'post', 'skilitsa_breed', 'T')]);

        $out = $this->reporter->render($plan);

        self::assertStringContainsString('0 slug change(s)', $out);
        self::assertStringNotContainsString('same -> same', $out);
    }
}
