<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Engine;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The engine's testability rests entirely on it never touching WordPress. That
 * property erodes the first time someone reaches for get_option() "just here".
 * This makes the erosion loud.
 */
final class NoWordPressDependencyTest extends TestCase
{
    /**
     * Patterns that would drag WordPress into the engine. Deliberately broad:
     * a false positive here costs one rename, a false negative costs the
     * engine's testability.
     */
    private const FORBIDDEN = [
        '/\bwp_[a-z_]+\s*\(/',
        '/\bget_option\s*\(/',
        '/\bupdate_option\s*\(/',
        '/\badd_(action|filter)\s*\(/',
        '/\bapply_filters\s*\(/',
        '/\bdo_action\s*\(/',
        '/\bglobal\s+\$wpdb\b/',
        '/\bABSPATH\b/',
        '/\besc_(html|attr|url)\s*\(/',
    ];

    /** @return list<string> */
    private function engineFiles(): array
    {
        $root = dirname(__DIR__, 3) . '/src/Engine';
        self::assertDirectoryExists($root, 'Engine source directory is missing.');

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    public function test_engine_has_source_files_to_check(): void
    {
        self::assertNotEmpty($this->engineFiles(), 'No engine files found - the guard would pass vacuously.');
    }

    public function test_no_engine_file_references_wordpress(): void
    {
        $offences = [];

        foreach ($this->engineFiles() as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);

            foreach (self::FORBIDDEN as $pattern) {
                if (preg_match($pattern, $source, $m) === 1) {
                    $offences[] = sprintf('%s contains "%s"', basename($path), trim($m[0]));
                }
            }
        }

        self::assertSame([], $offences, implode("\n", $offences));
    }

    public function test_every_engine_file_declares_strict_types(): void
    {
        $offences = [];

        foreach ($this->engineFiles() as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);

            if (!str_contains($source, 'declare(strict_types=1);')) {
                $offences[] = basename($path) . ' is missing declare(strict_types=1);';
            }
        }

        self::assertSame([], $offences, implode("\n", $offences));
    }
}
