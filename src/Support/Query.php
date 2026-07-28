<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

/**
 * Base class for GraphQL Query fields.
 *
 * Usage:
 *
 *   class UsersQuery extends Query
 *   {
 *       public function type(): \GraphQL\Type\Definition\Type
 *       {
 *           return Type::listOf(app('laragraph')->type('User'));
 *       }
 *
 *       public function args(): array
 *       {
 *           return [
 *               'limit' => ['type' => Type::int(), 'defaultValue' => 10],
 *           ];
 *       }
 *
 *       public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
 *       {
 *           return User::limit($args['limit'])->get();
 *       }
 *   }
 */
abstract class Query extends Field
{
    // Query is a thin semantic wrapper around Field.
    // All behaviour lives in Field; subclasses may override any method.
}
