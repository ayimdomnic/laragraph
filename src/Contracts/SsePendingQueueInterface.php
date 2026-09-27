<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Subscriptions\SsePendingQueue;

/**
 * A per-subscriber queue of pending SSE payloads.
 *
 * @see SsePendingQueue the built-in implementation.
 */
interface SsePendingQueueInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function push(string $subscriberId, array $payload): void;

    /**
     * @return array<string, mixed>|null
     */
    public function pop(string $subscriberId): ?array;
}
