<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\DataLoader;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Batches an Eloquent relation for a set of parent keys into a single query,
 * using the relation's own eager-loading machinery (the same code path
 * `Model::with()` uses) so belongsTo, hasOne, hasMany, belongsToMany, and
 * morph* relations are all supported without per-type SQL.
 *
 * Not registered directly — obtain an instance via
 * {@see DataLoaderRegistry::relation()}.
 */
final class EloquentRelationLoader extends BatchResolver
{
    /**
     * @param  class-string<Model>  $modelClass
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly string $relation,
    ) {}

    /** @var array<string, Model> Parent models supplied by resolvers, keyed by primary key. */
    private array $parents = [];

    /**
     * Supply a parent model the resolver already holds, so batch() neither
     * re-queries it nor misses it when a global scope (e.g. soft deletes)
     * would hide it from a fresh query.
     */
    public function remember(Model $parent): void
    {
        $this->parents[(string) $parent->getKey()] = $parent;
    }

    /**
     * @param  array<int|string>  $keys  Parent model primary key values.
     * @return array<int|string, mixed>  Relation result per key, in $keys order.
     */
    public function batch(array $keys): array
    {
        $parents = [];
        $missing = [];

        foreach ($keys as $key) {
            if (isset($this->parents[(string) $key])) {
                $parents[(string) $key] = $this->parents[(string) $key];
            } else {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            /** @var Model $instance */
            $instance = new $this->modelClass();

            foreach ($this->modelClass::query()->whereIn($instance->getKeyName(), $missing)->get() as $parent) {
                $parents[(string) $parent->getKey()] = $parent;
            }
        }

        // One query for every parent that does not already have the relation loaded.
        (new Collection(array_values($parents)))->loadMissing($this->relation);

        $relation = $this->relation;

        return array_map(
            static fn(int|string $key): mixed => isset($parents[(string) $key])
                ? $parents[(string) $key]->getRelation($relation)
                : null,
            $keys,
        );
    }
}
