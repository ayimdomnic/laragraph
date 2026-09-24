<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\UnionType as GraphQLUnionType;

/**
 * Base class for GraphQL Union Types.
 *
 * Usage:
 *
 *   class SearchResultUnion extends UnionType
 *   {
 *       protected array $attributes = [
 *           'name'        => 'SearchResult',
 *           'description' => 'A search result can be a User or a Post.',
 *       ];
 *
 *       public function types(): array
 *       {
 *           return [
 *               app('laragraph')->type('User'),
 *               app('laragraph')->type('Post'),
 *           ];
 *       }
 *
 *       public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
 *       {
 *           return $value instanceof \App\Models\User
 *               ? app('laragraph')->type('User')
 *               : app('laragraph')->type('Post');
 *       }
 *   }
 */
abstract class UnionType extends GraphQLUnionType
{
    /**
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    public function __construct()
    {
        $config = array_merge(
            ['name' => class_basename(static::class)],
            $this->attributes,
            [
                'types'       => $this->types(...),
                'resolveType' => fn(mixed $value, mixed $context, ResolveInfo $info): mixed => $this->resolveType($value, $context, $info),
            ],
        );

        parent::__construct($config);
    }

    /**
     * Return the array of possible concrete ObjectType instances.
     *
     * @return array<ObjectType>
     */
    abstract public function types(): array;

    /**
     * Resolve the concrete ObjectType for a given value.
     *
     * @param  mixed  $value    The resolved field value
     * @param  mixed  $context  Shared execution context
     */
    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }
}
