<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http;

use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionRegistrar;
use Illuminate\Http\Request;

/**
 * The execution context resolvers receive for HTTP-originated operations.
 *
 * It is a full {@see Request} — input, headers, files, `user()`, the session
 * and route all behave exactly as on the incoming request — with *declared*
 * slots for Laragraph's per-execution state. Earlier versions attached that
 * state to the framework's Request as dynamic properties, which PHP 8.2
 * deprecates (and PHP 9 turns into an error).
 *
 * Because the slots are declared, `$context->dataLoaders` keeps working in
 * resolvers, and client input named `subscribing` can no longer shadow the
 * subscription flag through Request's `__get()` input fallback.
 */
class GraphQLContext extends Request
{
    /** Per-execution DataLoader registry, attached by Laragraph::executeQuery(). */
    public ?DataLoaderRegistry $dataLoaders = null;

    /** True only while a subscription operation is registering its subscriber. */
    public bool $subscribing = false;

    /** Captures the channel a subscription field resolves while registering. */
    public ?SubscriptionRegistrar $subscriptionRegistrar = null;

    /**
     * Wrap an incoming request, or return it untouched when it already is a context.
     */
    public static function fromRequest(Request $request): self
    {
        return $request instanceof self ? $request : self::createFrom($request);
    }
}
