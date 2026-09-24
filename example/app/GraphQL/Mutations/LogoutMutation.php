<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Ayimdomnic\Laragraph\Auth\AuthorizationContext;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * `logout` — guard-aware authorization via authorizeWithContext().
 */
class LogoutMutation extends Mutation
{
    public function type(): Type
    {
        return Type::nonNull(Type::boolean());
    }

    /** Which guard(s) authenticate this field; the first one that does is used. */
    public function guards(): array
    {
        return ['api'];
    }

    public function authorizeWithContext(AuthorizationContext $ctx): bool
    {
        return $ctx->check();
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        JWTAuth::parseToken()->invalidate();

        return true;
    }
}
