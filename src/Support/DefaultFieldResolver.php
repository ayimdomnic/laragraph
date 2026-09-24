<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\Utils;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves fields that declare no resolver of their own.
 *
 * Behaves like webonyx's {@see Executor::defaultFieldResolver()}
 * — array keys, ArrayAccess offsets and object properties, calling a Closure
 * value with the resolver arguments — but reads Eloquent attributes once.
 * webonyx reads an ArrayAccess offset as `$value[$key] ?? null`, and on a
 * model that runs offsetExists() *and* offsetGet(): every attribute (with
 * its casts and accessors) was computed twice per field.
 */
final class DefaultFieldResolver
{
    /**
     * @param array<string, mixed> $args
     */
    public static function resolve(mixed $source, array $args, mixed $context, ResolveInfo $info): mixed
    {
        if ($source instanceof Model) {
            try {
                $value = $source->getAttribute($info->fieldName);
            } catch (MissingAttributeException) {
                // Model::shouldBeStrict(): read as null, exactly as `$model[$key] ?? null` does.
                $value = null;
            }
        } else {
            $value = Utils::extractKey($source, $info->fieldName);
        }

        return $value instanceof \Closure ? $value($source, $args, $context, $info) : $value;
    }
}
