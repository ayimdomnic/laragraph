<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Http\Actions;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Response;

/**
 * Serve the GraphiQL browser IDE.
 */
final class RenderGraphiqlAction
{
    public function handle(string $schemaName = 'default'): Response
    {
        $prefix   = (string) config('laragraph.route.prefix', 'graphql');
        $endpoint = url($prefix . '/' . ($schemaName !== 'default' ? $schemaName : ''));

        // null = automatic: the picker (and its dev-tokens endpoint) follows
        // the environment, never appearing under APP_ENV=production.
        $env          = (string) config('app.env', 'production');
        $picker       = (bool) (config('laragraph.graphiql.auth_picker') ?? $env !== 'production');
        $isProduction = $env === 'production';

        return response()
            ->view('laragraph::graphiql', [
                'endpoint'    => rtrim($endpoint, '/'),
                'title'       => config('laragraph.graphiql.title', 'Laragraph — GraphiQL'),
                'environment' => $env,
                // GraphiQL in production is a security risk; the IDE banners
                // whenever it is served under APP_ENV=production.
                'prodWarning' => $isProduction,
                'authPicker'  => $picker && !$isProduction,
                'tokensUrl'   => $picker && !$isProduction ? url($prefix . '/graphiql/dev-tokens') : null,
                'sessionUser' => ($user = $this->sessionUser()) === null ? null : $this->describeUser($user),
            ]);
    }

    /**
     * The user the current request is authenticated as via the application's
     * default (session-backed) guard — the identity same-origin cookie auth
     * rides, so the IDE can name it without a `me { id }` round-trip.
     *
     * A route outside the session middleware group has no store attached, so
     * a session guard that hasn't already resolved a user throws when read;
     * that just means "no visible session identity" and yields null.
     */
    private function sessionUser(): ?Authenticatable
    {
        try {
            return auth()->guard(config('laragraph.auth.default_guard'))->user();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A display label for the logged-in user, so the picker can show *who*
     * session-cookie auth would act as without running a `me { id }` query.
     */
    private function describeUser(Authenticatable $user): string
    {
        foreach (['name', 'email', 'username'] as $attribute) {
            if (isset($user->{$attribute}) && is_string($user->{$attribute}) && $user->{$attribute} !== '') {
                return $user->{$attribute};
            }
        }

        return 'user #' . $user->getAuthIdentifier();
    }
}
