<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

/**
 * A subscriber store that can look a subscriber up by id.
 *
 * Needed to authorize the private broadcast channel a subscriber listens on
 * ({@see SubscriberChannel}). Implement it on custom stores; without it,
 * joining a subscriber channel is always denied.
 *
 * @phpstan-import-type SubscriberRecord from SubscriberStoreInterface
 */
interface FindsSubscribers
{
    /**
     * @return SubscriberRecord|null
     */
    public function find(string $subscriberId): ?array;
}
