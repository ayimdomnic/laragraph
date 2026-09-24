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
    private const ATTRIBUTE = 'laragraph.request_id';

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
        return ['id' => $this->id ??= $this->resolve()];
    }

    /**
     * One id per HTTP request — shared by every operation in a batch. A
     * client-supplied X-Request-ID is only echoed back when it looks like an
     * id (letters, digits, `.`, `_`, `-`; at most 128 characters).
     */
    private function resolve(): string
    {
        $request = request();
        $id      = $request->attributes->get(self::ATTRIBUTE);

        if (is_string($id)) {
            return $id;
        }

        $header = $request->header('X-Request-ID');
        $id     = is_string($header) && preg_match('/^[A-Za-z0-9._-]{1,128}$/', $header) === 1
            ? $header
            : (string) Str::uuid();

        $request->attributes->set(self::ATTRIBUTE, $id);

        return $id;
    }
}
