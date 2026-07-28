<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Auth;

/**
 * Resolves the guard name to use for a given field execution.
 *
 * Priority (highest → lowest):
 *   1. Explicitly passed guard name (from per-field `guards()` or schema config)
 *   2. Package-level `laragraph.auth.default_guard` config
 *   3. `null` → Laravel's own default guard
 *
 * Example:
 *
 *   $guard = GuardResolver::resolve('sanctum');  // → 'sanctum'
 *   $guard = GuardResolver::resolve(null);        // → config default or null
 */
final class GuardResolver
{
    /**
     * Resolve the effective guard name.
     *
     * @param  string|null  $fieldGuard  Guard requested by the field (if any).
     */
    public static function resolve(?string $fieldGuard = null): ?string
    {
        return $fieldGuard
            ?? config('laragraph.auth.default_guard')
            ?? null;
    }

    /**
     * Build an AuthorizationContext for the current HTTP request, using the
     * resolved guard.
     *
     * @param  string|null  $fieldGuard  Guard requested by the field (if any).
     */
    public static function buildContext(?string $fieldGuard = null): AuthorizationContext
    {
        return new AuthorizationContext(
            request: request(),
            guardName: static::resolve($fieldGuard),
        );
    }
}
