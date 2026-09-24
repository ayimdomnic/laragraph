<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * DEPRECATED FIELD — kept for old clients; introspection and GraphiQL flag it.
 */
class CurrentUserQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('User');
    }

    public function deprecated(): ?string
    {
        return 'Use `me` instead.';
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $context->user();
    }
}
