<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Http\Requests\UpdateProfileRequest;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

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

    /**
     * FormRequest reuse — rules() / messages() / attributes() come from the
     * same App\Http\Requests\UpdateProfileRequest a REST endpoint would use.
     * Authorization stays on this field (authorize() below); the FormRequest's
     * authorize() is deliberately never called.
     */
    protected function formRequest(): ?string
    {
        return UpdateProfileRequest::class;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = $context->user();
        $user->update(array_intersect_key($args, array_flip(['name', 'email'])));

        return $user;
    }
}
