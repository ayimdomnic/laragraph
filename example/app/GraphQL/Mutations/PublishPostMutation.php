<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\PostStatus;
use App\GraphQL\Subscriptions\PostPublishedSubscription;
use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Mutation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `publishPost` — the policy() shortcut can't see the post being published,
 * so this mutation authorizes with the model instance instead.
 */
class PublishPostMutation extends Mutation
{
    public function type(): Type
    {
        return Type::nonNull(Laragraph::type('Post'));
    }

    public function args(): array
    {
        return ['id' => ['type' => Type::nonNull(Type::id())]];
    }

    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        $post = Post::find($args['id']);

        return $post !== null && ($context->user()?->can('update', $post) ?? false);
    }

    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $post = Post::findOrFail($args['id']);

        if ($post->status !== PostStatus::Published) {
            $post->update(['status' => PostStatus::Published, 'published_at' => now()]);
            self::notifySubscribers($post);
        }

        return $post;
    }

    /**
     * Push the post to every `postPublished` subscriber of its organization.
     * broadcastLater() queues the fan-out, so this mutation stays fast; each
     * subscriber's query runs authenticated as that subscriber.
     */
    public static function notifySubscribers(Post $post): void
    {
        Laragraph::broadcastLater(PostPublishedSubscription::channelFor($post->organization_id), $post);
    }
}
