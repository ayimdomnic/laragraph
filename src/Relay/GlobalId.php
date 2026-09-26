<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Relay;

/**
 * Encodes/decodes Relay's opaque global object id: base64 of `"{Type}:{id}"`.
 *
 * Purely additive — no existing field's `id` value changes unless a type
 * chooses to encode it. See {@see NodeQuery} for how a global id round-trips
 * back through the `node(id: ID!): Node` root field.
 */
final class GlobalId
{
    public static function encode(string $type, int|string $id): string
    {
        return base64_encode("{$type}:{$id}");
    }

    /**
     * @return array{type: string, id: string}|null Null when $globalId isn't a
     *                                               validly-encoded `"Type:id"` pair.
     */
    public static function decode(string $globalId): ?array
    {
        $decoded = base64_decode($globalId, true);

        if ($decoded === false || !str_contains($decoded, ':')) {
            return null;
        }

        [$type, $id] = explode(':', $decoded, 2);

        if ($type === '' || $id === '') {
            return null;
        }

        return ['type' => $type, 'id' => $id];
    }
}
