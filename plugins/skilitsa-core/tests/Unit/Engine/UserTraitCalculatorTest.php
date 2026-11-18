<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\UserTraitCalculator;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;

final class UserTraitCalculatorTest extends TestCase
{
    private UserTraitCalculator $calc;

    protected function setUp(): void
    {
        $this->calc = new UserTraitCalculator();
    }

    public function test_single_question_passes_answer_straight_through(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 4]),
            [new Question('Q01')],
            [new QuestionTraitLink('Q01', 'energy_outdoor')],
        );

        self::assertSame(4.0, $profile->valueFor('energy_outdoor'));
    }

    public function test_inverse_direction_flips_the_scale(): void
    {
        // "How much barking can you tolerate?" answered 5 (lots) means the user
        // wants a LOW barking score from the breed... or the reverse, depending
        // on how the question is worded. `inverse` is how the data says so.
        $profile = $this->calc->calculate(
            new AnswerSet(['Q13' => 5]),
            [new Question('Q13')],
            [new QuestionTraitLink('Q13', 'barking', QuestionTraitLink::INVERSE)],
        );

        self::assertSame(1.0, $profile->valueFor('barking'));
    }

    public function test_inverse_of_three_is_three(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q13' => 3]),
            [new Question('Q13')],
            [new QuestionTraitLink('Q13', 'barking', QuestionTraitLink::INVERSE)],
        );

        self::assertSame(3.0, $profile->valueFor('barking'));
    }

    public function test_two_questions_average_into_one_trait(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 5, 'Q03' => 1]),
            [new Question('Q01'), new Question('Q03')],
            [
                new QuestionTraitLink('Q01', 'energy_outdoor'),
                new QuestionTraitLink('Q03', 'energy_outdoor'),
            ],
        );

        self::assertSame(3.0, $profile->valueFor('energy_outdoor'));
    }

    public function test_question_weight_and_link_weight_multiply(): void
    {
        // Q01 carries question weight 2.0 and link weight 1.0  -> effective 2.0
        // Q03 carries question weight 1.0 and link weight 0.5  -> effective 0.5
        // Weighted mean of 5 and 1: (5*2 + 1*0.5) / 2.5 = 10.5 / 2.5 = 4.2
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 5, 'Q03' => 1]),
            [new Question('Q01', 2.0), new Question('Q03', 1.0)],
            [
                new QuestionTraitLink('Q01', 'energy_outdoor', QuestionTraitLink::SAME, 1.0),
                new QuestionTraitLink('Q03', 'energy_outdoor', QuestionTraitLink::SAME, 0.5),
            ],
        );

        self::assertEqualsWithDelta(4.2, $profile->valueFor('energy_outdoor'), 0.0001);
    }

    public function test_one_question_can_feed_several_traits(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 4]),
            [new Question('Q01')],
            [
                new QuestionTraitLink('Q01', 'energy_outdoor'),
                new QuestionTraitLink('Q01', 'trainability', QuestionTraitLink::SAME, 0.6),
            ],
        );

        self::assertSame(4.0, $profile->valueFor('energy_outdoor'));
        self::assertSame(4.0, $profile->valueFor('trainability'));
    }

    public function test_ignored_question_contributes_nothing(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 5], ignoredKeys: ['Q01']),
            [new Question('Q01')],
            [new QuestionTraitLink('Q01', 'energy_outdoor')],
        );

        self::assertNull($profile->valueFor('energy_outdoor'));
        self::assertSame(0, $profile->count());
    }

    public function test_unanswered_question_contributes_nothing(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet([]),
            [new Question('Q01')],
            [new QuestionTraitLink('Q01', 'energy_outdoor')],
        );

        self::assertNull($profile->valueFor('energy_outdoor'));
    }

    public function test_zero_total_weight_yields_null_not_division_by_zero(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 4]),
            [new Question('Q01', 0.0)],
            [new QuestionTraitLink('Q01', 'energy_outdoor', QuestionTraitLink::SAME, 0.0)],
        );

        self::assertNull($profile->valueFor('energy_outdoor'));
    }

    public function test_link_to_unknown_question_is_ignored(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 4]),
            [new Question('Q01')],
            [
                new QuestionTraitLink('Q01', 'energy_outdoor'),
                new QuestionTraitLink('Q99', 'grooming'),
            ],
        );

        self::assertSame(4.0, $profile->valueFor('energy_outdoor'));
        self::assertNull($profile->valueFor('grooming'));
    }

    public function test_result_is_always_clamped_to_the_one_to_five_scale(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 5, 'Q02' => 1]),
            [new Question('Q01'), new Question('Q02')],
            [
                new QuestionTraitLink('Q01', 'size'),
                new QuestionTraitLink('Q02', 'size'),
            ],
        );

        $value = $profile->valueFor('size');
        self::assertGreaterThanOrEqual(1.0, $value);
        self::assertLessThanOrEqual(5.0, $value);
    }

    public function test_known_trait_keys_are_sorted_for_determinism(): void
    {
        $profile = $this->calc->calculate(
            new AnswerSet(['Q01' => 4, 'Q02' => 2]),
            [new Question('Q01'), new Question('Q02')],
            [
                new QuestionTraitLink('Q01', 'shedding'),
                new QuestionTraitLink('Q02', 'barking'),
            ],
        );

        self::assertSame(['barking', 'shedding'], $profile->knownTraitKeys());
    }
}
