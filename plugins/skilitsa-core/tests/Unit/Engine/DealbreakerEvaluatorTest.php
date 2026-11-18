<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\DealbreakerEvaluator;
use Skilitsa\Core\Engine\TraitCostCalculator;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\Engine\Value\UserTraitProfile;

final class DealbreakerEvaluatorTest extends TestCase
{
    private DealbreakerEvaluator $evaluator;
    private ScoringConfig $config;
    /** @var array<string,TraitDefinition> */
    private array $traits;

    protected function setUp(): void
    {
        $this->evaluator = new DealbreakerEvaluator(new TraitCostCalculator());
        $this->config    = ScoringConfig::defaults();
        $this->traits    = [
            'barking'  => new TraitDefinition('barking', 1.0, 0.0),
            'shedding' => new TraitDefinition('shedding', 1.0, 0.0),
            'size'     => new TraitDefinition('size', 1.0, 1.0),
        ];
    }

    public function test_marked_question_makes_its_traits_salient(): void
    {
        $salient = $this->evaluator->salientTraitKeys(
            new AnswerSet(['Q13' => 1, 'Q21' => 3], dealbreakerKeys: ['Q13']),
            [
                new QuestionTraitLink('Q13', 'barking'),
                new QuestionTraitLink('Q21', 'shedding'),
            ],
        );

        self::assertArrayHasKey('barking', $salient);
        self::assertArrayNotHasKey('shedding', $salient);
    }

    public function test_one_marked_question_makes_all_its_traits_salient(): void
    {
        $salient = $this->evaluator->salientTraitKeys(
            new AnswerSet(['Q06' => 2], dealbreakerKeys: ['Q06']),
            [
                new QuestionTraitLink('Q06', 'size'),
                new QuestionTraitLink('Q06', 'barking'),
            ],
        );

        self::assertSame(['barking', 'size'], array_keys($salient));
    }

    public function test_ignored_question_is_never_salient_even_if_marked(): void
    {
        $salient = $this->evaluator->salientTraitKeys(
            new AnswerSet(['Q13' => 1], dealbreakerKeys: ['Q13'], ignoredKeys: ['Q13']),
            [new QuestionTraitLink('Q13', 'barking')],
        );

        self::assertSame([], $salient);
    }

    public function test_violation_when_cost_reaches_threshold(): void
    {
        // User wants barking 1, breed barks 4. barking is (over 1.0, under 0.0),
        // so rawCost = 3.0, above the default threshold of 2.0.
        $violations = $this->evaluator->violations(
            ['barking' => true],
            new UserTraitProfile(['barking' => 1.0]),
            new Breed('loud', ['barking' => 4.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame(['barking'], $violations);
    }

    public function test_no_violation_when_cost_below_threshold(): void
    {
        $violations = $this->evaluator->violations(
            ['barking' => true],
            new UserTraitProfile(['barking' => 3.0]),
            new Breed('mild', ['barking' => 4.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame([], $violations);
    }

    public function test_no_violation_on_the_free_side_of_an_asymmetric_trait(): void
    {
        // User tolerates barking 5, breed barks 1. Big distance, zero cost,
        // so a barking dealbreaker must NOT fire.
        $violations = $this->evaluator->violations(
            ['barking' => true],
            new UserTraitProfile(['barking' => 5.0]),
            new Breed('quiet', ['barking' => 1.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame([], $violations);
    }

    public function test_violation_counted_once_per_trait_not_once_per_link(): void
    {
        // v1 applied the penalty once per mapping row, so a question feeding
        // three traits was punished three times over.
        $violations = $this->evaluator->violations(
            ['barking' => true, 'shedding' => true],
            new UserTraitProfile(['barking' => 1.0, 'shedding' => 1.0]),
            new Breed('loud_and_hairy', ['barking' => 5.0, 'shedding' => 5.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame(['barking', 'shedding'], $violations);
        self::assertCount(2, $violations);
    }

    public function test_missing_breed_value_cannot_violate(): void
    {
        $violations = $this->evaluator->violations(
            ['barking' => true],
            new UserTraitProfile(['barking' => 1.0]),
            new Breed('unknown', ['shedding' => 3.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame([], $violations);
    }

    public function test_missing_user_value_cannot_violate(): void
    {
        $violations = $this->evaluator->violations(
            ['barking' => true],
            new UserTraitProfile([]),
            new Breed('loud', ['barking' => 5.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame([], $violations);
    }

    public function test_violations_are_sorted_for_determinism(): void
    {
        $violations = $this->evaluator->violations(
            ['size' => true, 'barking' => true],
            new UserTraitProfile(['barking' => 1.0, 'size' => 1.0]),
            new Breed('big_loud', ['barking' => 5.0, 'size' => 5.0]),
            $this->traits,
            $this->config,
        );

        self::assertSame(['barking', 'size'], $violations);
    }
}
