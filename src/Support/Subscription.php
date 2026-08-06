<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Base class for GraphQL Subscription fields.
 *
 * Subscriptions require a WebSocket-capable broadcast driver (e.g. Laravel
 * Reverb, Pusher) and a client that supports GraphQL subscriptions.
 *
 * Usage:
 *
 *   class UserCreatedSubscription extends Subscription
 *   {
 *       public function type(): Type
 *       {
 *           return app('laragraph')->type('User');
 *       }
 *
 *       public function args(): array
 *       {
 *           return [];
 *       }
 *
 *       public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
 *       {
 *           // $root is the event payload broadcast to the channel
 *           return $root;
 *       }
 *
 *       public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
 *       {
 *           // Return the channel name(s) to subscribe to
 *           return 'users';
 *       }
 *   }
 */
abstract class Subscription extends Field
{
    /**
     * Return the channel or topic name the client should subscribe to.
     * By default returns null (no subscription channel wiring).
     */
    public function subscribe(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return null;
    }

    /**
     * Compile this subscription field definition.
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'subscribe' => fn (mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
                => $this->subscribe($root, $args, $context, $info),
        ]);
    }

    /**
     * Branches between registering a subscriber and resolving a live update.
     *
     * webonyx/graphql-php has no dedicated subscription-execution entrypoint
     * of its own — {@see \Ayimdomnic\Laragraph\Controllers\LaragraphController}
     * drives this by flagging the execution context (`$context->subscribing`)
     * for the initial HTTP request that registers a subscriber. On that pass,
     * this calls {@see subscribe()} to resolve the channel and hands it to the
     * {@see \Ayimdomnic\Laragraph\Subscriptions\SubscriptionRegistrar} attached
     * to the context, instead of running the field's normal resolver. Later,
     * when {@see \Ayimdomnic\Laragraph\Laragraph::broadcast()} re-executes this
     * subscriber's original query with the event payload as $root, `subscribing`
     * is absent/false and {@see resolve()} runs as usual.
     */
    protected function handleField(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        $subscribing = is_object($context) && ($context->subscribing ?? false);

        if ($subscribing) {
            $channel = $this->subscribe($root, $args, $context, $info);

            if (isset($context->subscriptionRegistrar)) {
                $context->subscriptionRegistrar->capture($channel);
            }

            return null;
        }

        return $this->resolve($root, $args, $context, $info);
    }
}
