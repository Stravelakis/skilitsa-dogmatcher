<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Session;

use InvalidArgumentException;

/**
 * Result-URL tokens.
 *
 * Twelve characters from a 36-symbol alphabet is about 62 bits: unguessable in
 * practice, which is what matters for a URL someone may share with their
 * partner. It is not a secret - result pages are noindex and expire after 90
 * days - so this is deliberately not a signed token.
 *
 * The alphabet excludes nothing for readability on purpose: these are copied
 * from a browser bar, not read aloud.
 */
final class Token
{
    public const LENGTH   = 12;
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz0123456789';

    public static function generate(): string
    {
        $alphabetSize = strlen(self::ALPHABET);
        $token        = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $token .= self::ALPHABET[random_int(0, $alphabetSize - 1)];
        }

        return $token;
    }

    public static function isValid(string $token): bool
    {
        return strlen($token) === self::LENGTH
            && preg_match('/^[a-z0-9]+$/', $token) === 1;
    }

    /** @throws InvalidArgumentException */
    public static function assertValid(string $token): void
    {
        if (!self::isValid($token)) {
            throw new InvalidArgumentException('Malformed result token.');
        }
    }
}
