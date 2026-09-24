<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\Rule;

/**
 * `updateProfile` — users can only ever change *their own* profile: the
 * record comes from the authenticated user, never from an `id` argument.
 */
class UpdateProfileMutation extends Mutation
{
    public function type(): Type
    {
        return Type::nonNull(Laragraph::type('User'));
    }

    public function args(): array
    {
        return [
            'name' => ['type' => Type::string()],
            'email' => ['type' => Type::string()],
        ];
    }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return $context->user() !== null;
    }

    public function rules(array $args = []): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore(auth()->id())],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = $context->user();
        $user->update(array_intersect_key($args, array_flip(['name', 'email'])));

        return $user;
    }
}
