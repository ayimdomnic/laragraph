<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Executor\Executor;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Utils\Utils;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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

            // A real null column value and an accessor both already resolved
            // above — this only catches a field name that never existed in
            // the model at all, the classic camelCase/snake_case mismatch.
            if ($value === null && self::shouldWarn() && !array_key_exists($info->fieldName, $source->getAttributes())) {
                self::warnUnresolved($info);
            }
        } else {
            $value = Utils::extractKey($source, $info->fieldName);

            if ($value === null && self::shouldWarn() && is_array($source) && !array_key_exists($info->fieldName, $source)) {
                self::warnUnresolved($info);
            }
        }

        return $value instanceof \Closure ? $value($source, $args, $context, $info) : $value;
    }

    /**
     * Off by default in production, on by default in development — matching
     * the `config('laragraph.<key>') ?? config('app.debug')` convention used
     * throughout {@see Laragraph} for opt-in-in-prod,
     * loud-in-dev behaviour.
     */
    private static function shouldWarn(): bool
    {
        return (bool) (config('laragraph.log_unresolved_fields') ?? config('app.debug'));
    }

    private static function warnUnresolved(ResolveInfo $info): void
    {
        $message = "GraphQL: field [{$info->fieldName}] on [{$info->parentType->name()}] resolved to null because "
            . 'no matching attribute/key was found and no resolve{Field}Field() method exists — '
            . 'check for a camelCase/snake_case mismatch.';
        $context = ['field' => $info->fieldName, 'type' => $info->parentType->name()];

        $channel = config('laragraph.logging.channel');

        if ($channel !== null && $channel !== '') {
            Log::channel($channel)->warning($message, $context);
        } else {
            Log::warning($message, $context);
        }
    }
}
