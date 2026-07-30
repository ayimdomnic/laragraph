<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class LoginMutation extends Mutation
{
    public function type(): GType
    {
        return app('laragraph')->type('LoginPayload');
    }

    public function args(): array
    {
        return [
            'email' => ['type' => GType::nonNull(GType::string())],
            'password' => ['type' => GType::nonNull(GType::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $token = JWTAuth::attempt([
            'email' => $args['email'],
            'password' => $args['password'],
        ]);

        if (! $token) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        return [
            'token' => $token,
            'user' => JWTAuth::user(),
        ];
    }
}
