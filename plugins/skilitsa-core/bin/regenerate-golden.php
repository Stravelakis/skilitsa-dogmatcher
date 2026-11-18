<?php
declare(strict_types=1);

/**
 * Fills in the `expected` block of every golden case from the engine's current
 * behaviour.
 *
 * This is NOT a way to make failing tests pass. Run it only when you have
 * deliberately changed the engine, then read the diff line by line and satisfy
 * yourself that every changed number is a change you meant to make.
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../tests/Golden/GoldenMatchTest.php';

use Skilitsa\Core\Tests\Golden\GoldenMatchTest;

$path  = GoldenMatchTest::FIXTURE_DIR . '/cases.json';
$cases = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

foreach ($cases as $i => $case) {
    $cases[$i]['expected'] = GoldenMatchTest::actualFor($case);

    $top = $cases[$i]['expected']['ranking'][0]['breed'] ?? '(none)';
    printf(
        "%-38s top=%-30s ranked=%d excluded=%d%s",
        $case['name'],
        $top,
        count($cases[$i]['expected']['ranking']),
        count($cases[$i]['expected']['excluded']),
        PHP_EOL
    );
}

file_put_contents(
    $path,
    json_encode($cases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo PHP_EOL, 'Written ', $path, PHP_EOL;
echo 'Now run: git diff -- ', $path, PHP_EOL;
echo 'Read every changed number before committing.', PHP_EOL;
