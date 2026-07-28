<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Type\Definition\InterfaceType as GraphQLInterfaceType;

/**
 * Base class for GraphQL Interface Types.
 *
 * Usage:
 *
 *   class NodeInterface extends InterfaceType
 *   {
 *       protected array $attributes = [
 *           'name'        => 'Node',
 *           'description' => 'An object with a globally unique ID.',
 *       ];
 *
 *       public function fields(): array
 *       {
 *           return [
 *               'id' => ['type' => Type::nonNull(Type::id())],
 *           ];
 *       }
 *
 *       public function resolveType(mixed $value): string
 *       {
 *           return match (true) {
 *               $value instanceof \App\Models\User => 'User',
 *               $value instanceof \App\Models\Post => 'Post',
 *               default => throw new \GraphQL\Error\Error('Unknown type'),
 *           };
 *       }
 *   }
 */
abstract class InterfaceType extends GraphQLInterfaceType
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
                'fields' => function (): array {
                    return $this->fields();
                },
                'resolveType' => fn (mixed $value, mixed $context, \GraphQL\Type\Definition\ResolveInfo $info): mixed => $this->resolveType($value, $context, $info),
            ],
        );

        parent::__construct($config);
    }

    /**
     * Return the interface field definitions.
     *
     * @return array<string, mixed>
     */
    abstract public function fields(): array;

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
