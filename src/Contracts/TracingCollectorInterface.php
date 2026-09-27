<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * Records per-field resolver spans for a single GraphQL execution.
 *
 * The static {@see TracingCollector::wrap()}
 * helper is deliberately not part of this contract — it is invoked from
 * schema-build-time closures with no access to an injected instance, and
 * resolves the container singleton itself.
 *
 * @see TracingCollector the built-in implementation.
 */
interface TracingCollectorInterface
{
    public function reset(): void;

    public function isActive(): bool;

    public function startedAt(): ?\DateTimeImmutable;

    /** Nanoseconds elapsed since {@see reset()} was called. */
    public function elapsedNs(): int;

    /**
     * Record the start of a field resolution. Returns a span id to pass to {@see stop()}.
     */
    public function start(ResolveInfo $info): int;

    public function stop(int $spanId): void;

    /**
     * @return list<array{path: list<int|string>, parentType: string, fieldName: string, returnType: string, startOffset: int, duration: int|null}>
     */
    public function spans(): array;
}
