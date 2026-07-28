<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

/**
 * Reports query execution wall-clock time under `extensions.timing`.
 *
 * The elapsed time is measured from the start of
 * {@see \Ayimdomnic\Laragraph\Laragraph::execute()} to just before the
 * response is built and is passed in via `$context['execution_ms']`.
 *
 * Enable via config:
 * ```php
 * 'extensions' => ['query_timing' => true],
 * ```
 *
 * Response shape:
 * ```json
 * { "extensions": { "timing": { "execution_ms": 12.4 } } }
 * ```
 */
final class QueryTimingExtension implements GraphQLExtensionInterface
{
    public function key(): string
    {
        return 'timing';
    }

    /**
     * @param  array{execution_ms?: float} $context
     * @return array{execution_ms: float}
     */
    public function get(array $context = []): array
    {
        return ['execution_ms' => $context['execution_ms'] ?? 0.0];
    }
}
