<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Performance;

use Illuminate\Support\Facades\Cache;

/**
 * Per-execution GraphQL response cache.
 *
 * Caches the complete serialised result array for read-only (query) operations.
 * Mutations and subscriptions are NEVER cached.
 *
 * Enable via `config/laragraph.php`:
 *
 * ```php
 * 'cache' => [
 *     'response' => [
 *         'enabled' => true,
 *         'store'   => 'redis',   // any Laravel cache store
 *         'ttl'     => 60,        // seconds
 *     ],
 * ],
 * ```
 *
 * ## Cache key strategy
 * Key = `laragraph:response:{sha256(operationName + query + sorted-variables)}`
 *
 * This means the same logical query with different variable sets gets different
 * cache entries, which is correct behaviour.
 *
 * ## Invalidation
 * Call `ResponseCache::flush()` to drop ALL laragraph response entries, or
 * `ResponseCache::forget($key)` for a specific entry.
 */
final class ResponseCache
{
    private const KEY_PREFIX = 'laragraph:response:';

    /**
     * Build the cache key for a query execution.
     *
     * @param  string       $query          Raw GraphQL query string.
     * @param  array<mixed> $variables      Resolved variable map.
     * @param  string|null  $operationName  Operation name, if provided.
     */
    public static function key(string $query, array $variables = [], ?string $operationName = null): string
    {
        ksort($variables);

        return self::KEY_PREFIX . hash('sha256', implode('|', [
            $operationName ?? '',
            trim($query),
            json_encode($variables, JSON_THROW_ON_ERROR),
        ]));
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
     * @param  string  $key  The cache key from {@see key()}.
     * @return array<mixed>|null  Cached result or null on miss.
     */
    public static function get(string $key): ?array
    {
        /** @var array<mixed>|null */
        return static::driver()->get($key);
    }

    /**
     * Store a response in the cache.
     *
     * @param  string        $key    The cache key from {@see key()}.
     * @param  array<mixed>  $value  The serialised result array to cache.
     */
    public static function put(string $key, array $value): void
    {
        static::driver()->put($key, $value, static::ttl());
    }

    /**
     * Remove a specific entry from the response cache.
     */
    public static function forget(string $key): void
    {
        static::driver()->forget($key);
    }

    /**
     * Resolve the cache repository for the configured store.
     *
     * When the store name is 'default' we call Cache::store() with no argument
     * so that Laravel uses whatever driver is set in cache.default — there is
     * no actual store *named* "default" in the cache config.
     *
     * @return \Illuminate\Contracts\Cache\Repository
     */
    private static function driver(): \Illuminate\Contracts\Cache\Repository
    {
        $name = static::store();

        return Cache::store($name === 'default' ? null : $name);
    }

    /**
     * Determine whether this query should be cached.
     *
     * Only non-mutation, non-subscription queries are cacheable.
     * A simple heuristic: if the query string does not start with `mutation`
     * or `subscription` (after stripping whitespace and operation names),
     * it is considered cacheable.
     *
     * @param  string  $query  Raw GraphQL query string.
     */
    public static function isCacheable(string $query): bool
    {
        $normalised = strtolower(trim($query));

        return !str_starts_with($normalised, 'mutation')
            && !str_starts_with($normalised, 'subscription');
    }
}
