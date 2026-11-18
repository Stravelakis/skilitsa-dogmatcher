<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use InvalidArgumentException;

/**
 * One edge from a question to a trait.
 *
 * This replaces v1's mapping row. v1 also carried an `answer_value` column and
 * joined on it; the source sheet never populated it, so nothing ever matched
 * and every breed scored 0%. There is deliberately no such column here.
 */
final class QuestionTraitLink
{
    public const SAME    = 'same';
    public const INVERSE = 'inverse';

    /**
     * Spellings accepted for INVERSE.
     *
     * The source sheet writes "invert". Treating that as unknown silently fell
     * back to "same", which would have scored barking, prey drive, leash
     * difficulty and heat tolerance backwards - the same class of silent
     * mismatch that made v1 return 0% for every breed.
     */
    private const INVERSE_SPELLINGS = ['inverse', 'invert', 'inverted', 'reverse', 'reversed', 'opposite'];

    /**
     * Maps a sheet spelling onto a canonical direction, or null when it is
     * genuinely unrecognised and the caller should warn.
     */
    public static function normaliseDirection(string $direction): ?string
    {
        $clean = strtolower(trim($direction));

        if ($clean === '' || $clean === self::SAME || $clean === 'direct' || $clean === 'normal') {
            return self::SAME;
        }

        return in_array($clean, self::INVERSE_SPELLINGS, true) ? self::INVERSE : null;
    }

    public function __construct(
        public readonly string $questionKey,
        public readonly string $traitKey,
        public readonly string $direction = self::SAME,
        public readonly float $weight = 1.0,
    ) {
        if ($questionKey === '' || $traitKey === '') {
            throw new InvalidArgumentException('Question and trait keys cannot be empty.');
        }
        if ($direction !== self::SAME && $direction !== self::INVERSE) {
            throw new InvalidArgumentException(
                sprintf('Unknown direction "%s"; expected "same" or "inverse".', $direction)
            );
        }
        if ($weight < 0.0) {
            throw new InvalidArgumentException('Link weight cannot be negative.');
        }
    }

    public function isInverse(): bool
    {
        return $this->direction === self::INVERSE;
    }
}
