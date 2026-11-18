<?php
declare(strict_types=1);

/**
 * Sweeps gamma and shrinkage_m across the golden cases and reports the score
 * spread each combination produces.
 *
 * Spec section 16: gamma and shrinkage_m must be calibrated against a real
 * distribution before launch. A scoring curve that puts every breed between
 * 71% and 78% is useless however correct its arithmetic.
 *
 * Read the "mean" column for typical separation and "min top" for whether a
 * genuinely good match can still reach a satisfying number.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../tests/Golden/GoldenMatchTest.php';

use Skilitsa\Core\Engine\MatchEngine;
use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\ScoringConfig;
use Skilitsa\Core\Tests\Golden\GoldenMatchTest;

$cases     = GoldenMatchTest::loadJson('cases');
$questions = GoldenMatchTest::questions();
$links     = GoldenMatchTest::links();
$traits    = GoldenMatchTest::traits();
$breeds    = GoldenMatchTest::breeds();

$gammas     = [0.7, 1.0, 1.3, 1.6, 2.0];
$shrinkages = [0.0, 2.0, 4.0, 6.0];

printf(
    "%6s %6s %8s %8s %8s %8s%s",
    'gamma', 'm', 'mean sp', 'max sp', 'min top', 'max top',
    PHP_EOL
);
printf("%s%s", str_repeat('-', 56), PHP_EOL);

foreach ($gammas as $gamma) {
    foreach ($shrinkages as $m) {
        $config = new ScoringConfig(gamma: $gamma, shrinkageM: $m);

        $spreads = [];
        $tops    = [];

        foreach ($cases as $case) {
            $result = MatchEngine::create()->run(
                new AnswerSet($case['answers'], $case['dealbreakers'], $case['ignored']),
                $questions,
                $links,
                $traits,
                $breeds,
                $config,
            );

            if (count($result->ranked) < 2) {
                continue;
            }

            $scores    = array_map(static fn ($m2): int => $m2->score, $result->ranked);
            $spreads[] = max($scores) - min($scores);
            $tops[]    = max($scores);
        }

        if ($spreads === []) {
            continue;
        }

        printf(
            "%6.1f %6.1f %8.1f %8d %8d %8d%s",
            $gamma,
            $m,
            array_sum($spreads) / count($spreads),
            max($spreads),
            min($tops),
            max($tops),
            PHP_EOL
        );
    }
}

echo PHP_EOL, 'Wider mean spread separates breeds. Higher "min top" means a good', PHP_EOL;
echo 'match still feels good. Both matter; pick a row balancing them.', PHP_EOL;
