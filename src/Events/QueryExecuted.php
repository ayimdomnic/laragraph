<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Events;

/**
 * Fired after a GraphQL query has been fully executed and the response built.
 *
 * `$result` is the complete response array (including any `extensions` data
 * added by registered {@see \Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface}s).
 *
 * Use this event for performance monitoring, response logging, or tracing.
 */
final class QueryExecuted
{
    public function __construct(
        /** The raw GraphQL query string. */
        public readonly string $query,
        /** @var array<string, mixed> Resolved variable values. */
        public readonly array $variables,
        /** The operation name, or `null` when not specified. */
        public readonly ?string $operationName,
        /** The resolved schema name. */
        public readonly string $schemaName,
        /** @var array<string, mixed> The full serialised response array sent to the client. */
        public readonly array $result,
        /** Total `execute()` wall-clock time in milliseconds. */
        public readonly float $executionMs,
        /** `true` when `$result` contains at least one error. */
        public readonly bool $hasErrors,
    ) {}
}
