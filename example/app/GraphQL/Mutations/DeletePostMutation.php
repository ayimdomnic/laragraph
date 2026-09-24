<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Post;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Gate;

class DeletePostMutation extends Mutation
{
    public function type(): Type
    {
        return Type::nonNull(Type::boolean());
    }

    public function args(): array
    {
        return ['id' => ['type' => Type::nonNull(Type::id())]];
    }

    /** Missing and forbidden posts look the same, so ids can't be probed. */
    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        $post = Post::find($args['id']);

        return $post !== null && Gate::allows('delete', $post);
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return (bool) Post::findOrFail($args['id'])->delete();
    }
}
