<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

/**
 * Base class for GraphQL Mutation fields.
 *
 * Usage:
 *
 *   class CreateUserMutation extends Mutation
 *   {
 *       public function type(): \GraphQL\Type\Definition\Type
 *       {
 *           return app('laragraph')->type('User');
 *       }
 *
 *       public function args(): array
 *       {
 *           return [
 *               'name'  => ['type' => Type::nonNull(Type::string())],
 *               'email' => ['type' => Type::nonNull(Type::string())],
 *           ];
 *       }
 *
 *       public function rules(array $args = []): array
 *       {
 *           return [
 *               'name'  => ['required', 'string', 'max:255'],
 *               'email' => ['required', 'email', 'unique:users,email'],
 *           ];
 *       }
 *
 *       public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
 *       {
 *           return User::create($args);
 *       }
 *   }
 */
abstract class Mutation extends Field
{
    // Mutation is a thin semantic wrapper around Field.
    // Validation and authorization are handled by Field::buildResolver().
}
