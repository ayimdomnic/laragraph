<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Cancel a subscription. Only its owner may do so; unknown subscriptions
 * and those owned by someone else both answer 404, so ids cannot be probed.
 */
final readonly class UnsubscribeAction
{
    public function __construct(private SubscriptionManagerInterface $subscriptions) {}

    public function handle(string $subscriberId): Response|JsonResponse
    {
        if (!config('laragraph.subscriptions.enabled', false) || !$this->subscriptions->ownedByCurrentUser($subscriberId)) {
            return response()->json(['errors' => [[
                'message'    => 'Subscription not found.',
                'extensions' => ['code' => 'SUBSCRIPTION_NOT_FOUND'],
            ]]], 404);
        }

        $this->subscriptions->unsubscribe($subscriberId);

        return response()->noContent();
    }
}
