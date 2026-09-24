<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Pagination\ConnectionType;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `posts` — newest first; guests see published posts, authors also see
 * their drafts, admins see every draft in their organization.
 */
class PostsQuery extends Query
{
    public function type(): Type
    {
        return ConnectionType::make('PostConnection', Laragraph::type('Post'));
    }

    public function args(): array
    {
        return [
            ...ConnectionType::args(),
            'status' => ['type' => Laragraph::type('PostStatus')],
            'organizationId' => ['type' => Type::id()],
        ];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $query = Post::query()
            ->visibleTo($context->user())
            ->when(isset($args['status']), fn ($query) => $query->where('status', $args['status']))
            ->when(isset($args['organizationId']), fn ($query) => $query->where('organization_id', $args['organizationId']))
            ->orderByDesc('id');

        return ConnectionType::paginate($query, $args);
    }
}
