<?php

/**
 * Calibrates gamma and shrinkage_m against the REAL 257-breed matrix.
 *
 * Spec section 16 requires this before launch; the fixture calibration used six
 * breeds and cannot show the true distribution.
 */

use Skilitsa\Core\Engine\Value\AnswerSet;
use Skilitsa\Core\Engine\Value\ScoringConfig;

header('Content-Type: text/plain; charset=utf-8');

$service = skilitsa_core()->matchService();

// Six profiles spanning the space, using the real question keys.
$profiles = [
    'apartment / novice / quiet'   => ['Q01' => 1, 'Q02' => 1, 'Q03' => 1, 'Q05' => 5, 'Q06' => 1, 'Q07' => 1, 'Q13' => 1, 'Q19' => 2, 'Q20' => 1, 'Q23' => 1, 'Q30' => 3],
    'experienced / active / large' => ['Q01' => 5, 'Q02' => 5, 'Q03' => 5, 'Q05' => 1, 'Q06' => 5, 'Q07' => 5, 'Q13' => 5, 'Q19' => 5, 'Q20' => 5, 'Q23' => 5, 'Q30' => 3],
    'family with young children'   => ['Q01' => 3, 'Q02' => 2, 'Q03' => 3, 'Q05' => 3, 'Q06' => 3, 'Q07' => 3, 'Q13' => 2, 'Q14' => 5, 'Q19' => 4, 'Q20' => 2, 'Q23' => 2],
    'all neutral'                  => array_fill_keys(array_map(fn($i) => sprintf('Q%02d', $i), range(1, 30)), 3),
    'low maintenance everything'   => ['Q01' => 1, 'Q02' => 1, 'Q03' => 1, 'Q05' => 5, 'Q06' => 2, 'Q13' => 1, 'Q19' => 1, 'Q21' => 1, 'Q22' => 1, 'Q23' => 1, 'Q28' => 1],
    'maximum everything'           => array_fill_keys(array_map(fn($i) => sprintf('Q%02d', $i), range(1, 30)), 5),
];

$gammas     = [0.7, 1.0, 1.3, 1.6, 2.0];
$shrinkages = [0.0, 1.0, 2.0, 4.0];

printf("%6s %5s | %7s %7s %7s | %6s %6s %6s\n",
    'gamma', 'm', 'top', 'median', 'bottom', 'spread', 'p90-p10', 'top10sp');
echo str_repeat('-', 74), "\n";

foreach ($gammas as $gamma) {
    foreach ($shrinkages as $m) {
        $config = new ScoringConfig(gamma: $gamma, shrinkageM: $m, minTraitsForResults: 1);

        $tops = $medians = $bottoms = $spreads = $bands = $top10 = [];

        foreach ($profiles as $answers) {
            $result = $service->match(new AnswerSet($answers), $config);
            if (count($result->ranked) < 20) {
                continue;
            }

            $scores = array_map(static fn ($x) => $x->score, $result->ranked);
            sort($scores);
            $n = count($scores);

            $tops[]    = $scores[$n - 1];
            $medians[] = $scores[intdiv($n, 2)];
            $bottoms[] = $scores[0];
            $spreads[] = $scores[$n - 1] - $scores[0];
            $bands[]   = $scores[(int) ($n * 0.9)] - $scores[(int) ($n * 0.1)];

            $t = array_slice(array_reverse($scores), 0, 10);
            $top10[] = $t[0] - $t[9];
        }

        if ($tops === []) {
            continue;
        }

        $avg = static fn (array $a): float => array_sum($a) / count($a);

        printf("%6.1f %5.1f | %7.1f %7.1f %7.1f | %6.1f %6.1f %6.1f\n",
            $gamma, $m,
            $avg($tops), $avg($medians), $avg($bottoms),
            $avg($spreads), $avg($bands), $avg($top10));
    }
}

echo "\nspread  = best minus worst of 257\n";
echo "p90-p10 = the band most users actually see\n";
echo "top10sp = spread across the ten results shown on the page\n";
