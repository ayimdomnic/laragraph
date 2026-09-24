<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `me` — the authenticated user, or null for guests.
 *
 * $context is the HTTP request (a GraphQLContext), so $context->user()
 * resolves the user from the default guard (the JWT "api" guard here).
 */
class MeQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('User');
    }

    public function description(): ?string
    {
        return 'The authenticated user, or null.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $context->user();
    }
}
