<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\User;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class RegisterMutation extends Mutation
{
    public function type(): GType
    {
        return app('laragraph')->type('LoginPayload');
    }

    public function args(): array
    {
        return [
            'name' => ['type' => GType::nonNull(GType::string())],
            'email' => ['type' => GType::nonNull(GType::string())],
            'password' => ['type' => GType::nonNull(GType::string())],
            'password_confirmation' => ['type' => GType::nonNull(GType::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = User::create([
            'name' => $args['name'],
            'email' => $args['email'],
            'password' => $args['password'],
        ]);

        return [
            'token' => JWTAuth::fromUser($user),
            'user' => $user,
        ];
    }
}
