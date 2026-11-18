<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\View;

use Skilitsa\Core\Engine\Value\BreedMatch;
use Skilitsa\Core\Engine\Value\MatchResult;
use Skilitsa\Core\Engine\Value\TraitVerdict;
use Skilitsa\Matcher\Copy\ExplanationPicker;

/**
 * Turns a MatchResult plus the copy tables into what the page renders.
 *
 * Pure: no WordPress, no database, no templates. Breed display data arrives as
 * a plain array so this can be tested exhaustively, and so a breed with no post
 * is an ordinary case rather than a special one.
 */
final class ResultViewModelBuilder
{
    private const PODIUM_SIZE      = 3;
    private const ALIGNED_PER_CARD = 2;
    private const PITFALLS_PER_CARD = 1;

    public function __construct(
        private readonly ExplanationPicker $picker = new ExplanationPicker(),
    ) {
    }

    /**
     * @param array<string,array{name:string,image:?string,permalink:?string}> $breedDisplay
     * @param array<string,string>                                             $templates
     * @param array<string,array<string,string>>                               $traitCopy
     */
    public function build(
        MatchResult $result,
        array $breedDisplay,
        array $templates,
        array $traitCopy,
        int $runnersUpLimit = 7,
    ): ResultViewModel {
        if (!$result->hasEnoughEvidence) {
            return new ResultViewModel(
                hasEnoughEvidence: false,
                traitsKnown: $result->traitsKnown,
                disclaimerTitle: trim((string) ($templates['results_disclaimer_title'] ?? '')),
                disclaimerBody: trim((string) ($templates['results_disclaimer_body'] ?? '')),
            );
        }

        $podium    = [];
        $runnersUp = [];

        foreach ($result->podium() as $match) {
            $podium[] = $this->card($match, $breedDisplay, $templates, $traitCopy);
        }

        foreach ($result->runnersUp($runnersUpLimit) as $match) {
            $runnersUp[] = $this->card($match, $breedDisplay, $templates, $traitCopy);
        }

        $excluded = [];
        foreach ($result->excluded as $match) {
            $excluded[] = $this->excludedCard($match, $breedDisplay, $traitCopy);
        }

        return new ResultViewModel(
            podium: $podium,
            runnersUp: $runnersUp,
            excluded: $excluded,
            isCloseCall: $result->isCloseCall,
            hasEnoughEvidence: true,
            blockingDealbreakerLabel: $this->traitLabel($result->blockingDealbreaker, $traitCopy),
            disclaimerTitle: trim((string) ($templates['results_disclaimer_title'] ?? '')),
            disclaimerBody: trim((string) ($templates['results_disclaimer_body'] ?? '')),
            traitsKnown: $result->traitsKnown,
        );
    }

    /**
     * @param array<string,array{name:string,image:?string,permalink:?string}> $breedDisplay
     * @param array<string,string>                                             $templates
     * @param array<string,array<string,string>>                               $traitCopy
     */
    private function card(
        BreedMatch $match,
        array $breedDisplay,
        array $templates,
        array $traitCopy,
    ): BreedCard {
        $display = $breedDisplay[$match->breedKey] ?? null;

        $aligned  = [];
        foreach ($match->bestAligned(self::ALIGNED_PER_CARD) as $verdict) {
            $line = $this->line($verdict, $templates, $traitCopy, $match->breedKey);
            if ($line !== null) {
                $aligned[] = $line;
            }
        }

        $pitfalls = [];
        foreach ($match->worstProblems(self::PITFALLS_PER_CARD) as $verdict) {
            $line = $this->line($verdict, $templates, $traitCopy, $match->breedKey);
            if ($line !== null) {
                $pitfalls[] = $line;
            }
        }

        return new BreedCard(
            breedKey: $match->breedKey,
            name: $this->name($match->breedKey, $display),
            score: $match->score,
            imageUrl: $display['image'] ?? null,
            permalink: $display['permalink'] ?? null,
            aligned: $aligned,
            pitfalls: $pitfalls,
        );
    }

    /**
     * @param array<string,array{name:string,image:?string,permalink:?string}> $breedDisplay
     * @param array<string,array<string,string>>                               $traitCopy
     */
    private function excludedCard(BreedMatch $match, array $breedDisplay, array $traitCopy): BreedCard
    {
        $display = $breedDisplay[$match->breedKey] ?? null;

        $conflicts = [];
        foreach ($match->exclusionReasons as $traitKey) {
            $label = $this->traitLabel($traitKey, $traitCopy);
            if ($label !== null) {
                $conflicts[] = $label;
            }
        }

        return new BreedCard(
            breedKey: $match->breedKey,
            name: $this->name($match->breedKey, $display),
            score: $match->score,
            imageUrl: $display['image'] ?? null,
            permalink: $display['permalink'] ?? null,
            conflicts: $conflicts,
        );
    }

    /**
     * @param array<string,string>               $templates
     * @param array<string,array<string,string>> $traitCopy
     */
    private function line(
        TraitVerdict $verdict,
        array $templates,
        array $traitCopy,
        string $variantSeed,
    ): ?TraitLine {
        $body = $this->picker->pick($verdict, $templates, $traitCopy, $variantSeed);
        if ($body === '') {
            return null;
        }

        return new TraitLine(
            traitKey: $verdict->traitKey,
            label: $this->traitLabel($verdict->traitKey, $traitCopy) ?? $verdict->traitKey,
            tier: $verdict->tier,
            body: $body,
        );
    }

    /** @param array{name:string,image:?string,permalink:?string}|null $display */
    private function name(string $breedKey, ?array $display): string
    {
        $name = trim((string) ($display['name'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        // Last resort so a card is never nameless, which is what v1 shipped
        // when it looked for a `label` column the sheet has never had.
        return ucwords(str_replace('-', ' ', $breedKey));
    }

    /** @param array<string,array<string,string>> $traitCopy */
    private function traitLabel(?string $traitKey, array $traitCopy): ?string
    {
        if ($traitKey === null || $traitKey === '') {
            return null;
        }

        $label = trim((string) ($traitCopy[$traitKey]['label'] ?? ''));

        return $label !== '' ? $label : $traitKey;
    }
}
