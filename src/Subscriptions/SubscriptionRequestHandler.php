<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Contracts\QueryExecutorInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionRequestHandlerInterface;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Support\Operation;
use Ayimdomnic\Laragraph\Support\Subscription;
use Illuminate\Http\Request;

/**
 * Detects a subscription operation and registers a new subscriber for it,
 * instead of executing it normally.
 *
 * Runs the query through the normal auth/validation/middleware pipeline (via
 * {@see QueryExecutorInterface::execute()}, with `subscribing` flagged on the
 * context) so a subscription request is authorized exactly like any other
 * field, but the field's `handleField()` calls `subscribe()` rather than
 * `resolve()` — see {@see Subscription}.
 */
final readonly class SubscriptionRequestHandler implements SubscriptionRequestHandlerInterface
{
    public function __construct(
        private QueryExecutorInterface $executor,
        private SubscriptionManagerInterface $subscriptions,
    ) {}

    /**
     * webonyx/graphql-php has no dedicated subscription-execution entrypoint
     * — Laragraph must recognise the operation type itself, before deciding
     * whether to execute normally or register a new subscriber.
     */
    public function isSubscriptionOperation(string $query, ?string $operationName): bool
    {
        return Operation::isSubscription($query, $operationName);
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function register(
        string $query,
        array $variables,
        ?string $operationName,
        string $schemaName,
        Request $request,
    ): array {
        if (!config('laragraph.subscriptions.enabled', false)) {
            return ['errors' => [[
                'message' => 'Subscriptions are disabled. Enable via the laragraph.subscriptions.enabled config.',
            ]]];
        }

        $registrar = new SubscriptionRegistrar();

        $context                        = GraphQLContext::fromRequest($request);
        $context->subscribing           = true;
        $context->subscriptionRegistrar = $registrar;

        $result = $this->executor->execute($query, $context, $variables, $operationName, $schemaName);

        if (!empty($result['errors'])) {
            return $result;
        }

        $channel = $registrar->channel();

        if ($channel === null) {
            return ['errors' => [[
                'message' => 'The subscription field did not resolve a channel via subscribe().',
            ]]];
        }

        $subscriberId = $this->subscriptions->register($channel, [
            'query'         => $query,
            'variables'     => $variables,
            'operationName' => $operationName,
            'schemaName'    => $schemaName,
        ]);

        $subscription = ['channel' => $channel, 'subscriberId' => $subscriberId];

        if (config('laragraph.subscriptions.driver', 'broadcast') === 'sse') {
            $subscription['streamUrl'] = route('laragraph.subscriptions.stream', $subscriberId);
        }

        return [
            'data'       => $result['data'] ?? null,
            'extensions' => array_merge($result['extensions'] ?? [], ['subscription' => $subscription]),
        ];
    }
}
