<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;

final class MatchEngineTest extends TestCase
{
    private MatchEngine $engine;

    protected function setUp(): void
    {
        $this->engine = MatchEngine::create();
    }

    /**
     * Ten traits, ten questions, one link each. Enough to clear the default
     * minimum-evidence floor of 8.
     *
     * @return array{list<Question>,list<QuestionTraitLink>,array<string,TraitDefinition>}
     */
    private function scaffold(int $count = 10): array
    {
        $questions = $links = $traits = [];
        for ($i = 1; $i <= $count; $i++) {
            $q = sprintf('Q%02d', $i);
            $t = 't' . $i;
            $questions[] = new Question($q);
            $links[]     = new QuestionTraitLink($q, $t);
            $traits[$t]  = new TraitDefinition($t, 1.0, 1.0);
        }

        return [$questions, $links, $traits];
    }

    /** @param list<string> $dealbreakers */
    private function answers(int $answer, int $count = 10, array $dealbreakers = []): AnswerSet
    {
        $a = [];
        for ($i = 1; $i <= $count; $i++) {
            $a[sprintf('Q%02d', $i)] = $answer;
        }

        return new AnswerSet($a, $dealbreakers);
    }

    private function breed(string $key, float $value, int $count = 10): Breed
    {
        $t = [];
        for ($i = 1; $i <= $count; $i++) {
            $t['t' . $i] = $value;
        }

        return new Breed($key, $t);
    }

