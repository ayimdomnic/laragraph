<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Middleware\ThrottleMiddleware;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * `login` — FIELD MIDDLEWARE: at most 5 attempts per minute per client.
 */
class LoginMutation extends Mutation
{
    public function type(): Type
    {
        return Laragraph::type('LoginPayload');
    }

    public function args(): array
    {
        return [
            'email' => ['type' => Type::nonNull(Type::string())],
            'password' => ['type' => Type::nonNull(Type::string())],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    public function middleware(): array
    {
        return [new ThrottleMiddleware(maxAttempts: 5, decaySeconds: 60)];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $token = JWTAuth::attempt(['email' => $args['email'], 'password' => $args['password']]);

        if (! is_string($token)) {
            // A GraphQL\Error\Error message is always shown to the client.
            throw new Error('The provided credentials are incorrect.');
        }

        return [
            'token' => $token,
            'expiresIn' => JWTAuth::factory()->getTTL() * 60,
            'user' => JWTAuth::user(),
        ];
    }
}
