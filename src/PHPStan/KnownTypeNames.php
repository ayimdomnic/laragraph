<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PHPStan;

use Ayimdomnic\Laragraph\Laragraph;

/**
 * A best-effort, purely static/textual set of GraphQL type names a consuming
 * project has registered — no container bootstrap, no instantiating any of
 * the project's own classes. Three sources, all additive (a name matching
 * any one is accepted):
 *
 *  - `config('laragraph.types')`, and every per-schema `types` array under
 *    `config('laragraph.schemas')` — read by literally `include`-ing
 *    config/laragraph.php. Safe: it's a plain array literal whose only side
 *    effects are `env()` calls and class-string references, both of which
 *    resolve fine without a booted container.
 *  - Class basenames under the configured discovery directory
 *    (`laragraph.discover.types`, default `app/GraphQL/Types`), with a
 *    trailing `Type` suffix stripped (`UserType` → `User`) — covering
 *    `InterfaceType`/`UnionType`/`EnumType` too, since those still end in
 *    `Type` by this codebase's own convention — plus the raw basename
 *    itself, in case a type class doesn't follow that convention.
 *  - `NAME = '...'` / `NAME = "..."` constant assignments found anywhere in
 *    those same files (covers a class whose name differs from its basename).
 *
 * A type registered only via the runtime `->name` property (no `NAME`
 * constant, no basename match — {@see Laragraph::resolveTypeName()})
 * is invisible to this heuristic and will be flagged as a false positive.
 * The fix is adding a `NAME` constant to that type.
 */
final class KnownTypeNames
{
    /** @var array<string, array<string, true>> Keyed by project root. */
    private static array $cache = [];

    /**
     * @return array<string, true>
     */
    public static function resolve(string $projectRoot): array
    {
        if (isset(self::$cache[$projectRoot])) {
            return self::$cache[$projectRoot];
        }

        $names  = [];
        $config = self::loadConfig($projectRoot);

        foreach (self::namesFromConfig($config) as $name) {
            $names[$name] = true;
        }

        foreach (self::namesFromDiscoveryDirectory($projectRoot, $config) as $name) {
            $names[$name] = true;
        }

        return self::$cache[$projectRoot] = $names;
    }

    /**
     * Test-only: forget cached results so a test run doesn't leak into the
     * next one.
     */
    public static function reset(): void
    {
        self::$cache = [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function loadConfig(string $projectRoot): ?array
    {
        $configPath = $projectRoot . '/config/laragraph.php';

        if (!is_file($configPath)) {
            return null;
        }

        try {
            $config = (static fn(): mixed => include $configPath)();
        } catch (\Throwable) {
            return null;
        }

        return is_array($config) ? $config : null;
    }

    /**
     * @param array<string, mixed>|null $config
     * @return list<string>
     */
    private static function namesFromConfig(?array $config): array
    {
        if ($config === null) {
            return [];
        }

        $names = array_keys((array) ($config['types'] ?? []));

        foreach ((array) ($config['schemas'] ?? []) as $schema) {
            if (is_array($schema)) {
                array_push($names, ...array_keys((array) ($schema['types'] ?? [])));
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed>|null $config
     * @return list<string>
     */
    private static function namesFromDiscoveryDirectory(string $projectRoot, ?array $config): array
    {
        $directory = self::discoveryDirectory($projectRoot, $config);

        if ($directory === null || !is_dir($directory)) {
            return [];
        }

        $names    = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $basename = $file->getBasename('.php');
            $names[]  = $basename;

            $stripped = preg_replace('/Type$/', '', $basename);

            if (is_string($stripped)) {
                $names[] = $stripped;
            }

            $source = file_get_contents($file->getPathname());

            if ($source === false) {
                continue;
            }

            if (preg_match_all('/\bNAME\s*=\s*[\'"]([^\'"]+)[\'"]/', $source, $matches) > 0) {
                array_push($names, ...$matches[1]);
            }
        }

        return $names;
    }

    /**
     * @param array<string, mixed>|null $config
     */
    private static function discoveryDirectory(string $projectRoot, ?array $config): ?string
    {
        $default    = $projectRoot . '/app/GraphQL/Types';
        $configured = is_array($config['discover'] ?? null) ? ($config['discover']['types'] ?? $default) : $default;

        if (!is_string($configured) || $configured === '') {
            return null;
        }

        return str_starts_with($configured, '/') ? $configured : $projectRoot . '/' . $configured;
    }
}
