<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\DataLoader;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

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

    /**
     * @param  array<int|string>  $keys  Parent model primary key values.
     * @return array<int|string, mixed>  Relation result per key, in $keys order.
     */
    public function batch(array $keys): array
    {
        /** @var Model $instance */
        $instance = new $this->modelClass();
        $keyName  = $instance->getKeyName();

        $parents = $this->modelClass::query()->whereIn($keyName, $keys)->get();

        /** @var Relation $relation */
        $relation = Relation::noConstraints(fn () => $instance->{$this->relation}());
        $relation->addEagerConstraints($parents->all());
        $results = $relation->getEager();
        $parents = $relation->match($parents->all(), $results, $this->relation);

        $byKey = [];
        foreach ($parents as $parent) {
            $byKey[(string) $parent->getKey()] = $parent->getRelation($this->relation);
        }

        return array_map(
            static fn ($key) => $byKey[(string) $key] ?? null,
            $keys,
        );
    }
}
