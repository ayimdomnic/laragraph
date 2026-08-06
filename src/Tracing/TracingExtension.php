<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tracing;

use Ayimdomnic\Laragraph\Extensions\GraphQLExtensionInterface;

/**
 * Reports per-field resolver timings under `extensions.tracing`, following
 * the Apollo Tracing format (https://github.com/apollographql/apollo-tracing)
 * so existing GraphQL tooling that understands that shape works out of the box.
 *
 * Enable via config:
 * ```php
 * 'tracing' => ['enabled' => true],
 * ```
 */
final class TracingExtension implements GraphQLExtensionInterface
{
    public function __construct(private readonly TracingCollector $collector) {}

    public function key(): string
    {
        return 'tracing';
    }

    /**
     * @return array<string, mixed>
     */
    public function get(array $context = []): array
    {
        if (!$this->collector->isActive()) {
            return [];
        }

        $startedAt = $this->collector->startedAt();
        $durationNs = $this->collector->elapsedNs();

        return [
            'version'   => 1,
            'startTime' => $startedAt?->format('Y-m-d\TH:i:s.v\Z') ?? '',
            'endTime'   => $startedAt?->modify('+' . intdiv($durationNs, 1_000_000) . ' milliseconds')?->format('Y-m-d\TH:i:s.v\Z') ?? '',
            'duration'  => $durationNs,
            'execution' => [
                'resolvers' => array_map(
                    static fn (array $span): array => [
                        'path'        => $span['path'],
                        'parentType'  => $span['parentType'],
                        'fieldName'   => $span['fieldName'],
                        'returnType'  => $span['returnType'],
                        'startOffset' => $span['startOffset'],
                        'duration'    => $span['duration'] ?? 0,
                    ],
                    $this->collector->spans(),
                ),
            ],
        ];
    }
}
