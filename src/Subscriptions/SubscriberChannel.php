<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorizes the private channel (`graphql-subscriber.{subscriberId}`) that
 * subscription updates are broadcast on: only the user who created the
 * subscription may listen to it.
 *
 * Registered automatically for the configured channel prefix; disable with
 * `laragraph.subscriptions.authorize_channel` to provide your own rule in
 * routes/channels.php.
 */
final readonly class SubscriberChannel
{
    public function __construct(private SubscriberStoreInterface $store) {}

    public function join(Authenticatable $user, string $subscriberId): bool
    {
        if (!$this->store instanceof FindsSubscribers) {
            return false;
        }

        $owner = $this->store->find($subscriberId)['auth'] ?? null;

        if ($owner === null || $owner['id'] === null) {
            return false;
        }

        $type = $owner['type'] ?? null;

        return (string) $owner['id'] === (string) $user->getAuthIdentifier()
            && ($type === null || $user::class === $type);
    }
}
