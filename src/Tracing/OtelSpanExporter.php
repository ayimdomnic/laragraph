<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tracing;

use Ayimdomnic\Laragraph\Support\Operation;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;

/**
 * Replays an already-collected {@see TracingCollector} run into real
 * OpenTelemetry spans, with explicit historical timestamps rather than
 * instrumenting resolvers a second way.
 *
 * {@see TracingCollector} records every span as a plain array with
 * nanosecond-precision offsets relative to one absolute anchor
 * ({@see TracingCollector::startedAt()}) — this class only needs to convert
 * those into real start/end timestamps and open/close spans for them, after
 * execution has already finished. The hot-path resolver-wrapping code that
 * feeds the collector is untouched by this — see docs/12-observability.md.
 *
 * Depends only on `open-telemetry/api` (interfaces plus a no-op default): the
 * consuming application wires up its own SDK/exporter the standard OTel way.
 * With no SDK configured, every call here is a cheap no-op.
 */
final class OtelSpanExporter
{
    public function export(
        TracingCollector $collector,
        string $query,
        ?string $operationName,
        string $schemaName,
        bool $hasErrors,
    ): void {
        $startedAt = $collector->startedAt();

        if (!$startedAt instanceof \DateTimeImmutable) {
            return;
        }

        $tracer = $this->tracer();
        $startEpochNanos = ((int) $startedAt->format('U')) * 1_000_000_000 + ((int) $startedAt->format('u')) * 1_000;

        // A GraphQL operation name, when given, is never empty (grammar:
        // /[_A-Za-z][_0-9A-Za-z]*/) — guarded explicitly rather than widening
        // spanBuilder()'s non-empty-string parameter.
        $spanName = $operationName !== null && $operationName !== '' ? $operationName : 'GraphQL Operation';

        $root = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setStartTimestamp($startEpochNanos)
            ->setAttribute('graphql.operation.name', $operationName)
            ->setAttribute('graphql.operation.type', Operation::type($query, $operationName))
            ->setAttribute('graphql.document', $query)
            ->setAttribute('laragraph.schema', $schemaName)
            ->startSpan();

        if ($hasErrors) {
            $root->setStatus(StatusCode::STATUS_ERROR);
        }

        $parentContext = $root->storeInContext(Context::getCurrent());

        foreach ($collector->spans() as $span) {
            // Same grammar guarantee as $spanName above — a resolved field's
            // name is never empty; guarded rather than widened.
            if ($span['fieldName'] === '') {
                continue;
            }

            $childStart = $startEpochNanos + $span['startOffset'];

            $child = $tracer->spanBuilder($span['fieldName'])
                ->setParent($parentContext)
                ->setSpanKind(SpanKind::KIND_INTERNAL)
                ->setStartTimestamp($childStart)
                ->setAttribute('graphql.field.name', $span['fieldName'])
                ->setAttribute('graphql.field.path', implode('.', array_map(strval(...), $span['path'])))
                ->setAttribute('graphql.type.name', $span['parentType'])
                ->setAttribute('graphql.field.return_type', $span['returnType'])
                ->startSpan();

            $child->end($childStart + ($span['duration'] ?? 0));
        }

        $root->end($startEpochNanos + $collector->elapsedNs());
    }

    private function tracer(): TracerInterface
    {
        $name = (string) config('laragraph.tracing.otel.tracer_name', 'laragraph');

        return Globals::tracerProvider()->getTracer($name);
    }
}
