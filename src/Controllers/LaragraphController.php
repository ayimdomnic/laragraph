<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Controllers;

use Ayimdomnic\Laragraph\Http\Actions\ExecuteQueryAction;
use Ayimdomnic\Laragraph\Http\Actions\RenderGraphiqlAction;
use Ayimdomnic\Laragraph\Http\Actions\StreamSubscriptionAction;
use Ayimdomnic\Laragraph\Http\Actions\UnsubscribeAction;
use Ayimdomnic\Laragraph\Http\ResponseNegotiator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller as BaseController;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
class LaragraphController extends BaseController
{
    /** The GraphQL-over-HTTP response media type. */
    public const GRAPHQL_RESPONSE_MEDIA_TYPE = ResponseNegotiator::GRAPHQL_RESPONSE_MEDIA_TYPE;

    public function __construct(
        protected readonly ExecuteQueryAction $executeQueryAction,
        protected readonly UnsubscribeAction $unsubscribeAction,
        protected readonly StreamSubscriptionAction $streamSubscriptionAction,
        protected readonly RenderGraphiqlAction $renderGraphiqlAction,
    ) {}

    /**
     * Execute a GraphQL query / mutation.
     *
     * Follows the GraphQL-over-HTTP specification: clients that send
     * `Accept: application/graphql-response+json` receive that media type and
     * a 4xx status whenever the request fails before execution; others get
     * `application/json` with the traditional always-200 behaviour.
     */
    public function query(Request $request, string $schemaName = 'default'): JsonResponse
    {
        return $this->executeQueryAction->handle($request, $schemaName);
    }

    /**
     * Cancel a subscription. Only its owner may do so; unknown subscriptions
     * and those owned by someone else both answer 404, so ids cannot be probed.
     */
    public function unsubscribe(string $subscriberId): Response|JsonResponse
    {
        return $this->unsubscribeAction->handle($subscriberId);
    }

    /**
     * Stream a subscriber's updates over Server-Sent Events — only available
     * when `laragraph.subscriptions.driver` is `'sse'`. Each connection
     * self-closes after `sse.max_duration` seconds; the client's `EventSource`
     * reconnects transparently. See docs/07-subscriptions.md for the capacity
     * trade-offs of this transport before relying on it for many subscribers.
     */
    public function stream(string $subscriberId): StreamedResponse|JsonResponse
    {
        return $this->streamSubscriptionAction->handle($subscriberId);
    }

    /**
     * Serve the GraphiQL browser IDE.
     */
    public function graphiql(Request $request, string $schemaName = 'default'): Response
    {
        return $this->renderGraphiqlAction->handle($schemaName);
    }
}
