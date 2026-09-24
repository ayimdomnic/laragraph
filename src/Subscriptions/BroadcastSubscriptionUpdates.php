<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Laragraph;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued fan-out of a subscription event: re-runs every subscriber's query
 * on the queue instead of inside the request that triggered the event.
 *
 * Dispatched by {@see Laragraph::broadcastLater()}.
 */
final class BroadcastSubscriptionUpdates implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $channel,
        public readonly mixed $payload = null,
    ) {}

    public function handle(SubscriptionManager $subscriptions): void
    {
        $subscriptions->broadcast($this->channel, $this->payload);
    }
}
