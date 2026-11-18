<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Migration\MigrationPlanner;
use Skilitsa\Core\Migration\PostChange;

final class MigrationPlannerTest extends TestCase
{
    private MigrationPlanner $planner;

    protected function setUp(): void
    {
        $this->planner = new MigrationPlanner();
    }

    /** @return list<array<string,mixed>> */
    private function posts(array ...$rows): array
    {
        return array_map(
            static fn (array $r): array => [
                'ID'         => $r[0],
                'post_name'  => $r[1],
                'post_type'  => $r[2] ?? 'post',
                'post_title' => $r[3] ?? 'Title ' . $r[0],
            ],
            $rows
        );
    }

    public function test_plans_a_type_change_for_every_post(): void
    {
        $plan = $this->planner->plan(
            $this->posts([1, 'greyhound'], [2, 'belgian-malinois']),
            'skilitsa_breed',
            'breeds'
        );

        self::assertSame(2, $plan->count());
        foreach ($plan->changes as $change) {
            self::assertTrue($change->changesType());
            self::assertSame('skilitsa_breed', $change->newType);
        }
    }

    public function test_plans_slug_changes_only_where_needed(): void
    {
        $plan = $this->planner->plan(
            $this->posts([1, 'border_collie'], [2, 'greyhound']),
            'skilitsa_breed',
            'breeds'
        );

        self::assertCount(1, $plan->slugChanges());
        self::assertSame('border-collie', $plan->slugChanges()[0]->newSlug);
    }

    public function test_builds_a_redirect_for_every_post(): void
    {
        // The path prefix changes for all of them, so every old URL needs a
        // 301 even when the slug itself is unchanged.
        $plan = $this->planner->plan(
            $this->posts([1, 'greyhound'], [2, 'border_collie']),
            'skilitsa_breed',
            'breeds'
        );

        self::assertCount(2, $plan->redirects);
        self::assertContains('/greyhound/ -> /breeds/greyhound/', $plan->redirects);
        self::assertContains('/border_collie/ -> /breeds/border-collie/', $plan->redirects);
    }

    public function test_warns_about_collisions_without_planning_them(): void
    {
        $plan = $this->planner->plan(
            $this->posts([1, 'border_collie'], [2, 'border-collie']),
            'skilitsa_breed',
            'breeds'
        );

        self::assertNotEmpty($plan->warnings);

        $renamed = array_filter(
            $plan->slugChanges(),
            static fn (PostChange $c): bool => $c->oldSlug === 'border_collie'
        );
        self::assertSame([], $renamed, 'a colliding rename must not be planned');
    }

    public function test_a_post_already_of_the_target_type_is_still_planned_if_its_slug_is_wrong(): void
    {
        $plan = $this->planner->plan(
            $this->posts([1, 'border_collie', 'skilitsa_breed']),
            'skilitsa_breed',
            'breeds'
        );

        self::assertSame(1, $plan->count());
        self::assertFalse($plan->changes[0]->changesType());
        self::assertTrue($plan->changes[0]->changesSlug());
    }

    public function test_a_post_needing_nothing_is_excluded(): void
    {
        $plan = $this->planner->plan(
            $this->posts([1, 'greyhound', 'skilitsa_breed']),
            'skilitsa_breed',
            'breeds'
        );

        self::assertTrue($plan->isEmpty());
    }

    public function test_empty_input_yields_an_empty_plan(): void
    {
        $plan = $this->planner->plan([], 'skilitsa_breed', 'breeds');

        self::assertTrue($plan->isEmpty());
        self::assertSame([], $plan->redirects);
    }

    public function test_skips_rows_missing_an_id_or_slug_and_warns(): void
    {
        $plan = $this->planner->plan(
            [
                ['ID' => 0, 'post_name' => 'x', 'post_type' => 'post', 'post_title' => 'bad id'],
                ['ID' => 5, 'post_name' => '', 'post_type' => 'post', 'post_title' => 'no slug'],
            ],
            'skilitsa_breed',
            'breeds'
        );

        self::assertTrue($plan->isEmpty());
        self::assertCount(2, $plan->warnings);
    }

    public function test_plan_is_deterministic_for_the_same_input(): void
    {
        $posts = $this->posts([3, 'zulu_dog'], [1, 'alpha_dog'], [2, 'mike_dog']);

        $first  = $this->planner->plan($posts, 'skilitsa_breed', 'breeds');
        $second = $this->planner->plan($posts, 'skilitsa_breed', 'breeds');

        self::assertSame(
            array_map(static fn (PostChange $c): int => $c->postId, $first->changes),
            array_map(static fn (PostChange $c): int => $c->postId, $second->changes)
        );
    }

    public function test_type_changes_and_slug_changes_are_separately_countable(): void
    {
        $plan = $this->planner->plan(
            $this->posts(
                [1, 'border_collie', 'post'],
                [2, 'greyhound', 'post'],
                [3, 'rough_collie', 'skilitsa_breed'],
            ),
            'skilitsa_breed',
            'breeds'
        );

        self::assertCount(2, $plan->typeChanges());
        self::assertCount(2, $plan->slugChanges());
        self::assertSame(3, $plan->count());
    }
}
