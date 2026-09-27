<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Http\BatchProcessor;

/**
 * Processes a batch of GraphQL operations against a single schema.
 *
 * @see BatchProcessor the built-in implementation.
 */
interface BatchProcessorInterface
{
    /**
     * @param  array<int, array{query?: string, variables?: mixed, operationName?: string|null}> $operations
     * @param  (\Closure(array<string, mixed>): array<string, mixed>)|null $executor
     * @return array<int, array<string, mixed>>
     *
     * @throws BatchingDisabledException
     * @throws BatchLimitExceededException
     */
    public function process(array $operations, mixed $context = null, string $schemaName = 'default', ?\Closure $executor = null): array;
}
