<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Contracts\SsePendingQueueInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Ayimdomnic\Laragraph\Http\Actions\StreamSubscriptionAction;
use Ayimdomnic\Laragraph\Http\Actions\StreamSubscriptionOperationAction;

/**
 * The SSE polling loop shared by {@see StreamSubscriptionAction}
 * (the pre-existing, bespoke "register then poll a separate stream URL" flow) and
 * {@see StreamSubscriptionOperationAction} (the
 * graphql-sse-compliant "one request is the stream" flow) — both push
 * `event: next` frames from the same {@see SsePendingQueueInterface}, so the
 * loop itself only needed extracting once.
 */
final readonly class SseEventLoop
{
    public function __construct(private SubscriptionManagerInterface $subscriptions) {}

    /**
     * Poll $queue for $subscriberId's pending payloads and echo each as an
     * `event: next` SSE frame, with periodic heartbeats, until
     * `sse.max_duration` elapses or the client disconnects.
     *
     * Never emits `event: complete` on the `max_duration` bound — that's a
     * capacity bound, not the end of the subscription, and per the
     * graphql-sse spec an unclean disconnect is precisely the client's cue
     * to reconnect by re-sending the same operation. On a *clean*,
     * client-initiated disconnect, the subscriber is unsubscribed
     * immediately instead of being left to expire via
     * `laragraph.subscriptions.ttl`.
     */
    public function run(string $subscriberId, SsePendingQueueInterface $queue): void
    {
        $maxDuration      = (int) config('laragraph.subscriptions.sse.max_duration', 30);
        $pollIntervalUs   = (int) config('laragraph.subscriptions.sse.poll_interval_ms', 500) * 1000;
        $heartbeatSeconds = (int) config('laragraph.subscriptions.sse.heartbeat_seconds', 15);

        $deadline      = microtime(true) + $maxDuration;
        $lastHeartbeat = microtime(true);

        while (microtime(true) < $deadline) {
            if (connection_aborted()) {
                $this->subscriptions->unsubscribe($subscriberId);

                break;
            }

            $payload = $queue->pop($subscriberId);

            if ($payload !== null) {
                echo "event: next\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                $lastHeartbeat = microtime(true);
            } elseif (microtime(true) - $lastHeartbeat >= $heartbeatSeconds) {
                echo ": heartbeat\n\n";
                $lastHeartbeat = microtime(true);
            } else {
                usleep($pollIntervalUs);

                continue;
            }

            if (ob_get_level() > 0) {
                ob_flush();
            }

            flush();
        }
    }
}
