<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Discovery;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Support\Type;
use Illuminate\Support\Str;

/**
 * Auto-discovers Laragraph classes by scanning configured directories.
 *
 * Enables zero-config usage: create your classes in app/GraphQL/{Types,...}
 * and they are automatically registered without touching config/laragraph.php.
 *
 * The namespace is derived from Composer's PSR-4 map so it works with any
 * app namespace, not just the Laravel default "App\".
 */
class Discover
{
    /**
     * Scan a directory for PHP classes that extend a given base class.
     *
     * @param  string  $path       Path relative to base_path() — e.g. "app/GraphQL/Types"
     * @param  string  $baseClass  FQCN of the base class to match against
     * @return array<string, string>  alias => FQCN
     */
    public static function scan(string $path, string $baseClass): array
    {
        if (empty($path)) {
            return [];
        }

        $absolutePath = base_path($path);

        if (!is_dir($absolutePath)) {
            return [];
        }

        $namespace = static::namespaceForDirectory($absolutePath);
        $results   = [];

        foreach (glob("{$absolutePath}/*.php") ?: [] as $file) {
            $class = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

            if (!class_exists($class)) {
                continue;
            }

            $ref = new \ReflectionClass($class);

            if ($ref->isAbstract() || !$ref->isSubclassOf($baseClass)) {
                continue;
            }

            $results[static::aliasFor($class, $baseClass)] = $class;
        }

        return $results;
    }

    // -------------------------------------------------------------------------
    // Typed helpers
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    public static function types(string $path): array
    {
        return static::scan($path, Type::class);
    }

    /** @return array<string, string> */
    public static function queries(string $path): array
    {
        return static::scan($path, Query::class);
    }

    /** @return array<string, string> */
    public static function mutations(string $path): array
    {
        return static::scan($path, Mutation::class);
    }

    /** @return array<string, string> */
    public static function subscriptions(string $path): array
    {
        return static::scan($path, Subscription::class);
    }

    // -------------------------------------------------------------------------
    // Namespace derivation
    // -------------------------------------------------------------------------

    /**
     * Derive the PSR-4 namespace for an absolute directory path.
     *
     * Uses the already-loaded Composer ClassLoader (universally available in any
     * Composer-managed application) and falls back to `fallbackNamespace()`.
     */
    public static function namespaceForDirectory(string $absolutePath): string
    {
        $absolutePath = rtrim($absolutePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        foreach (spl_autoload_functions() as $autoloader) {
            if (is_array($autoloader) && $autoloader[0] instanceof \Composer\Autoload\ClassLoader) {
                $result = static::matchPsr4Map($absolutePath, $autoloader[0]->getPrefixesPsr4());
                if ($result !== '') {
                    return $result;
                }
            }
        }

        return static::fallbackNamespace($absolutePath);
    }

    /**
     * Find the longest PSR-4 prefix matching $absolutePath and return the full
     * qualified namespace for that path.
     *
     * @param  array<string, array<string>>  $psr4
     */
    protected static function matchPsr4Map(string $absolutePath, array $psr4): string
    {
        $longest = '';
        $result  = '';

        foreach ($psr4 as $namespace => $dirs) {
            foreach ((array) $dirs as $dir) {
                // Resolve symlinks / ".." segments so the prefix comparison is reliable.
                $resolved = realpath((string) $dir);
                $dir      = $resolved !== false
                    ? rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
                    : rtrim((string) $dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

                if (str_starts_with($absolutePath, $dir) && strlen($dir) > strlen($longest)) {
                    $longest   = $dir;
                    $remainder = rtrim(
                        str_replace('/', '\\', str_replace($dir, '', $absolutePath)),
                        '\\',
                    );
                    $result = rtrim($namespace, '\\') . ($remainder ? '\\' . $remainder : '');
                }
            }
        }

        return $result;
    }

    protected static function fallbackNamespace(string $absolutePath): string
    {
        $appPath  = rtrim(app_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $appNs    = rtrim(app()->getNamespace(), '\\');

        if (str_starts_with($absolutePath, $appPath)) {
            $relative = str_replace($appPath, '', $absolutePath);
            $relative = rtrim(str_replace(DIRECTORY_SEPARATOR, '\\', $relative), '\\');
            return $appNs . ($relative ? '\\' . $relative : '');
        }

        return $appNs;
    }

    // -------------------------------------------------------------------------
    // Alias derivation
    // -------------------------------------------------------------------------

    /**
     * Derive the GraphQL field / type alias from a class name.
     *
     * - Types:         strip the "Type" suffix, keep PascalCase   → "UserType" → "User"
     * - Queries:       strip "Query", convert to camelCase         → "UsersQuery" → "users"
     * - Mutations:     strip "Mutation", camelCase                 → "CreateUserMutation" → "createUser"
     * - Subscriptions: strip "Subscription", camelCase             → "UserCreatedSubscription" → "userCreated"
     */
    public static function aliasFor(string $class, string $baseClass): string
    {
        $basename = class_basename($class);

        if ($baseClass === Type::class || is_a($baseClass, Type::class, true)) {
            // Type alias: strip "Type" suffix, keep PascalCase
            return preg_replace('/Type$/', '', $basename) ?: $basename;
        }

        // Field alias: strip suffix, camelCase
        $name = preg_replace('/(Query|Mutation|Subscription)$/', '', $basename) ?: $basename;
        return Str::camel($name);
    }
}
