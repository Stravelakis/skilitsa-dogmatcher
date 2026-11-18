<?php
declare(strict_types=1);

namespace Skilitsa\Matcher\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Skilitsa\Core\Engine\MatchEngine;

final class HarnessTest extends TestCase
{
    public function test_harness_runs(): void
    {
        self::assertTrue(true);
    }

    public function test_core_classes_are_autoloadable(): void
    {
        // The matcher is useless without core; failing here is clearer than
        // failing later inside a controller.
        self::assertTrue(class_exists(MatchEngine::class));
    }
}
