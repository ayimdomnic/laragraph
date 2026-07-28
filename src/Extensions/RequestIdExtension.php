<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Extensions;

use Illuminate\Support\Str;

/**
 * Propagates a per-request identifier under `extensions.requestId`.
 *
 * The value is taken from the incoming `X-Request-ID` header when present;
 * otherwise a fresh UUID v4 is generated. The same ID is returned for all
 * calls within the same instance, so batched operations share a single ID.
 *
 * Enable via config:
 * ```php
 * 'extensions' => ['request_id' => true],
 * ```
 *
 * Response shape:
 * ```json
 * { "extensions": { "requestId": { "id": "018e..." } } }
 * ```
 */
final class RequestIdExtension implements GraphQLExtensionInterface
{
    private ?string $id = null;

    public function key(): string
    {
        return 'requestId';
    }

    /**
     * @return array{id: string}
     */
    public function get(array $context = []): array
    {
        if ($this->id === null) {
            $this->id = request()->header('X-Request-ID') ?: (string) Str::uuid();
        }

        return ['id' => $this->id];
    }
}
