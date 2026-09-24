<?php

declare(strict_types=1);

namespace App\Listeners;

use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Performance\ResponseCache;
use Ayimdomnic\Laragraph\Support\Operation;

/**
 * RESPONSE CACHE INVALIDATION — the response cache stores query results for
 * `cache.response.ttl` seconds. Flushing after every successful mutation
 * means nobody reads data that a mutation has just changed.
 *
 * ResponseCache::flush() advances a key generation instead of deleting
 * entries, so it is cheap and works on every cache store.
 */
class FlushResponseCacheAfterMutations
{
    public function handle(QueryExecuted $event): void
    {
        if (! $event->hasErrors && Operation::isMutation($event->query, $event->operationName)) {
            ResponseCache::flush();
        }
    }
}
