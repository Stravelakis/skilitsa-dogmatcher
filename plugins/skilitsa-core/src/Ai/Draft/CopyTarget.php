<?php
declare(strict_types=1);

namespace Skilitsa\Core\Ai\Draft;

use InvalidArgumentException;

/**
 * Which pieces of copy an AI task is allowed to propose changes to.
 *
 * A deliberate whitelist rather than "any row in any table". Three tasks write
 * copy drafts, and the cost of getting the addressing wrong is a suggestion
 * applied to the wrong sentence - which nobody would notice until a visitor
 * read it.
 *
 * A target is written as `kind:key`, so `template:pos_size_1` or `trait:barking`
 * or `media:5943`. The kind decides which table and which columns are legal.
 */
final class CopyTarget
{
    public const TEMPLATE = 'template';
    public const TRAIT    = 'trait';
    public const QUESTION = 'question';
    public const MEDIA    = 'media';

    /** kind => the only fields that may be written on it. */
    private const FIELDS = [
        self::TEMPLATE => ['body'],
        self::TRAIT    => ['label', 'description', 'scale_low', 'scale_high',
                           'warning_too_low', 'warning_too_high'],
        self::QUESTION => ['label', 'label_low', 'label_high', 'helper_text'],
        self::MEDIA    => ['alt'],
    ];

    private function __construct(
        public readonly string $kind,
        public readonly string $key,
    ) {
    }

    public static function of(string $kind, string $key): self
    {
        if (!isset(self::FIELDS[$kind])) {
            throw new InvalidArgumentException(sprintf('Unknown copy target kind "%s".', $kind));
        }

        if (trim($key) === '') {
            throw new InvalidArgumentException('A copy target needs a key.');
        }

        return new self($kind, trim($key));
    }

    /** Parses `kind:key` back out of a stored draft. Null when it is not one. */
    public static function parse(string $targetKey): ?self
    {
        $at = strpos($targetKey, ':');

        if ($at === false) {
            return null;
        }

        $kind = substr($targetKey, 0, $at);
        $key  = substr($targetKey, $at + 1);

        if (!isset(self::FIELDS[$kind]) || trim($key) === '') {
            return null;
        }

        return new self($kind, $key);
    }

    public function address(): string
    {
        return $this->kind . ':' . $this->key;
    }

    public function allows(string $field): bool
    {
        return in_array($field, self::FIELDS[$this->kind], true);
    }

    /** @return list<string> */
    public function fields(): array
    {
        return self::FIELDS[$this->kind];
    }
}
