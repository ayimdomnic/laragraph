<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Events;

/**
 * Fired when a GraphQL response contains one or more errors.
 *
 * This is a convenience companion to {@see QueryExecuted} for listeners that
 * only care about error scenarios (e.g. error reporters, alert systems).
 *
 * `$errors` is the serialised `errors` array from the GraphQL response —
 * each element is an array with at minimum a `"message"` key.
 */
final class QueryError
{
    public function __construct(
        /** The raw GraphQL query string. */
        public readonly string $query,
        /** Resolved variable values. */
        public readonly array $variables,
        /** The operation name, or `null` when not specified. */
        public readonly ?string $operationName,
        /** The resolved schema name. */
        public readonly string $schemaName,
        /** The serialised errors array from the GraphQL response. */
        public readonly array $errors,
    ) {}
}
