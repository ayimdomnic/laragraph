<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use Illuminate\Database\Eloquent\Attributes\Fillable as FillableAttribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Resolves a model's mass-assignable columns from *both* declaration sources:
 * the traditional `protected $fillable` property and Laravel 11+'s
 * `#[Fillable([...])]` class attribute.
 *
 * Precedence follows the issue-#54 contract — deliberately stricter than
 * Laravel's own runtime behaviour:
 *
 *  1. A non-empty declared `$fillable` property wins, *even when* a
 *     `#[Fillable]` attribute is also present. (On recent Laravel the
 *     framework merges the attribute into the property at construction —
 *     we read the declared default instead, so "both declared" resolves to
 *     the property alone, and models that mutate `$fillable` in their
 *     constructor never surprise scaffold output.)
 *  2. Otherwise the `#[Fillable]` attribute is used, searched up the parent
 *     chain and on traits — mirroring Laravel's own `resolveClassAttribute()`.
 *  3. Otherwise an empty list — matching "nothing declared" semantics.
 *
 * On Laravel 10 the attribute class does not exist, so only the property
 * path is reachable; declaring `#[Fillable]` is impossible there anyway.
 */
final class FillableResolver
{
    /**
     * Resolve the fillable columns declared by a model class.
     *
     * @param  string  $modelClass  any class name; non-models resolve to []
     * @return list<string>
     */
    public static function for(string $modelClass): array
    {
        if (! is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $reflection = new \ReflectionClass($modelClass);

        // 1. Prefer the declared `protected $fillable` property — read the
        //    class-level default (no instantiation) so the result is stable
        //    across Laravel versions and immune to the runtime merge.
        $declared = $reflection->getDefaultProperties()['fillable'] ?? null;

        if (is_array($declared) && $declared !== []) {
            return array_values(array_filter($declared, static fn(mixed $v): bool => is_string($v) && $v !== ''));
        }

        // 2. Fall back to the `#[Fillable]` attribute (Laravel 11+).
        return self::fromAttribute($reflection);
    }

    /**
     * Search the class, its traits, then its parents for `#[Fillable([...])]`.
     *
     * @param  \ReflectionClass<Model>  $reflection
     * @return list<string>
     */
    private static function fromAttribute(\ReflectionClass $reflection): array
    {
        // Laravel 10 has no attribute class — nothing to resolve.
        if (! class_exists(FillableAttribute::class)) {
            return [];
        }

        for ($class = $reflection; $class !== false; $class = $class->getParentClass()) {
            $columns = self::attributeColumns($class->getAttributes(FillableAttribute::class, \ReflectionAttribute::IS_INSTANCEOF));

            if ($columns !== null) {
                return $columns;
            }

            foreach ($class->getTraits() as $trait) {
                $columns = self::attributeColumns($trait->getAttributes(FillableAttribute::class, \ReflectionAttribute::IS_INSTANCEOF));

                if ($columns !== null) {
                    return $columns;
                }
            }
        }

        return [];
    }

    /**
     * @param  list<\ReflectionAttribute<object>>  $attributes
     * @return list<string>|null  null when no attribute is present at all
     */
    private static function attributeColumns(array $attributes): ?array
    {
        if ($attributes === []) {
            return null;
        }

        /** @var FillableAttribute $instance */
        $instance = $attributes[0]->newInstance();

        return array_values($instance->columns);
    }
}
