<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Execution\QueryExecutor;
use GraphQL\Executor\ExecutionResult;

/**
 * Executes a single GraphQL query string and returns either the serialised
 * response array ({@see execute()}) or the raw ExecutionResult
 * ({@see executeQuery()}) for callers that need to post-process it
 * themselves (e.g. subscription broadcasting).
 *
 * @see QueryExecutor the built-in implementation.
 */
interface QueryExecutorInterface
{
    /**
     * Execute a GraphQL query string and return the serialised result array.
     *
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function execute(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
        mixed $rootValue = null,
    ): array;

    /**
     * Execute a GraphQL query and return the raw ExecutionResult.
     *
     * @param array<string, mixed> $variables
     */
    public function executeQuery(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
        mixed $rootValue = null,
    ): ExecutionResult;
}
