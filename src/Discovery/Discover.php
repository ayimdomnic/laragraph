<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Discovery;

use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Support\Type;
use Composer\Autoload\ClassLoader;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\Type as GraphQLType;
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
    /** Configured discovery category => base class of the classes it collects (null: named types). */
    private const CATEGORIES = [
        'types'         => null,
        'queries'       => Query::class,
        'mutations'     => Mutation::class,
        'subscriptions' => Subscription::class,
    ];

    /** @var array<string, array<string, string>>|null Loaded manifest; null until first read. */
    private static ?array $manifest = null;

    // -------------------------------------------------------------------------
    // Manifest (php artisan laragraph:cache / optimize)
    // -------------------------------------------------------------------------

    /**
     * Where `laragraph:cache` writes the discovery manifest.
     */
    public static function manifestPath(): string
    {
        return app()->bootstrapPath('cache/laragraph.php');
    }

    public static function isCached(): bool
    {
        return is_file(static::manifestPath());
    }

    /**
     * Scan every configured discovery directory and write the results to the
     * manifest, so production requests never touch the filesystem to find
     * GraphQL classes. Run by `php artisan laragraph:cache` and `optimize`.
     *
     * @return array<string, array<string, string>> The manifest that was written.
     */
    public static function cache(): array
    {
        static::clearCache();

        $manifest = [];

        foreach (self::CATEGORIES as $category => $baseClass) {
            $path = static::configuredPath($category);

            if ($path === '') {
                continue;
            }

            $manifest[$baseClass === null ? "types:{$path}" : "scan:{$baseClass}:{$path}"] = $baseClass === null
                ? static::discoverTypes($path)
                : static::discover($path, $baseClass);
        }

        $file = static::manifestPath();

        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }

        file_put_contents($file, '<?php' . PHP_EOL . PHP_EOL . 'return ' . var_export($manifest, true) . ';' . PHP_EOL);

        return self::$manifest = $manifest;
    }

    /**
     * Delete the manifest; discovery scans directories again on the next request.
     */
    public static function clearCache(): void
    {
        if (static::isCached()) {
            unlink(static::manifestPath());
        }

        self::$manifest = null;
    }

    /**
     * The directory configured for a discovery category, relative to base_path().
     */
    public static function configuredPath(string $category): string
    {
        $path = config("laragraph.discover.{$category}", '');

        return is_array($path) ? (string) ($path['path'] ?? '') : (string) $path;
    }

    /**
     * @param \Closure(): array<string, string> $discover
     * @return array<string, string>
     */
    protected static function remember(string $key, \Closure $discover): array
    {
        if (self::$manifest === null) {
            $file           = static::manifestPath();
            self::$manifest = is_file($file) ? (array) require $file : [];
        }

        return self::$manifest[$key] ?? $discover();
    }

    /**
     * Scan a directory for PHP classes that extend a given base class.
     *
     * @param  string  $path       Path relative to base_path() — e.g. "app/GraphQL/Types"
     * @param  string  $baseClass  FQCN of the base class to match against
     * @return array<string, string>  alias => FQCN
     */
    public static function scan(string $path, string $baseClass): array
    {
        return static::remember("scan:{$baseClass}:{$path}", static fn(): array => static::discover($path, $baseClass));
    }

    /**
     * @return array<string, string> alias => FQCN
     */
    protected static function discover(string $path, string $baseClass): array
    {
        $results = [];

        foreach (static::classesIn($path) as $class) {
            $ref = new \ReflectionClass($class);

            if ($ref->isAbstract() || !$ref->isSubclassOf($baseClass)) {
                continue;
            }

            $results[static::aliasFor($class, $baseClass)] = $class;
        }

        return $results;
    }

    /**
     * Every loadable class (or enum) defined by a PHP file directly inside $path.
     *
     * @param string $path Path relative to base_path()
     * @return list<class-string>
     */
    protected static function classesIn(string $path): array
    {
        if ($path === '') {
            return [];
        }

        $absolutePath = base_path($path);

        if (!is_dir($absolutePath)) {
            return [];
        }

        $namespace = static::namespaceForDirectory($absolutePath);
        $classes   = [];

        foreach (glob("{$absolutePath}/*.php") ?: [] as $file) {
            $class = $namespace . '\\' . pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($class) || enum_exists($class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    // -------------------------------------------------------------------------
    // Typed helpers
    // -------------------------------------------------------------------------

    /**
     * Discover every GraphQL named type in $path: object types, input types,
     * enums, interfaces, unions and scalars built on the Laragraph (or
     * webonyx) base classes, plus native PHP enums, which are exposed as
     * GraphQL enum types.
     *
     * @return array<string, string>
     */
    public static function types(string $path): array
    {
        return static::remember("types:{$path}", static fn(): array => static::discoverTypes($path));
    }

    /**
     * @return array<string, string> alias => FQCN
     */
    protected static function discoverTypes(string $path): array
    {
        $results = [];

        foreach (static::classesIn($path) as $class) {
            if (enum_exists($class)) {
                $results[static::aliasFor($class, Type::class)] = $class;

                continue;
            }

            $ref = new \ReflectionClass($class);

            if ($ref->isAbstract()
                || !$ref->isSubclassOf(GraphQLType::class)
                || !$ref->implementsInterface(NamedType::class)) {
                continue;
            }

            $results[static::aliasFor($class, Type::class)] = $class;
        }

        return $results;
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
            if (is_array($autoloader) && $autoloader[0] instanceof ClassLoader) {
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
