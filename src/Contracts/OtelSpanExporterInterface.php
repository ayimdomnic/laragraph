<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Tracing\OtelSpanExporter;

/**
 * Replays an already-collected {@see TracingCollectorInterface} run into real
 * OpenTelemetry spans.
 *
 * @see OtelSpanExporter the built-in implementation.
 */
interface OtelSpanExporterInterface
{
    public function export(
        TracingCollectorInterface $collector,
        string $query,
        ?string $operationName,
        string $schemaName,
        bool $hasErrors,
    ): void;
}
