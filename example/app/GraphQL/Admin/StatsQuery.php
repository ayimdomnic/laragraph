<?php

declare(strict_types=1);

namespace App\GraphQL\Admin;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `stats` on the separate admin schema (POST /graphql/admin). The schema's
 * route middleware (auth:api + can:access-admin-api) has already run by the
 * time this resolves. It lives outside the discovered directories, so it is
 * only part of the schema that lists it explicitly.
 */
class StatsQuery extends Query
{
    public function type(): Type
    {
        return Type::nonNull(Laragraph::type('AdminStats'));
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $organizationId = $context->user()->organization_id;
        $posts = Post::where('organization_id', $organizationId);

        return [
            'members' => User::where('organization_id', $organizationId)->count(),
            'publishedPosts' => (clone $posts)->where('status', PostStatus::Published)->count(),
            'draftPosts' => (clone $posts)->where('status', PostStatus::Draft)->count(),
        ];
    }
}
