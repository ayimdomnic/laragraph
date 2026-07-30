<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type as GType;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class LogoutMutation extends Mutation
{
    public function type(): GType
    {
        return GType::nonNull(GType::boolean());
    }

    public function description(): ?string
    {
        return 'Log out the current user.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        if (request()->bearerToken()) {
            JWTAuth::parseToken()->invalidate();
        }

        return true;
    }
}
