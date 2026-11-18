<?php
declare(strict_types=1);

namespace Skilitsa\Core\Data;

use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\TraitDefinition;

/**
 * In-memory implementation of all three sources.
 *
 * Used by tests, and by the importer to run a dry-run match against candidate
 * data before it is written anywhere.
 */
final class InMemorySources implements TraitSource, QuestionSource, BreedSource
{
    /**
     * @param array<string,TraitDefinition> $traits
     * @param list<Question>                $questions
     * @param list<QuestionTraitLink>       $links
     * @param list<Breed>                   $breeds
     */
    public function __construct(
        private readonly array $traits = [],
        private readonly array $questions = [],
        private readonly array $links = [],
        private readonly array $breeds = [],
    ) {
    }

    public function traits(): array
    {
        return $this->traits;
    }

    public function questions(): array
    {
        return $this->questions;
    }

    public function links(): array
    {
        return $this->links;
    }

    public function breeds(): array
    {
        return $this->breeds;
    }
}
