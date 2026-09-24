<?php

declare(strict_types=1);

namespace App\Listeners;

use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

/**
 * LIFECYCLE EVENTS — Laragraph fires QueryExecuting, QueryExecuted,
 * QueryError and SchemaBuilt. This listener logs slow operations; the
 * `cached` flag tells response-cache hits apart.
 */
class LogSlowGraphQLOperations
{
    public function handle(QueryExecuted $event): void
    {
        if ($event->executionMs < (float) config('app.graphql_slow_ms', 500)) {
            return;
        }

        Log::warning('Slow GraphQL operation', [
            'operation' => $event->operationName ?? '(anonymous)',
            'schema' => $event->schemaName,
            'ms' => $event->executionMs,
            'cached' => $event->cached,
        ]);
    }
}
