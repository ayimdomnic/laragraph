<?php

declare(strict_types=1);

namespace App\GraphQL\Queries;

use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Query;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Gate;

class PostQuery extends Query
{
    public function type(): Type
    {
        return Laragraph::type('Post');
    }

    public function args(): array
    {
        return ['id' => ['type' => Type::nonNull(Type::id())]];
    }

    /** Drafts are only visible to their author and organization admins (PostPolicy::view). */
    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        $post = Post::find($args['id']);

        return $post !== null && Gate::allows('view', $post);
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return Post::findOrFail($args['id']);
    }
}
