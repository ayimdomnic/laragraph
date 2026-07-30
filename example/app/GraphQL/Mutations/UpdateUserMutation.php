<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Validation\Rule;

class UpdateUserMutation extends Mutation
{
    public function type(): GType
    {
        return app('laragraph')->type('User');
    }

    public function args(): array
    {
        return [
            'id' => ['type' => GType::nonNull(GType::id())],
            'name' => ['type' => GType::string()],
            'email' => ['type' => GType::string()],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($args['id'] ?? null),
            ],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = User::find($args['id']);

        if ($user === null) {
            return null;
        }

        $user->fill(array_filter([
            'name' => $args['name'] ?? null,
            'email' => $args['email'] ?? null,
        ], fn ($value) => $value !== null));

        $user->save();

        return $user;
    }
}
