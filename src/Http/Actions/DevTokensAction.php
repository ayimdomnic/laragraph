<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;

/**
 * Serve Sanctum tokens for the configured GraphiQL test users.
 *
 * Development-only: the route is registered when app.env is not
 * 'production', and this action re-checks the environment at runtime — so
 * tokens are refused even if the route was registered while the app booted
 * in another environment (cached config, force-deployed routes).
 */
final class DevTokensAction
{
    public function handle(): JsonResponse
    {
        // config() rather than app()->environment(): the detector is frozen
        // at boot, and a stale route surviving into a production config
        // still has to refuse.
        if (config('app.env') === 'production') {
            abort(404);
        }

        $users = $this->resolveUsers();

        return response()->json([
            'tokens' => $users,
            'note'   => $this->note($users),
        ]);
    }

    /**
     * Resolve each configured test user and mint a fresh token when the
     * model supports Sanctum personal access tokens.
     *
     * @return list<array{role: string, identifier: string, user_id: int|string, token: string|null}>
     */
    private function resolveUsers(): array
    {
        $model   = $this->authUserModel();
        $entries = [];

        if ($model === null) {
            return $entries;
        }

        foreach ((array) config('laragraph.graphiql.test_users', []) as $role => $identifier) {
            if (!is_string($identifier) || $identifier === '') {
                continue;
            }

            // Eloquent models expose their primary key as the auth identifier
            // name — but the lookup key is whatever `getAuthIdentifierName()`
            // reports, i.e. typically `email` for the shipped fixtures.
            $column = match ($model->getAuthIdentifierName()) {
                'id'    => 'email',
                default => $model->getAuthIdentifierName(),
            };

            try {
                $user = $model->newQuery()->where($column, $identifier)->first();
            } catch (QueryException) {
                // Unqueryable provider (missing table/connection): treat the
                // fixture as unresolved rather than failing the whole feed.
                continue;
            }

            if (!$user instanceof Authenticatable) {
                continue;
            }

            $token = null;

            if (method_exists($user, 'createToken')) {
                // Dev-only fixture tokens: no ability restrictions, and old
                // ones are pruned so repeated picker refreshes don't pile up.
                if (method_exists($user, 'tokens')) {
                    $user->tokens()->delete();
                }

                $token = (string) $user->createToken("graphiql:{$role}")->plainTextToken;
            }

            $entries[] = [
                'role'       => (string) $role,
                'identifier' => $identifier,
                'user_id'    => $user->getAuthIdentifier(),
                'token'      => $token,
            ];
        }

        return $entries;
    }

    /**
     * The application's configurable user model, or null when the default
     * auth provider isn't an Eloquent model that can authenticate.
     *
     * @return (Model&Authenticatable)|null
     */
    private function authUserModel(): ?Model
    {
        $provider = config('auth.providers.' . config('auth.defaults.provider', 'users'));

        if (!is_array($provider) || ($provider['model'] ?? null) === null) {
            return null;
        }

        $class = (string) $provider['model'];

        if (!class_exists($class) || !is_subclass_of($class, Model::class) || !is_subclass_of($class, Authenticatable::class)) {
            return null;
        }

        /** @var Model&Authenticatable $model */
        $model = new $class();

        return $model;
    }

    /**
     * Explain an empty or tokenless result so the IDE can show it instead of
     * failing silently.
     *
     * @param list<array{role: string, identifier: string, user_id: int|string, token: string|null}> $users
     */
    private function note(array $users): ?string
    {
        if ($users === []) {
            return 'No configured test users matched. Seed them or adjust laragraph.graphiql.test_users.';
        }

        if (array_all($users, static fn(array $u): bool => $u['token'] === null)) {
            return 'The user model does not use Laravel Sanctum\'s HasApiTokens trait; only session-cookie auth is available.';
        }

        return null;
    }
}
