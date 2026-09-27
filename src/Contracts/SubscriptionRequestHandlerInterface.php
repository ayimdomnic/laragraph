<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Subscriptions\SubscriptionRequestHandler;
use Illuminate\Http\Request;

/**
 * Detects a subscription operation and registers a new subscriber for it,
 * instead of executing it normally.
 *
 * @see SubscriptionRequestHandler the built-in implementation.
 */
interface SubscriptionRequestHandlerInterface
{
    /**
     * Whether $query's (matching) operation is a `subscription`.
     */
    public function isSubscriptionOperation(string $query, ?string $operationName): bool;

    /**
     * Register a new subscriber instead of executing normally.
     *
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function register(
        string $query,
        array $variables,
        ?string $operationName,
        string $schemaName,
        Request $request,
    ): array;
}
