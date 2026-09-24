<?php

declare(strict_types=1);

namespace App\GraphQL\Types;

use App\Models\Organization;
use App\Models\Post;
use App\Models\User;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\UnionType;
use GraphQL\Type\Definition\ResolveInfo;

/**
 * UNION — `search` returns a mix of users, posts and organizations.
 * Clients pick fields per member with inline fragments (`... on Post { title }`).
 */
class SearchResultType extends UnionType
{
    protected array $attributes = [
        'name' => 'SearchResult',
        'description' => 'Anything `search` can find.',
    ];

    public function types(): array
    {
        return [Laragraph::type('User'), Laragraph::type('Post'), Laragraph::type('Organization')];
    }

    public function resolveType(mixed $value, mixed $context, ResolveInfo $info): mixed
    {
        return match (true) {
            $value instanceof User => Laragraph::type('User'),
            $value instanceof Post => Laragraph::type('Post'),
            $value instanceof Organization => Laragraph::type('Organization'),
            default => null,
        };
    }
}
