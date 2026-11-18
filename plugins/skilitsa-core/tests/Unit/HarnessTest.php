<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HarnessTest extends TestCase
{
    public function test_harness_runs(): void
    {
        self::assertTrue(true);
    }

    public function test_php_version_is_supported(): void
    {
        self::assertGreaterThanOrEqual(80100, \PHP_VERSION_ID);
        self::assertLessThan(80400, \PHP_VERSION_ID);
    }
}
