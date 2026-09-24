<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * `register` — Laravel validation rules (including `confirmed` and `exists`)
 * run before the resolver; failures come back under extensions.validation.
 */
class RegisterMutation extends Mutation
{
    public function type(): Type
    {
        return Laragraph::type('LoginPayload');
    }

    public function args(): array
    {
        return [
            'name' => ['type' => Type::nonNull(Type::string())],
            'email' => ['type' => Type::nonNull(Type::string())],
            'password' => ['type' => Type::nonNull(Type::string())],
            'password_confirmation' => ['type' => Type::nonNull(Type::string())],
            'organizationSlug' => ['type' => Type::nonNull(Type::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'organizationSlug' => ['required', 'exists:organizations,slug'],
        ];
    }

    public function messages(): array
    {
        return ['organizationSlug.exists' => 'There is no organization with that slug.'];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => $args['password'],
            'role' => UserRole::Member,
            'organization_id' => Organization::where('slug', $args['organizationSlug'])->value('id'),
        ]);

        return [
            'token' => JWTAuth::fromUser($user),
            'expiresIn' => JWTAuth::factory()->getTTL() * 60,
            'user' => $user,
        ];
    }
}
