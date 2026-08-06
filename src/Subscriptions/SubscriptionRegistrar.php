<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

/**
 * A per-request, mutable carrier attached to the execution context
 * (`$context->subscriptionRegistrar`) only while registering a new
 * subscriber. It exists so {@see \Ayimdomnic\Laragraph\Support\Subscription}
 * — which only has access to the field's own resolve arguments — can hand
 * the channel it resolved via `subscribe()` back out to
 * {@see \Ayimdomnic\Laragraph\Controllers\LaragraphController}, without
 * changing {@see \Ayimdomnic\Laragraph\Laragraph::execute()}'s return shape.
 */
final class SubscriptionRegistrar
{
    private mixed $channel = null;

    public function capture(mixed $channel): void
    {
        $this->channel = $channel;
    }

    public function channel(): mixed
    {
        return $this->channel;
    }
}
