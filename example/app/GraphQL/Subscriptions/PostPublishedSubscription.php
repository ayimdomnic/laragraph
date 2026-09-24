<?php

declare(strict_types=1);

namespace App\GraphQL\Subscriptions;

use App\Models\Post;
use Ayimdomnic\Laragraph\Facades\Laragraph;
use Ayimdomnic\Laragraph\Support\Subscription;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * `postPublished(organizationId: ID!)` — REAL-TIME SUBSCRIPTIONS.
 *
 * 1. The client sends `subscription { postPublished(organizationId: 1) { id title } }`.
 *    authorize() runs, subscribe() picks the channel, and the response carries
 *    `extensions.subscription.subscriberId`.
 * 2. The client listens on the private channel
 *    `graphql-subscriber.{subscriberId}` with Laravel Echo (event
 *    `.GraphQLSubscriptionUpdate`). Laragraph authorizes that channel: only
 *    the subscriber may join.
 * 3. When a post is published, PublishPostMutation calls
 *    Laragraph::broadcastLater($channel, $post): the query is re-run with the
 *    post as $root — as the subscriber — and the result is pushed to them.
 */
class PostPublishedSubscription extends Subscription
{
    public static function channelFor(int|string $organizationId): string
    {
        return "organization.{$organizationId}.posts";
    }

    /**
     * Must be nullable: on the subscribing request the field resolves to
     * null (there is no post yet) — only later updates carry data.
     */
    public function type(): Type
    {
        return Laragraph::type('Post');
    }

    public function args(): array
    {
        return ['organizationId' => ['type' => Type::nonNull(Type::id())]];
    }

    /** Only members of the organization may subscribe to its posts. */
    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        $user = $context->user();

        return $user !== null && (string) $user->organization_id === (string) $args['organizationId'];
    }

    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return self::channelFor($args['organizationId']);
    }

    /** $root is the payload given to broadcast()/broadcastLater() — the Post. */
    public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $root instanceof Post ? $root : Post::findOrFail($root);
    }
}
