<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PersistedQuery;

/**
 * Contract for a persisted-query store.
 *
 * A persisted-query store maps opaque IDs (typically SHA-256 hashes) to
 * full GraphQL query strings. Clients submit only the ID over the wire;
 * the server resolves the full text before execution.
 *
 * ## Implementations provided
 *
 * - {@see ArrayPersistedQueryStore}  — static in-memory map (great for tests / config-based lists)
 * - {@see CachePersistedQueryStore} — durable store backed by any Laravel cache driver
 */
interface PersistedQueryStoreInterface
{
    /**
     * Retrieve a query string by its ID.
     *
     * @return string|null  The full query text, or null if not found.
     */
    public function get(string $id): ?string;

    /**
     * Register a query string under the given ID.
     *
     * @param  int|null  $ttl  Optional time-to-live in seconds (store-dependent).
     */
    public function set(string $id, string $query, ?int $ttl = null): void;

    /**
     * Check whether a query has been registered for the given ID.
     */
    public function has(string $id): bool;

    /**
     * Remove a query from the store.
     */
    public function forget(string $id): void;
}
