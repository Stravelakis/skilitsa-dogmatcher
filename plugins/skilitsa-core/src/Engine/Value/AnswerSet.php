<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine\Value;

use InvalidArgumentException;

final class AnswerSet
{
    /** @var array<string,int> */
    public readonly array $answers;
    /** @var array<string,true> */
    private readonly array $dealbreakers;
    /** @var array<string,true> */
    private readonly array $ignored;

    /**
     * @param array<string,int|string> $answers         questionKey => 1..5
     * @param list<string>             $dealbreakerKeys
     * @param list<string>             $ignoredKeys
     */
    public function __construct(
        array $answers,
        array $dealbreakerKeys = [],
        array $ignoredKeys = [],
    ) {
        $clean = [];
        foreach ($answers as $key => $value) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException(
                    sprintf('Answer for "%s" is not numeric.', (string) $key)
                );
            }
            $int = (int) $value;
            if ($int < 1 || $int > 5) {
                throw new InvalidArgumentException(
                    sprintf('Answer for "%s" must be 1-5, got %d.', (string) $key, $int)
                );
            }
            $clean[(string) $key] = $int;
        }

        $this->answers      = $clean;
        $this->dealbreakers = array_fill_keys($dealbreakerKeys, true);
        $this->ignored      = array_fill_keys($ignoredKeys, true);
    }

    public function answerFor(string $questionKey): ?int
    {
        if ($this->isIgnored($questionKey)) {
            return null;
        }

        return $this->answers[$questionKey] ?? null;
    }

    public function isDealbreaker(string $questionKey): bool
    {
        return isset($this->dealbreakers[$questionKey]);
    }

    public function isIgnored(string $questionKey): bool
    {
        return isset($this->ignored[$questionKey]);
    }

    /** @return list<string> Questions that actually contribute to scoring. */
    public function activeQuestionKeys(): array
    {
        $keys = [];
        foreach ($this->answers as $key => $_) {
            if (!$this->isIgnored($key)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function count(): int
    {
        return count($this->activeQuestionKeys());
    }
}
