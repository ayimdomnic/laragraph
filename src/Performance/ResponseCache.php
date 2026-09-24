<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Performance;

use Ayimdomnic\Laragraph\Support\Operation;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Per-execution GraphQL response cache.
 *
 * Caches the complete serialised result array for read-only (query) operations.
 * Mutations and subscriptions are NEVER cached — the operation type is read
 * from the parsed document, so comments, fragments and `operationName`
 * selection cannot smuggle a mutation into the cache.
 *
 * Enable via `config/laragraph.php`:
 *
 * ```php
 * 'cache' => [
 *     'response' => [
 *         'enabled' => true,
 *         'store'   => 'redis',   // any Laravel cache store
 *         'ttl'     => 60,        // seconds
 *         'scope'   => 'user',    // 'user' (default) or 'global'
 *     ],
 * ],
 * ```
 *
 * ## Cache key strategy
 * Key = `laragraph:response:{sha256(generation + schema + scope + operationName + query + sorted-variables)}`
 *
 * - **schema** — the same document run against different schemas never collides.
 * - **scope** — with `scope => 'user'` every authenticated user (and guests as
 *   a group) gets their own entries, so one user's data is never served to
 *   another. Use `'global'` only for APIs whose responses are identical for
 *   every caller.
 * - **generation** — bumped by {@see flush()} to invalidate everything at once
 *   on any cache store, without needing tag support.
 */
final class ResponseCache
{
    private const KEY_PREFIX = 'laragraph:response:';

    private const GENERATION_KEY = 'laragraph:response-generation';

    /**
     * Build the cache key for a query execution.
     *
     * @param string       $query         Raw GraphQL query string.
     * @param array<mixed> $variables     Resolved variable map.
     * @param string|null  $operationName Operation name, if provided.
     * @param string|null  $schemaName    Schema the operation runs against.
     * @param string|null  $scope         Caller scope, see {@see scope()}.
     */
    public static function key(
        string $query,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
        ?string $scope = null,
    ): string {
        ksort($variables);

        return self::KEY_PREFIX . hash('sha256', implode('|', [
            self::generation(),
            $schemaName ?? '',
            $scope ?? '',
            $operationName ?? '',
            trim($query),
            json_encode($variables, JSON_THROW_ON_ERROR),
        ]));
    }

    /**
     * The caller scope used to partition cache entries.
     *
     * `'user:{id}'` / `'guest'` for the (default) `user` scope, resolved on
     * `laragraph.auth.default_guard`; `'global'` when the scope is `global`.
     */
    public static function scope(): string
    {
        if (config('laragraph.cache.response.scope', 'user') === 'global') {
            return 'global';
        }

        $id = Auth::guard(config('laragraph.auth.default_guard'))->id();

        return $id === null ? 'guest' : 'user:' . $id;
    }

    /**
     * Check whether caching is enabled for queries.
     */
    public static function enabled(): bool
    {
        return (bool) config('laragraph.cache.response.enabled', false);
    }

    /**
     * Return the configured cache store name.
     */
    public static function store(): string
    {
        return (string) config('laragraph.cache.response.store', 'default');
    }

    /**
     * Return the configured TTL in seconds.
     */
    public static function ttl(): int
    {
        return (int) config('laragraph.cache.response.ttl', 60);
    }

    /**
     * Attempt to retrieve a cached response.
     *
     * @param string $key The cache key from {@see key()}.
     * @return array<mixed>|null Cached result or null on miss.
     */
    public static function get(string $key): ?array
    {
        $value = self::driver()->get($key);

        return is_array($value) ? $value : null;
    }

    /**
     * Store a response in the cache.
     *
     * @param string       $key   The cache key from {@see key()}.
     * @param array<mixed> $value The serialised result array to cache.
     */
    public static function put(string $key, array $value): void
    {
        self::driver()->put($key, $value, self::ttl());
    }

    /**
     * Remove a specific entry from the response cache.
     */
    public static function forget(string $key): void
    {
        self::driver()->forget($key);
    }

    /**
     * Invalidate every cached response.
     *
     * Entries are not deleted one by one: the key generation is advanced, so
     * all previously written keys become unreachable and expire via their TTL.
     * Works on every cache store, with or without tag support.
     */
    public static function flush(): void
    {
        self::driver()->forever(self::GENERATION_KEY, self::generation() + 1);
    }

    /**
     * Determine whether this operation should be cached: only `query`
     * operations are, as determined from the parsed document.
     *
     * @param string      $query         Raw GraphQL query string.
     * @param string|null $operationName The operation to execute, when the document has several.
     */
    public static function isCacheable(string $query, ?string $operationName = null): bool
    {
        return Operation::isQuery($query, $operationName);
    }

    private static function generation(): int
    {
        return (int) self::driver()->get(self::GENERATION_KEY, 0);
    }

    /**
     * Resolve the cache repository for the configured store.
     *
     * When the store name is 'default' we call Cache::store() with no argument
     * so that Laravel uses whatever driver is set in cache.default — there is
     * no actual store *named* "default" in the cache config.
     */
    private static function driver(): Repository
    {
        $name = self::store();

        return Cache::store($name === 'default' ? null : $name);
    }
}
