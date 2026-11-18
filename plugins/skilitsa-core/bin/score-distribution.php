<?php
declare(strict_types=1);

/**
 * Prints the score distribution for every golden case.
 *
 * Spec section 16 requires gamma and shrinkage_m to be calibrated against a
 * real distribution before launch. This is the instrument for that: run it,
 * look at the spread, change one constant, run it again.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../tests/Golden/GoldenMatchTest.php';

use Skilitsa\Core\Tests\Golden\GoldenMatchTest;

$cases = GoldenMatchTest::loadJson('cases');

printf("%-38s %5s %5s %6s  %s%s", 'case', 'top', 'low', 'spread', 'scores', PHP_EOL);
printf("%s%s", str_repeat('-', 100), PHP_EOL);

$spreads = [];

foreach ($cases as $case) {
    $actual  = GoldenMatchTest::actualFor($case);
    $ranking = $actual['ranking'];

    if ($ranking === []) {
        printf("%-38s %5s %5s %6s  (all excluded)%s", $case['name'], '-', '-', '-', PHP_EOL);
        continue;
    }

    $scores = array_column($ranking, 'score');
    $top    = max($scores);
    $low    = min($scores);
    $spread = $top - $low;
    $spreads[] = $spread;

    printf(
        "%-38s %5d %5d %6d  %s%s",
        $case['name'],
        $top,
        $low,
        $spread,
        implode(' ', $scores),
        PHP_EOL
    );
}

if ($spreads !== []) {
    printf(
        "%smean spread: %.1f points   widest: %d   narrowest: %d%s",
        PHP_EOL,
        array_sum($spreads) / count($spreads),
        max($spreads),
        min($spreads),
        PHP_EOL
    );
}
