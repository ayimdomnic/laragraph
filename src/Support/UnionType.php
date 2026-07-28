<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

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
 *       public function resolveType(mixed $value): mixed
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
                'types'       => fn (): array => $this->types(),
                'resolveType' => fn (mixed $value, mixed $context, \GraphQL\Type\Definition\ResolveInfo $info): mixed => $this->resolveType($value, $context, $info),
            ],
        );

        parent::__construct($config);
    }

    /**
     * Return the array of possible concrete ObjectType instances.
     *
     * @return array<\GraphQL\Type\Definition\ObjectType>
     */
    abstract public function types(): array;

    /**
     * Resolve the concrete ObjectType for a given value.
     *
     * @param  mixed  $value    The resolved field value
     * @param  mixed  $context  Shared execution context
     * @param  \GraphQL\Type\Definition\ResolveInfo  $info
     */
    public function resolveType(mixed $value, mixed $context, \GraphQL\Type\Definition\ResolveInfo $info): mixed
    {
        return null;
    }
}
