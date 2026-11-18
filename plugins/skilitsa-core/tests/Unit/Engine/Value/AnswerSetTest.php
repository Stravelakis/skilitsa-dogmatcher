<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine\Value;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\Value\AnswerSet;

final class AnswerSetTest extends TestCase
{
    public function test_returns_answers_and_null_for_unanswered(): void
    {
        $set = new AnswerSet(['Q01' => 4, 'Q02' => 1]);

        self::assertSame(4, $set->answerFor('Q01'));
        self::assertSame(1, $set->answerFor('Q02'));
        self::assertNull($set->answerFor('Q03'));
    }

    public function test_ignored_question_reports_no_answer(): void
    {
        // An ignored question must not contribute even if an answer lingers
        // in the payload from before the user toggled Ignore.
        $set = new AnswerSet(['Q01' => 5], ignoredKeys: ['Q01']);

        self::assertTrue($set->isIgnored('Q01'));
        self::assertNull($set->answerFor('Q01'));
        self::assertSame([], $set->activeQuestionKeys());
    }

    public function test_dealbreaker_flags(): void
    {
        $set = new AnswerSet(['Q01' => 5, 'Q02' => 2], dealbreakerKeys: ['Q02']);

        self::assertFalse($set->isDealbreaker('Q01'));
        self::assertTrue($set->isDealbreaker('Q02'));
    }

    public function test_active_keys_exclude_ignored_and_unanswered(): void
    {
        $set = new AnswerSet(
            ['Q01' => 3, 'Q02' => 4, 'Q03' => 2],
            ignoredKeys: ['Q02'],
        );

        self::assertSame(['Q01', 'Q03'], $set->activeQuestionKeys());
        self::assertSame(2, $set->count());
    }

    public function test_rejects_out_of_range_answers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AnswerSet(['Q01' => 7]);
    }

    public function test_rejects_zero_answer(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AnswerSet(['Q01' => 0]);
    }

    public function test_accepts_numeric_string_answers(): void
    {
        // The HTTP layer delivers strings; normalising here keeps the rest of
        // the engine free of casts.
        $set = new AnswerSet(['Q01' => '4']);
        self::assertSame(4, $set->answerFor('Q01'));
    }
}
