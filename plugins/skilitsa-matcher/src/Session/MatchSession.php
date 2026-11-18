<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Session;

use Skilitsa\Core\Engine\Value\AnswerSet;

/** One run of the quiz. */
final class MatchSession
{
    /**
     * @param array<string,int> $answers      questionKey => 1..5
     * @param list<string>      $dealbreakers
     * @param list<string>      $ignored
     */
    public function __construct(
        public readonly string $token,
        public readonly array $answers = [],
        public readonly array $dealbreakers = [],
        public readonly array $ignored = [],
        public readonly string $flowMode = 'all',
        public readonly string $termsVersion = '',
        public readonly ?string $userUuid = null,
        public readonly ?string $resultSnapshot = null,
    ) {
    }

    public function toAnswerSet(): AnswerSet
    {
        return new AnswerSet($this->answers, $this->dealbreakers, $this->ignored);
    }

    public function withResult(string $snapshot): self
    {
        return new self(
            $this->token,
            $this->answers,
            $this->dealbreakers,
            $this->ignored,
            $this->flowMode,
            $this->termsVersion,
            $this->userUuid,
            $snapshot,
        );
    }
}
