<?php

declare(strict_types=1);

namespace Workbench\App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Workbench\App\Models\User;

class CreateUserMutation extends Mutation
{
    public function type(): Type
    {
        return app('laragraph')->type('User');
    }

    public function args(): array
    {
        return [
            'name'  => ['type' => Type::nonNull(Type::string())],
            'email' => ['type' => Type::nonNull(Type::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
        ];
    }

    public function description(): ?string
    {
        return 'Create a new user.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return User::create($args);
    }
}
