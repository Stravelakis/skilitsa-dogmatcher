<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Data\BreedSource;
use Skilitsa\Core\Data\InMemorySources;
use Skilitsa\Core\Data\QuestionSource;
use Skilitsa\Core\Data\TraitSource;
use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;
use Skilitsa\Core\MatchService;

final class MatchServiceTest extends TestCase
{
    private function sources(): InMemorySources
    {
        return new InMemorySources(
            traits: [
                'energy' => new TraitDefinition('energy', 1.5, 0.5),
                'size'   => new TraitDefinition('size', 1.0, 1.0),
            ],
            questions: [new Question('Q01'), new Question('Q02')],
            links: [
                new QuestionTraitLink('Q01', 'energy'),
                new QuestionTraitLink('Q02', 'size'),
            ],
            breeds: [
                new Breed('couch_potato', ['energy' => 1.0, 'size' => 2.0]),
                new Breed('athlete', ['energy' => 5.0, 'size' => 4.0]),
            ],
        );
    }

    private function service(): MatchService
    {
        $sources = $this->sources();

        return new MatchService($sources, $sources, $sources, MatchEngine::create());
    }

    public function test_runs_a_match_from_its_sources(): void
    {
        $result = $this->service()->match(
            new AnswerSet(['Q01' => 1, 'Q02' => 2]),
            new ScoringConfig(minTraitsForResults: 2),
        );

        self::assertCount(2, $result->ranked);
        self::assertSame('couch_potato', $result->ranked[0]->breedKey);
        self::assertTrue($result->hasEnoughEvidence);
    }

    public function test_reverses_when_the_answers_reverse(): void
    {
        $result = $this->service()->match(
            new AnswerSet(['Q01' => 5, 'Q02' => 4]),
            new ScoringConfig(minTraitsForResults: 2),
        );

        self::assertSame('athlete', $result->ranked[0]->breedKey);
    }

    public function test_empty_sources_yield_an_empty_result_not_an_error(): void
    {
        $empty   = new InMemorySources([], [], [], []);
        $service = new MatchService($empty, $empty, $empty, MatchEngine::create());

        $result = $service->match(new AnswerSet([]), ScoringConfig::defaults());

        self::assertSame([], $result->ranked);
        self::assertFalse($result->hasEnoughEvidence);
    }

    public function test_sources_are_read_once_per_match(): void
    {
        // 257 breeds x 24 traits is a real query. The service must not
        // re-fetch inside a loop.
        $counting = new class ($this->sources()) implements TraitSource, QuestionSource, BreedSource {
            public int $breedCalls = 0;

            public function __construct(private readonly InMemorySources $inner)
            {
            }

            public function traits(): array
            {
                return $this->inner->traits();
            }

            public function questions(): array
            {
                return $this->inner->questions();
            }

            public function links(): array
            {
                return $this->inner->links();
            }

            public function breeds(): array
            {
                $this->breedCalls++;

                return $this->inner->breeds();
            }
        };

        $service = new MatchService($counting, $counting, $counting, MatchEngine::create());
        $service->match(new AnswerSet(['Q01' => 3, 'Q02' => 3]), new ScoringConfig(minTraitsForResults: 2));

        self::assertSame(1, $counting->breedCalls);
    }
}
