<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Events;

use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;

/**
 * Fired after a GraphQL query has been fully executed and the response built.
 *
 * `$result` is the complete response array (including any `extensions` data
 * added by registered {@see GraphQLExtensionInterface}s).
 *
 * Use this event for performance monitoring, response logging, or tracing.
 */
final readonly class QueryExecuted
{
    public function __construct(
        /** The raw GraphQL query string. */
        public string $query,
        /** @var array<string, mixed> Resolved variable values. */
        public array $variables,
        /** The operation name, or `null` when not specified. */
        public ?string $operationName,
        /** The resolved schema name. */
        public string $schemaName,
        /** @var array<string, mixed> The full serialised response array sent to the client. */
        public array $result,
        /** Total `execute()` wall-clock time in milliseconds. */
        public float $executionMs,
        /** `true` when `$result` contains at least one error. */
        public bool $hasErrors,
        /** `true` when `$result` was served from the response cache. */
        public bool $cached = false,
    ) {}
}
