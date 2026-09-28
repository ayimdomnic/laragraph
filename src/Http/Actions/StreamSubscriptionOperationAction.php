<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Ayimdomnic\Laragraph\Contracts\SsePendingQueueInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionRequestHandlerInterface;
use Ayimdomnic\Laragraph\Subscriptions\SseEventLoop;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Registers a subscription operation and streams its updates over the
 * *same* connection — the shape the actual
 * [graphql-sse](https://github.com/enisdenjo/graphql-sse) protocol expects
 * (distinct connections mode): one request, immediately upgraded to an SSE
 * stream, carrying `next`/`complete` frames. See
 * {@see ExecuteQueryAction} for how a
 * request is routed here instead of the pre-existing
 * "register, then GET a separate `streamUrl`" flow — only requests that
 * explicitly ask for it (`Accept: text/event-stream`) ever reach this.
 *
 * Reuses {@see SubscriptionRequestHandlerInterface::register()} verbatim
 * for the authorize/validate/register step, so both flows share the exact
 * same registration logic — this class only changes how the *result* of
 * that step is delivered.
 */
final readonly class StreamSubscriptionOperationAction
{
    public function __construct(
        private SubscriptionRequestHandlerInterface $subscriptions,
        private SsePendingQueueInterface $queue,
        private SseEventLoop $loop,
    ) {}

    /**
     * @param array<string, mixed> $variables
     */
    public function handle(
        string $query,
        array $variables,
        ?string $operationName,
        string $schemaName,
        Request $request,
    ): StreamedResponse {
        $result       = $this->subscriptions->register($query, $variables, $operationName, $schemaName, $request);
        $subscriberId = $result['extensions']['subscription']['subscriberId'] ?? null;

        // Per spec: validation/authorization errors — and a subscription
        // whose field never resolved a channel via subscribe() — stream as
        // a `next` event, not an HTTP error response, followed by
        // `complete` since nothing more will ever be sent.
        if (!empty($result['errors']) || !is_string($subscriberId)) {
            return $this->stream(function () use ($result): void {
                $this->emit('next', $result);
                $this->emit('complete');
            });
        }

        return $this->stream(function () use ($result, $subscriberId): void {
            $this->emit('next', ['data' => $result['data'] ?? null]);
            $this->loop->run($subscriberId, $this->queue);
        });
    }

    private function stream(\Closure $callback): StreamedResponse
    {
        return response()->stream($callback, 200, [
            'Content-Type'      => 'text/event-stream',
            'Cache-Control'     => 'no-cache',
            'Connection'        => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function emit(string $event, array $payload = []): void
    {
        echo "event: {$event}\n";
        // The spec calls for a literal empty `data:` field on `complete`
        // (for EventSource compatibility) rather than omitting it.
        echo 'data: ' . ($event === 'complete' ? '' : json_encode($payload)) . "\n\n";

        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
