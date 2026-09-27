<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\PersistedQuery;

use Ayimdomnic\Laragraph\Contracts\PersistedQueryResolverInterface;
use Ayimdomnic\Laragraph\Exceptions\RequestException;

/**
 * Resolve the query text for a request when persisted queries are enabled.
 *
 * - `queryId` or the APQ `extensions.persistedQuery.sha256Hash` alone
 *   looks the query up in the store.
 * - APQ registration: a full query *plus* its SHA-256 hash stores it for
 *   subsequent hash-only requests (unless `persisted_queries.only` is on).
 * - `persisted_queries.only` turns the store into an allow-list: query
 *   text is executed only when it is already stored under its hash.
 */
final readonly class PersistedQueryResolver implements PersistedQueryResolverInterface
{
    public function __construct(private PersistedQueryStoreInterface $store) {}

    /**
     * @param array<string, mixed> $data
     *
     * @throws RequestException
     */
    public function resolve(string $query, array $data): string
    {
        $only    = (bool) config('laragraph.persisted_queries.only', false);
        $queryId = $data['queryId'] ?? $data['extensions']['persistedQuery']['sha256Hash'] ?? null;
        $queryId = is_scalar($queryId) ? (string) $queryId : null;

        if ($query === '') {
            if ($queryId === null) {
                return $query;
            }

            return $this->store->get($queryId) ?? throw RequestException::persistedQueryNotFound();
        }

        $hash = hash('sha256', $query);

        if ($only) {
            return $this->store->has($hash) ? $query : throw RequestException::persistedQueryRequired();
        }

        if ($queryId !== null && isset($data['extensions']['persistedQuery'])) {
            if (!hash_equals($hash, strtolower($queryId))) {
                throw RequestException::persistedQueryHashMismatch();
            }

            if (config('laragraph.persisted_queries.apq', true)) {
                $this->store->set($hash, $query);
            }
        }

        return $query;
    }
}
