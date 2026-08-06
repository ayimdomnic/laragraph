<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tracing;

use GraphQL\Type\Definition\ResolveInfo;

/**
 * Records per-field resolver spans for a single GraphQL execution, in the
 * shape needed to build an Apollo Tracing (`extensions.tracing`) response.
 *
 * Bound as a container singleton (see {@see \Ayimdomnic\Laragraph\LaragraphServiceProvider})
 * and reset at the start of every {@see \Ayimdomnic\Laragraph\Laragraph::execute()}
 * call — the same "per-request, container-scoped" shape already used by
 * {@see \Ayimdomnic\Laragraph\Extensions\ExtensionRegistry}. A container
 * singleton (rather than something attached to the execution context) is
 * necessary here because field resolvers are wrapped once, at schema-build
 * time, in code ({@see \Ayimdomnic\Laragraph\Support\Type},
 * {@see \Ayimdomnic\Laragraph\Schema\SchemaBuilder}) that has no access to
 * the per-request context object.
 */
final class TracingCollector
{
    private ?int $startNs = null;

    private ?\DateTimeImmutable $startedAt = null;

    /**
     * @var list<array{path: list<int|string>, parentType: string, fieldName: string, returnType: string, startOffset: int, duration: int|null}>
     */
    private array $spans = [];

    public function reset(): void
    {
        $this->startNs   = hrtime(true);
        $this->startedAt = new \DateTimeImmutable();
        $this->spans     = [];
    }

    public function isActive(): bool
    {
        return $this->startNs !== null;
    }

    public function startedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    /** Nanoseconds elapsed since {@see reset()} was called. */
    public function elapsedNs(): int
    {
        return $this->startNs === null ? 0 : hrtime(true) - $this->startNs;
    }

    /**
     * Record the start of a field resolution. Returns a span id to pass to
     * {@see stop()}. No-op (returns -1) when the collector hasn't been reset
     * for this request, so a stale singleton never records misattributed spans.
     */
    public function start(ResolveInfo $info): int
    {
        if ($this->startNs === null) {
            return -1;
        }

        $this->spans[] = [
            'path'       => $info->path,
            'parentType' => $info->parentType->name(),
            'fieldName'  => $info->fieldName,
            'returnType' => (string) $info->returnType,
            'startOffset' => hrtime(true) - $this->startNs,
            'duration'   => null,
        ];

        return array_key_last($this->spans);
    }

    public function stop(int $spanId): void
    {
        if ($spanId < 0 || !isset($this->spans[$spanId])) {
            return;
        }

        $this->spans[$spanId]['duration'] = (hrtime(true) - $this->startNs) - $this->spans[$spanId]['startOffset'];
    }

    /**
     * @return list<array{path: list<int|string>, parentType: string, fieldName: string, returnType: string, startOffset: int, duration: int|null}>
     */
    public function spans(): array
    {
        return $this->spans;
    }

    /**
     * Wrap a field resolver so every call is recorded as a span. Used both
     * for explicit per-field resolvers (root Query/Mutation/Subscription
     * fields, and Type fields with a custom or convention-bound resolver)
     * and as webonyx's `fieldResolver` fallback for fields with none.
     *
     * Note: for resolvers that return a DataLoader promise, the recorded
     * duration only covers the synchronous portion of the call (enqueuing
     * the batch key) — the same limitation Apollo Tracing has for any
     * asynchronous resolver.
     */
    public static function wrap(callable $resolver): \Closure
    {
        return static function (mixed $root, array $args, mixed $context, ResolveInfo $info) use ($resolver) {
            $collector = app(self::class);
            $spanId    = $collector->start($info);

            try {
                return $resolver($root, $args, $context, $info);
            } finally {
                $collector->stop($spanId);
            }
        };
    }
}
