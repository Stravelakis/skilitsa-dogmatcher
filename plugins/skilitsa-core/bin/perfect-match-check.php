<?php
declare(strict_types=1);

/**
 * Answers one question empirically: what does a PERFECT breed score?
 *
 * If the answer is 100 (before shrinkage), then costs are already normalised
 * against the best achievable result and no extra rescaling is needed. If it
 * is well below 100, something is capping the top end and should be found.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../tests/Golden/GoldenMatchTest.php';

use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\UserTraitCalculator;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\Breed;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Tests\Golden\GoldenMatchTest;

$questions = GoldenMatchTest::questions();
$links     = GoldenMatchTest::links();
$traits    = GoldenMatchTest::traits();
$realBreeds = GoldenMatchTest::breeds();

$calc = new UserTraitCalculator();

printf(
    "%-38s %8s %8s %8s%s",
    'case', 'perfect', 'best real', 'gap',
    PHP_EOL
);
printf("%s%s", str_repeat('-', 66), PHP_EOL);

foreach (GoldenMatchTest::loadJson('cases') as $case) {
    $answers = new AnswerSet($case['answers'], $case['dealbreakers'], $case['ignored']);

    // Build a synthetic breed whose every trait equals what this user asked
    // for. Nothing can fit them better than this.
    $profile = $calc->calculate($answers, $questions, $links);
    $perfect = new Breed('__perfect__', $profile->values);

    $withPerfect = MatchEngine::create()->run(
        $answers,
        $questions,
        $links,
        $traits,
        array_merge($realBreeds, [$perfect]),
        ScoringConfig::defaults(),
    );

    $perfectScore = null;
    $bestReal     = null;
    foreach ($withPerfect->ranked as $m) {
        if ($m->breedKey === '__perfect__') {
            $perfectScore = $m->score;
        } elseif ($bestReal === null) {
            $bestReal = $m->score;
        }
    }

    if ($perfectScore === null) {
        printf("%-38s %8s %8s %8s%s", $case['name'], 'excluded', '-', '-', PHP_EOL);
        continue;
    }

    printf(
        "%-38s %8d %8s %8s%s",
        $case['name'],
        $perfectScore,
        $bestReal === null ? '-' : (string) $bestReal,
        $bestReal === null ? '-' : (string) ($perfectScore - $bestReal),
        PHP_EOL
    );
}

echo PHP_EOL;
echo 'A "perfect" column at or near 100 means costs are already scaled so that', PHP_EOL;
echo 'the best achievable match reads as 100. Whatever it falls short by is', PHP_EOL;
echo 'shrinkage, which is deliberate: it encodes how much we actually know.', PHP_EOL;
