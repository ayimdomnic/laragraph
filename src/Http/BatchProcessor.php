<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http;

use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Laragraph;

/**
 * Processes a batch of GraphQL operations against a single schema.
 *
 * Enforces the `laragraph.batching.enabled` toggle and the
 * `laragraph.batching.max_operations` ceiling before dispatching
 * each operation to {@see Laragraph::execute()}.
 */
class BatchProcessor
{
    public function __construct(protected readonly Laragraph $laragraph) {}

    /**
     * Execute a batch of GraphQL operations and return an indexed array of results.
     *
     * @param  array<int, array{query?: string, variables?: mixed, operationName?: string|null}> $operations
     * @param  mixed  $context    Passed through to every individual execute() call.
     * @param  string $schemaName Schema to run all operations against.
     * @param  (\Closure(array<string, mixed>): array<string, mixed>)|null $executor
     *         Runs one operation; defaults to Laragraph::execute(). The HTTP
     *         controller passes its own so batched operations get the same
     *         persisted-query and subscription handling as single requests.
     * @return array<int, array<string, mixed>> One result per input operation, preserving order.
     *
     * @throws BatchingDisabledException   When `laragraph.batching.enabled` is false.
     * @throws BatchLimitExceededException When the operation count exceeds the configured maximum.
     */
    public function process(array $operations, mixed $context = null, string $schemaName = 'default', ?\Closure $executor = null): array
    {
        if (!config('laragraph.batching.enabled', false)) {
            throw new BatchingDisabledException();
        }

        $max = (int) (config('laragraph.batching.max_operations') ?? 10);

        // 0 (or a negative value) means "no limit", as documented in the config.
        if ($max > 0 && count($operations) > $max) {
            throw new BatchLimitExceededException($max);
        }

        $executor ??= fn(array $op): array => $this->laragraph->execute(
            query: (string) ($op['query'] ?? ''),
            context: $context,
            variables: is_array($op['variables'] ?? null) ? $op['variables'] : [],
            operationName: isset($op['operationName']) ? (string) $op['operationName'] : null,
            schemaName: $schemaName,
        );

        return array_values(array_map($executor, $operations));
    }
}
