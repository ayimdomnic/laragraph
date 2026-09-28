<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Packagist installs from `git archive`, which honours `.gitattributes`.
 * Anything not export-ignored there ships to every consumer's vendor/ directory.
 */
class PackageExportTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function devOnlyPaths(): array
    {
        return [
            'example app'       => ['example/composer.json'],
            'tests'             => ['tests/TestCase.php'],
            'benchmarks'        => ['benchmarks/BenchCase.php'],
            'CI workflows'      => ['.github/workflows/tests.yml'],
            'vercel config'     => ['vercel.json'],
            'phpbench config'   => ['phpbench.json'],
            'phpstan config'    => ['phpstan.neon.dist'],
        ];
    }

    #[DataProvider('devOnlyPaths')]
    public function test_dev_only_paths_are_excluded_from_the_dist_archive(string $path): void
    {
        $root = dirname(__DIR__, 2);

        if (!is_dir($root . '/.git') || !is_file($root . '/' . $path)) {
            $this->markTestSkipped('Not running from a git checkout of the package.');
        }

        // Attributes on a directory apply to the directory entry, not the files inside it.
        $entry  = explode('/', $path)[0];
        $output = shell_exec(sprintf('git -C %s check-attr export-ignore -- %s 2>&1', escapeshellarg($root), escapeshellarg($entry)));

        $this->assertStringContainsString('export-ignore: set', (string) $output, "{$entry} would ship to Packagist consumers; add it to .gitattributes.");
    }

    public function test_the_phpstan_extension_still_ships(): void
    {
        $root = dirname(__DIR__, 2);

        if (!is_dir($root . '/.git')) {
            $this->markTestSkipped('Not running from a git checkout of the package.');
        }

        $output = shell_exec(sprintf('git -C %s check-attr export-ignore -- phpstan-extension.neon 2>&1', escapeshellarg($root)));

        $this->assertStringNotContainsString('export-ignore: set', (string) $output, 'Consumers reference this file from vendor/.');
    }
}
