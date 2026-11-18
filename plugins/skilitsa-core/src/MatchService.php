<?php
declare(strict_types=1);

namespace Skilitsa\Core;

use Skilitsa\Core\Data\BreedSource;
use Skilitsa\Core\Data\QuestionSource;
use Skilitsa\Core\Data\TraitSource;
use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\MatchResult;
use Skilitsa\Core\Engine\Value\ScoringConfig;

/**
 * The single entry point the rest of the plugin calls to run a match.
 *
 * Depends on interfaces rather than $wpdb, so it is fully unit-testable and so
 * the importer can score candidate data before writing it.
 */
final class MatchService
{
    public function __construct(
        private readonly TraitSource $traits,
        private readonly QuestionSource $questions,
        private readonly BreedSource $breeds,
        private readonly MatchEngine $engine,
    ) {
    }

    public function match(AnswerSet $answers, ScoringConfig $config): MatchResult
    {
        return $this->engine->run(
            $answers,
            $this->questions->questions(),
            $this->questions->links(),
            $this->traits->traits(),
            $this->breeds->breeds(),
            $config,
        );
    }
}
