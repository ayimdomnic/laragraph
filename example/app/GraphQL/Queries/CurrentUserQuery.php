<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class CurrentUserQuery extends Query
{
    public function type(): GType
    {
        return app('laragraph')->type('User');
    }

    public function description(): ?string
    {
        return 'Get the currently authenticated user.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        try {
            return JWTAuth::parseToken()->authenticate();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