    public function test_ranks_breeds_best_first(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3),
            $questions,
            $links,
            $traits,
            [$this->breed('far', 5.0), $this->breed('perfect', 3.0), $this->breed('near', 4.0)],
            ScoringConfig::defaults(),
        );

        self::assertSame(
            ['perfect', 'near', 'far'],
            array_map(static fn ($m) => $m->breedKey, $result->ranked)
        );
    }

    public function test_podium_is_the_first_three(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits,
            [
                $this->breed('a', 3.0), $this->breed('b', 3.5), $this->breed('c', 4.0),
                $this->breed('d', 4.5), $this->breed('e', 5.0),
            ],
            ScoringConfig::defaults(),
        );

        self::assertCount(3, $result->podium());
        self::assertSame(['a', 'b', 'c'], array_map(static fn ($m) => $m->breedKey, $result->podium()));
    }

    public function test_runners_up_start_after_the_podium(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits,
            [
                $this->breed('a', 3.0), $this->breed('b', 3.2), $this->breed('c', 3.4),
                $this->breed('d', 3.6), $this->breed('e', 3.8),
            ],
            ScoringConfig::defaults(),
        );

        self::assertSame(['d', 'e'], array_map(static fn ($m) => $m->breedKey, $result->runnersUp(7)));
    }

    public function test_ties_break_deterministically_by_breed_key(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        // Identical breeds under different keys must always come out in the
        // same order, or a shared /match/ link would show a different podium
        // on every load.
        $breeds = [
            $this->breed('zulu', 3.0),
            $this->breed('alpha', 3.0),
            $this->breed('mike', 3.0),
        ];

        $first  = $this->engine->run($this->answers(3), $questions, $links, $traits, $breeds, ScoringConfig::defaults());
        $second = $this->engine->run($this->answers(3), $questions, $links, $traits, array_reverse($breeds), ScoringConfig::defaults());

        $keys = static fn ($r) => array_map(static fn ($m) => $m->breedKey, $r->ranked);

        self::assertSame(['alpha', 'mike', 'zulu'], $keys($first));
        self::assertSame($keys($first), $keys($second), 'input order must not affect output order');
    }

    public function test_excluded_breeds_are_separated_from_the_ranking(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        // Q01 is a dealbreaker; user answers 1, so any breed at 5 on t1 violates.
        $rest = array_fill_keys(
            array_map(static fn ($i) => sprintf('Q%02d', $i), range(2, 10)),
            3
        );
        $answers = new AnswerSet(array_merge(['Q01' => 1], $rest), dealbreakerKeys: ['Q01']);

        $restTraits = array_fill_keys(array_map(static fn ($i) => 't' . $i, range(2, 10)), 3.0);
        $violating  = new Breed('violating', array_merge(['t1' => 5.0], $restTraits));
        $fine       = new Breed('fine', array_merge(['t1' => 1.0], $restTraits));

        $result = $this->engine->run($answers, $questions, $links, $traits, [$violating, $fine], ScoringConfig::defaults());

        self::assertSame(['fine'], array_map(static fn ($m) => $m->breedKey, $result->ranked));
        self::assertSame(['violating'], array_map(static fn ($m) => $m->breedKey, $result->excluded));
    }

    public function test_close_call_flagged_when_top_three_are_tight(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits,
            // Every trait differs by the same step here, so the fixture is far
            // more separable than real breeds, whose differences cancel across
            // 24 traits. At gamma 0.7 a step of 0.05 already spans 11 points;
            // 0.02 is what "tied" looks like on this surface.
            [$this->breed('a', 3.0), $this->breed('b', 3.02), $this->breed('c', 3.04)],
            ScoringConfig::defaults(),
        );

        self::assertTrue($result->isCloseCall);
    }

    public function test_close_call_not_flagged_when_the_winner_is_clear(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(1), $questions, $links, $traits,
            [$this->breed('a', 1.0), $this->breed('b', 3.0), $this->breed('c', 5.0)],
            ScoringConfig::defaults(),
        );

        self::assertFalse($result->isCloseCall);
    }

    public function test_close_call_needs_at_least_three_breeds(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits,
            [$this->breed('a', 3.0), $this->breed('b', 3.0)],
            ScoringConfig::defaults(),
        );

        self::assertFalse($result->isCloseCall);
    }

    public function test_too_few_traits_means_no_usable_result(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        // Only 3 questions answered; default floor is 8 traits.
        $result = $this->engine->run(
            $this->answers(3, 3), $questions, $links, $traits,
            [$this->breed('a', 3.0)],
            ScoringConfig::defaults(),
        );

        self::assertSame(3, $result->traitsKnown);
        self::assertFalse($result->hasEnoughEvidence);
    }

    public function test_enough_traits_means_usable_result(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits,
            [$this->breed('a', 3.0)],
            ScoringConfig::defaults(),
        );

        self::assertSame(10, $result->traitsKnown);
        self::assertTrue($result->hasEnoughEvidence);
    }

    public function test_names_the_dealbreaker_blocking_the_most_breeds(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        // Q01 and Q02 both dealbreakers. Every breed violates t1; only one
        // violates t2. t1 is therefore the one worth offering to relax.
        $answerMap = array_fill_keys(
            array_map(static fn ($i) => sprintf('Q%02d', $i), range(1, 10)),
            1
        );
        $answers = new AnswerSet($answerMap, dealbreakerKeys: ['Q01', 'Q02']);

        $mk = static function (string $key, float $t1, float $t2): Breed {
            $t = array_fill_keys(array_map(static fn ($i) => 't' . $i, range(3, 10)), 1.0);

            return new Breed($key, array_merge(['t1' => $t1, 't2' => $t2], $t));
        };

        $result = $this->engine->run(
            $answers, $questions, $links, $traits,
            [$mk('a', 5.0, 1.0), $mk('b', 5.0, 1.0), $mk('c', 5.0, 5.0)],
            ScoringConfig::defaults(),
        );

        self::assertSame([], $result->ranked);
        self::assertCount(3, $result->excluded);
        self::assertSame('t1', $result->blockingDealbreaker);
    }

    public function test_blocking_dealbreaker_is_null_when_results_exist(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits,
            [$this->breed('a', 3.0)],
            ScoringConfig::defaults(),
        );

        self::assertNull($result->blockingDealbreaker);
    }

    public function test_empty_breed_list_yields_an_empty_result_not_an_error(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            $this->answers(3), $questions, $links, $traits, [], ScoringConfig::defaults()
        );

        self::assertSame([], $result->ranked);
        self::assertSame([], $result->excluded);
        self::assertNull($result->blockingDealbreaker);
    }

    public function test_no_answers_yields_an_empty_profile_not_an_error(): void
    {
        [$questions, $links, $traits] = $this->scaffold();

        $result = $this->engine->run(
            new AnswerSet([]), $questions, $links, $traits,
            [$this->breed('a', 3.0)],
            ScoringConfig::defaults(),
        );

        self::assertSame(0, $result->traitsKnown);
        self::assertFalse($result->hasEnoughEvidence);
    }
}
