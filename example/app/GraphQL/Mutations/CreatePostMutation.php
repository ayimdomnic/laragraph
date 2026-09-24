<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\PostStatus;
use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Gate;

/**
 * `createPost(input: PostInput!)` — an input type, dot-notation validation
 * rules, a policy check, and a queued subscription broadcast.
 */
class CreatePostMutation extends Mutation
{
    public function type(): Type
    {
        return Type::nonNull(Laragraph::type('Post'));
    }

    public function args(): array
    {
        return ['input' => ['type' => Type::nonNull(Laragraph::type('PostInput'))]];
    }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return Gate::allows('create', Post::class);
    }

    public function rules(array $args = []): array
    {
        return [
            'input.title' => ['required', 'string', 'min:3', 'max:200'],
            'input.body' => ['required', 'string', 'min:10'],
        ];
    }

    public function attributes(): array
    {
        return ['input.title' => 'title', 'input.body' => 'body'];
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $user = $context->user();
        $publish = (bool) $args['input']['publish'];

        $post = Post::create([
            'title' => $args['input']['title'],
            'body' => $args['input']['body'],
            'status' => $publish ? PostStatus::Published : PostStatus::Draft,
            'published_at' => $publish ? now() : null,
            'user_id' => $user->id,
            'organization_id' => $user->organization_id,
        ]);

        if ($publish) {
            PublishPostMutation::notifySubscribers($post);
        }

        return $post;
    }
}
