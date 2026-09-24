<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `search` — returns a union, validates its input and declares a cost.
 */
class SearchQuery extends Query
{
    public function type(): Type
    {
        return Type::nonNull(Type::listOf(Type::nonNull(Laragraph::type('SearchResult'))));
    }

    public function args(): array
    {
        return [
            'term' => ['type' => Type::nonNull(Type::string())],
            'limit' => ['type' => Type::int(), 'defaultValue' => 10],
        ];
    }

    public function rules(array $args = []): array
    {
        return [
            'term' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['integer', 'between:1,25'],
        ];
    }

    /** Counts towards laragraph.security.query_max_complexity. */
    public function complexity(): ?int
    {
        return 20;
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $like = '%'.$args['term'].'%';
        $limit = $args['limit'];

        return [
            ...Organization::where('name', 'like', $like)->limit($limit)->get(),
            ...Post::visibleTo($context->user())->where('title', 'like', $like)->limit($limit)->get(),
            ...User::where('name', 'like', $like)
                ->where('organization_id', $context->user()?->organization_id ?? 0)
                ->limit($limit)
                ->get(),
        ];
    }
}
