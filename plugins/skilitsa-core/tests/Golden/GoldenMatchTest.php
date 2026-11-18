<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Golden;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;

final class GoldenMatchTest extends TestCase
{
    public const FIXTURE_DIR = __DIR__ . '/../fixtures/golden';

    /** @return array<int|string,mixed> */
    public static function loadJson(string $name): array
    {
        $path = self::FIXTURE_DIR . '/' . $name . '.json';
        $raw  = file_get_contents($path);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read fixture: ' . $path);
        }

        return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string,TraitDefinition> */
    public static function traits(): array
    {
        $traits = [];
        foreach (self::loadJson('traits') as $row) {
            $traits[$row['key']] = new TraitDefinition(
                $row['key'],
                (float) $row['penalty_over'],
                (float) $row['penalty_under'],
                (float) $row['global_weight'],
            );
        }

        return $traits;
    }

    /** @return list<Question> */
    public static function questions(): array
    {
        return array_map(
            static fn (array $r): Question => new Question($r['key'], (float) $r['weight']),
            self::loadJson('questions')
        );
    }

    /** @return list<QuestionTraitLink> */
    public static function links(): array
    {
        return array_map(
            static fn (array $r): QuestionTraitLink => new QuestionTraitLink(
                $r['question'],
                $r['trait'],
                $r['direction'],
                (float) $r['weight']
            ),
            self::loadJson('links')
        );
    }

    /** @return list<Breed> */
    public static function breeds(): array
    {
        return array_map(
            static fn (array $r): Breed => new Breed($r['key'], $r['traits']),
            self::loadJson('breeds')
        );
    }

    /**
     * The engine's output reduced to comparable scalars.
     *
     * @param array<string,mixed> $case
     * @return array<string,mixed>
     */
    public static function actualFor(array $case): array
    {
        $result = MatchEngine::create()->run(
            new AnswerSet($case['answers'], $case['dealbreakers'], $case['ignored']),
            self::questions(),
            self::links(),
            self::traits(),
            self::breeds(),
            ScoringConfig::defaults(),
        );

        return [
            'ranking' => array_map(
                static fn ($m): array => ['breed' => $m->breedKey, 'score' => $m->score],
                $result->ranked
            ),
            'excluded'             => array_map(static fn ($m): string => $m->breedKey, $result->excluded),
            'traits_known'         => $result->traitsKnown,
            'has_enough_evidence'  => $result->hasEnoughEvidence,
            'is_close_call'        => $result->isCloseCall,
            'blocking_dealbreaker' => $result->blockingDealbreaker,
        ];
    }

    /** @return iterable<string,array{array<string,mixed>}> */
    public static function caseProvider(): iterable
    {
        foreach (self::loadJson('cases') as $case) {
            yield $case['name'] => [$case];
        }
    }

    /** @param array<string,mixed> $case */
    #[DataProvider('caseProvider')]
    public function test_golden_case(array $case): void
    {
        self::assertNotNull(
            $case['expected'],
            sprintf(
                'Case "%s" has no expected output. Run bin/regenerate-golden.php, review the values, then commit them.',
                $case['name']
            )
        );

        self::assertSame($case['expected'], self::actualFor($case));
    }

    public function test_every_breed_scores_above_zero_for_a_neutral_profile(): void
    {
        // The specific regression that made v1 useless: a neutral profile
        // produced 0% for all 257 breeds because nothing ever matched.
        $result = MatchEngine::create()->run(
            new AnswerSet(['Q01' => 3, 'Q02' => 3, 'Q03' => 3, 'Q04' => 3, 'Q05' => 3, 'Q06' => 3, 'Q07' => 3]),
            self::questions(),
            self::links(),
            self::traits(),
            self::breeds(),
            ScoringConfig::defaults(),
        );

        self::assertNotEmpty($result->ranked);
        foreach ($result->ranked as $match) {
            self::assertGreaterThan(0, $match->score, $match->breedKey . ' scored zero');
        }
    }

    public function test_rankings_are_not_all_identical(): void
    {
        // The other half of the v1 failure: identical scores meant the "ranking"
        // was just the source data's row order.
        $result = MatchEngine::create()->run(
            new AnswerSet(['Q01' => 1, 'Q02' => 1, 'Q03' => 1, 'Q04' => 5, 'Q05' => 5, 'Q06' => 1, 'Q07' => 1]),
            self::questions(),
            self::links(),
            self::traits(),
            self::breeds(),
            ScoringConfig::defaults(),
        );

        $scores = array_map(static fn ($m): int => $m->score, $result->ranked);
        self::assertGreaterThan(1, count(array_unique($scores)), 'All breeds scored the same');
    }
}
