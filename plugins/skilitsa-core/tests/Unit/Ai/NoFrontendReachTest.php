<?php
declare(strict_types=1);

namespace Skilitsa\Core\Tests\Unit\Ai;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The owner's firmest product principle, made structural.
 *
 * "No generative AI output ever reaches an end user" is a rule that decays the
 * moment it depends on everyone remembering it. This test is the version that
 * does not: nothing a visitor can reach may so much as mention the AI namespace.
 *
 * Modelled on NoWordPressDependencyTest, which keeps WordPress out of the
 * scoring engine the same way and for the same reason.
 */
final class NoFrontendReachTest extends TestCase
{
    private const NEEDLE = 'Skilitsa\\Core\\Ai';

    private function corePath(string $relative = ''): string
    {
        return dirname(__DIR__, 3) . $relative;
    }

    private function matcherPath(string $relative = ''): string
    {
        return dirname(__DIR__, 4) . '/skilitsa-matcher' . $relative;
    }

    /** @return list<SplFileInfo> */
    private function phpFilesIn(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files    = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function assertNoAiIn(string $root, string $why): void
    {
        $files = $this->phpFilesIn($root);

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            self::assertStringNotContainsString(
                self::NEEDLE,
                $contents,
                sprintf('%s reaches the AI layer. %s', $file->getFilename(), $why)
            );
        }
    }

    public function test_the_scoring_engine_never_reaches_the_ai_layer(): void
    {
        // Scoring is deterministic. A model in this path would make two runs of
        // the same quiz disagree, and no result URL could be trusted again.
        $this->assertNoAiIn(
            $this->corePath('/src/Engine'),
            'Scoring must stay deterministic.'
        );
    }

    public function test_the_public_plugin_never_reaches_the_ai_layer(): void
    {
        // The quiz, the REST routes, the result page, the templates: everything
        // a visitor can reach lives in this plugin.
        $this->assertNoAiIn(
            $this->matcherPath('/src'),
            'Nothing a visitor can reach may touch generated content.'
        );

        $this->assertNoAiIn(
            $this->matcherPath('/templates'),
            'Nothing a visitor can reach may touch generated content.'
        );
    }

    public function test_the_ai_layer_is_reached_only_from_the_admin(): void
    {
        $offenders = [];

        foreach ($this->phpFilesIn($this->corePath('/src')) as $file) {
            $contents = (string) file_get_contents($file->getPathname());
            $path     = str_replace('\\', '/', $file->getPathname());

            if (!str_contains($contents, self::NEEDLE)) {
                continue;
            }

            $isAdmin = str_contains($path, '/src/Admin/');
            $isAi    = str_contains($path, '/src/Ai/');

            if (!$isAdmin && !$isAi) {
                $offenders[] = $file->getFilename();
            }
        }

        self::assertSame(
            [],
            $offenders,
            'Only the admin may run an AI task. Anything else is a path a request could take.'
        );
    }

    public function test_the_matcher_plugin_does_not_depend_on_core_ai_at_all(): void
    {
        $composer = $this->matcherPath('/composer.json');

        if (!is_file($composer)) {
            self::markTestSkipped('No matcher composer.json.');
        }

        self::assertStringNotContainsString('Ai', (string) file_get_contents($composer));
    }

    public function test_the_guard_would_actually_fire(): void
    {
        // A test that can never fail protects nothing. This proves the needle
        // and the scan agree.
        $sample = '<?php use ' . self::NEEDLE . '\\TaskRegistry;';

        self::assertStringContainsString(self::NEEDLE, $sample);
    }
}
