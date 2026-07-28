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
}
