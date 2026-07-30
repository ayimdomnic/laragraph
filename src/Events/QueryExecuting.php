<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Events;

/**
 * Fired immediately before a GraphQL query is executed.
 *
 * Requests served from the response cache do **not** fire this event.
 * Use this event for audit logging, per-query rate-limiting, or request
 * correlation.
 */
final class QueryExecuting
{
    public function __construct(
        /** The raw GraphQL query string. */
        public readonly string $query,
        /** @var array<string, mixed> Resolved variable values (empty array when none supplied). */
        public readonly array $variables,
        /** The operation name, or `null` when not specified. */
        public readonly ?string $operationName,
        /** The resolved schema name (never null -- defaults to the configured default). */
        public readonly string $schemaName,
    ) {}
}
