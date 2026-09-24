<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Subscriptions;

use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Facade;

/**
 * Runs a subscriber's query as *that subscriber*.
 *
 * Laragraph::broadcast() is called from whatever code triggered the event —
 * usually another user's mutation, or a queued job. Re-executing subscriber
 * queries there would evaluate every authorize()/policy()/auth() check as the
 * broadcaster, pushing data to subscribers that they may not see.
 *
 * For the duration of the callback this swaps in:
 *  - an empty request and an empty session, so no guard can fall back to the
 *    broadcaster's token, cookies or session;
 *  - a clone of the auth manager (custom guards such as Sanctum's survive)
 *    with no resolved guards, logged in as the subscriber on the guard they
 *    subscribed through — or as a guest.
 *
 * Everything is restored afterwards, even if the callback throws.
 *
 * @phpstan-type SubscriberIdentity array{guard: string, id: int|string|null, type?: class-string|null}
 */
final readonly class SubscriberSandbox
{
    private const SWAPPED = ['request', 'auth', 'session.store'];

    public function __construct(private Application $app) {}

    /**
     * The identity of the user making the current request, to be stored with a subscriber.
     *
     * @return SubscriberIdentity
     */
    public function currentIdentity(): array
    {
        $guard = (string) (config('laragraph.auth.default_guard') ?? $this->auth()->getDefaultDriver());
        $user  = $this->auth()->guard($guard)->user();

        return [
            'guard' => $guard,
            'id'    => $user?->getAuthIdentifier(),
            'type'  => $user === null ? null : $user::class,
        ];
    }

    /**
     * Run $callback authenticated as the subscriber described by $identity.
     *
     * Returns null without calling $callback when the subscriber's user no
     * longer exists.
     *
     * @template TReturn
     * @param SubscriberIdentity|null              $identity null for guest subscribers (and records stored before identities existed)
     * @param \Closure(GraphQLContext): TReturn $callback
     * @return TReturn|null
     */
    public function run(?array $identity, \Closure $callback): mixed
    {
        $original      = [];
        $originalGuard = config('auth.defaults.guard');

        foreach (self::SWAPPED as $abstract) {
            if ($this->app->bound($abstract)) {
                $original[$abstract] = $this->app->make($abstract);
            }
        }

        $request = GraphQLContext::create('/', 'POST');
        $auth    = clone $this->auth();
        $auth->forgetGuards();
        $auth->resolveUsersUsing(static fn(?string $guard = null): ?Authenticatable => $auth->guard($guard)->user());

        $isolated = ['request' => $request, 'auth' => $auth];

        if (isset($original['session.store'])) {
            $isolated['session.store'] = new Store('laragraph-subscriber', new ArraySessionHandler(0));
        }

        $this->swap($isolated);

        try {
            if ($identity !== null && $identity['id'] !== null) {
                $user = $this->findUser($auth, $identity);

                if (!$user instanceof Authenticatable) {
                    return null;
                }

                $auth->shouldUse($identity['guard']);
                $auth->guard($identity['guard'])->setUser($user);
            }

            $request->setUserResolver(static fn(?string $guard = null): ?Authenticatable => $auth->guard($guard)->user());

            return $callback($request);
        } finally {
            config(['auth.defaults.guard' => $originalGuard]);
            $this->swap($original);
        }
    }

    /**
     * @param SubscriberIdentity $identity
     */
    private function findUser(AuthManager $auth, array $identity): ?Authenticatable
    {
        $provider = $auth->createUserProvider(config("auth.guards.{$identity['guard']}.provider"));
        $user     = $provider?->retrieveById($identity['id']);

        if ($user === null) {
            return null;
        }

        // Never authenticate as a different kind of user that happens to share the id.
        $type = $identity['type'] ?? null;

        return $type === null || $user::class === $type ? $user : null;
    }

    /**
     * @param array<string, object> $instances
     */
    private function swap(array $instances): void
    {
        foreach ($instances as $abstract => $instance) {
            $this->app->instance($abstract, $instance);
            Facade::clearResolvedInstance($abstract);
        }
    }

    private function auth(): AuthManager
    {
        return $this->app->make('auth');
    }
}
