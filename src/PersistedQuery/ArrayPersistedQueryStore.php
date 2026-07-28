<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PersistedQuery;

/**
 * A persisted-query store backed by a plain PHP array.
 *
 * Useful for:
 *   - Static query lists defined in `config/laragraph.php` under
 *     `persisted_queries.map`
 *   - Unit tests (no I/O needed)
 *
 * The store is ephemeral — it lives only for the duration of the PHP process.
 * `ttl` is accepted by {@see set()} for interface compatibility but ignored.
 *
 * ## Configuration
 *
 * ```php
 * 'persisted_queries' => [
 *     'enabled' => true,
 *     'store'   => 'array',
 *     'map'     => [
 *         'get-all-users'   => '{ users { id name } }',
 *         'get-user-by-id'  => 'query GetUser($id: ID!) { user(id: $id) { id name } }',
 *     ],
 * ],
 * ```
 */
final class ArrayPersistedQueryStore implements PersistedQueryStoreInterface
{
    /** @param  array<string, string>  $map  Initial ID → query-string map. */
    public function __construct(private array $map = []) {}

    public function get(string $id): ?string
    {
        return $this->map[$id] ?? null;
    }

    public function set(string $id, string $query, ?int $ttl = null): void
    {
        $this->map[$id] = $query;
    }

    public function has(string $id): bool
    {
        return isset($this->map[$id]);
    }

    public function forget(string $id): void
    {
        unset($this->map[$id]);
    }
}
