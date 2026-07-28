<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PersistedQuery;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * A persisted-query store backed by a Laravel cache driver.
 *
 * Supports runtime registration of new queries (e.g. via the Automatic
 * Persisted Queries / APQ protocol) because entries are written to the
 * configured cache store and survive between requests until they expire.
 *
 * ## Configuration
 *
 * ```php
 * 'persisted_queries' => [
 *     'enabled' => true,
 *     'store'   => 'cache',      // use this implementation
 *     'ttl'     => 3600,         // seconds; null = forever
 * ],
 * ```
 *
 * The cache driver used is taken from `laragraph.cache.response.store` by
 * default, but you may inject any {@see CacheRepository} directly.
 */
final class CachePersistedQueryStore implements PersistedQueryStoreInterface
{
    private const PREFIX = 'laragraph_pq:';

    public function __construct(
        private readonly CacheRepository $cache,
        private readonly ?int $ttl = 3600,
    ) {}

    public function get(string $id): ?string
    {
        /** @var string|null */
        return $this->cache->get($this->key($id));
    }

    public function set(string $id, string $query, ?int $ttl = null): void
    {
        $this->cache->put($this->key($id), $query, $ttl ?? $this->ttl);
    }

    public function has(string $id): bool
    {
        return $this->cache->has($this->key($id));
    }

    public function forget(string $id): void
    {
        $this->cache->forget($this->key($id));
    }

    private function key(string $id): string
    {
        return self::PREFIX . $id;
    }
}
