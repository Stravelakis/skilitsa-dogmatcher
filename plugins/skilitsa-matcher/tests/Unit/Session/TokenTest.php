<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Tests\Unit\Session;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Skilitsa\Matcher\Session\Token;

final class TokenTest extends TestCase
{
    public function test_generates_a_token_of_the_declared_length(): void
    {
        self::assertSame(Token::LENGTH, strlen(Token::generate()));
    }

    public function test_generated_tokens_use_only_the_safe_alphabet(): void
    {
        for ($i = 0; $i < 200; $i++) {
            self::assertMatchesRegularExpression('/^[a-z0-9]+$/', Token::generate());
        }
    }

    public function test_generated_tokens_are_unique_across_a_thousand_draws(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $seen[Token::generate()] = true;
        }

        self::assertCount(1000, $seen, 'a collision in 1000 draws means the entropy is wrong');
    }

    public function test_every_generated_token_validates(): void
    {
        for ($i = 0; $i < 500; $i++) {
            self::assertTrue(Token::isValid(Token::generate()));
        }
    }

    public function test_rejects_the_wrong_length(): void
    {
        self::assertFalse(Token::isValid('abc'));
        self::assertFalse(Token::isValid(str_repeat('a', Token::LENGTH + 1)));
        self::assertFalse(Token::isValid(''));
    }

    public function test_rejects_characters_outside_the_alphabet(): void
    {
        self::assertFalse(Token::isValid('ABCDEFGHIJKL'), 'uppercase');
        self::assertFalse(Token::isValid('abcdefghijk-'), 'punctuation');
        self::assertFalse(Token::isValid('abcdefghij k'), 'whitespace');
        self::assertFalse(Token::isValid('../etc/passw'), 'path traversal');
        self::assertFalse(Token::isValid('abcdefghij%2'), 'percent encoding');
    }

    public function test_assert_valid_throws_on_a_malformed_token(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Token::assertValid('nope');
    }

    public function test_assert_valid_is_silent_on_a_good_token(): void
    {
        Token::assertValid(Token::generate());

        // Reaching here without an exception is the assertion.
        self::assertTrue(true);
    }
}
