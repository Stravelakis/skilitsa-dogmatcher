<?php
declare(strict_types=1);

namespace Skilitsa\Core\Engine;

use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\BreedMatch;
use Skilitsa\Core\Engine\Value\MatchResult;
use Skilitsa\Core\Engine\Value\Question;
use Skilitsa\Core\Engine\Value\QuestionTraitLink;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Engine\Value\TraitDefinition;

/**
 * The engine's only public entry point. Spec sections 4.8 and 4.10.
 *
 * Deliberately free of WordPress: give it plain value objects and it returns a
 * MatchResult. Plan 02 supplies the adapters that load these objects from the
 * database.
 */
final class MatchEngine
{
    private const PODIUM_SIZE = 3;

    public function __construct(
        private readonly UserTraitCalculator $traitCalculator,
        private readonly DealbreakerEvaluator $dealbreakers,
        private readonly BreedScorer $scorer,
    ) {
    }

    /** Wires the default object graph. */
    public static function create(): self
    {
        $costs = new TraitCostCalculator();

        return new self(
            new UserTraitCalculator(),
            new DealbreakerEvaluator($costs),
            new BreedScorer($costs, new VerdictClassifier($costs)),
        );
    }

    /**
     * @param list<Question>                $questions
     * @param list<QuestionTraitLink>       $links
     * @param array<string,TraitDefinition> $traits keyed by trait key
     * @param list<Breed>                   $breeds
     */
    public function run(
        AnswerSet $answers,
        array $questions,
        array $links,
        array $traits,
        array $breeds,
        ScoringConfig $config,
    ): MatchResult {
        $profile = $this->traitCalculator->calculate($answers, $questions, $links);
        $salient = $this->dealbreakers->salientTraitKeys($answers, $links);

        $ranked         = [];
        $excluded       = [];
        $violationTally = [];

        foreach ($breeds as $breed) {
            $violations = $this->dealbreakers->violations($salient, $profile, $breed, $traits, $config);

            $match = $this->scorer->score($profile, $breed, $traits, $salient, $violations, $config);

            if ($violations !== []) {
                $excluded[] = $match;
                foreach ($violations as $traitKey) {
                    $violationTally[$traitKey] = ($violationTally[$traitKey] ?? 0) + 1;
                }
                continue;
            }

            $ranked[] = $match;
        }

        $this->sortMatches($ranked);
        $this->sortMatches($excluded);

        return new MatchResult(
            ranked: $ranked,
            excluded: $excluded,
            traitsKnown: $profile->count(),
            hasEnoughEvidence: $profile->count() >= $config->minTraitsForResults,
            isCloseCall: $this->isCloseCall($ranked, $config),
            blockingDealbreaker: $ranked === [] ? $this->mostBlocking($violationTally) : null,
        );
    }

    /**
     * Highest score first. Ties break on full-precision cost, then on breed key,
     * so identical inputs always produce an identical podium regardless of the
     * order breeds arrived in.
     *
     * @param list<BreedMatch> $matches
     */
    private function sortMatches(array &$matches): void
    {
        usort($matches, static function (BreedMatch $a, BreedMatch $b): int {
            return $b->score <=> $a->score
                ?: $a->weightedCost <=> $b->weightedCost
                ?: strcmp($a->breedKey, $b->breedKey);
        });
    }

    /** @param list<BreedMatch> $ranked */
    private function isCloseCall(array $ranked, ScoringConfig $config): bool
    {
        if (count($ranked) < self::PODIUM_SIZE) {
            return false;
        }

        return ($ranked[0]->score - $ranked[self::PODIUM_SIZE - 1]->score) <= $config->closeCallPoints;
    }

    /** @param array<string,int> $tally */
    private function mostBlocking(array $tally): ?string
    {
        if ($tally === []) {
            return null;
        }

        // Sort by count descending, then key ascending, so the answer is stable.
        $keys = array_keys($tally);
        usort($keys, static fn (string $a, string $b): int => $tally[$b] <=> $tally[$a] ?: strcmp($a, $b));

        return $keys[0];
    }
}
