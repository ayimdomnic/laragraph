<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Auth;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Http\Request;

/**
 * Immutable value object passed into every field's `authorizeWithContext()` call.
 *
 * It resolves the active guard on first use and exposes a clean API for
 * policy / ability checks without coupling your field classes to the Auth facade.
 *
 * Example inside a Field class:
 *
 *   public function authorizeWithContext(AuthorizationContext $ctx): bool
 *   {
 *       return $ctx->check() && $ctx->can('viewAny', Post::class);
 *   }
 */
final class AuthorizationContext
{
    private ?Guard $resolvedGuard = null;

    public function __construct(
        private readonly Request $request,
        private readonly ?string $guardName = null,
    ) {}

    /**
     * The originating HTTP request.
     */
    public function request(): Request
    {
        return $this->request;
    }

    /**
     * The name of the guard being used (null = Laravel default).
     */
    public function guardName(): ?string
    {
        return $this->guardName;
    }

    /**
     * The resolved Guard instance.
     */
    public function guard(): Guard
    {
        if ($this->resolvedGuard === null) {
            $this->resolvedGuard = auth()->guard($this->guardName);
        }

        return $this->resolvedGuard;
    }

    /**
     * Whether the current user is authenticated via the active guard.
     */
    public function check(): bool
    {
        return $this->guard()->check();
    }

    /**
     * The currently authenticated user, or null if a guest.
     */
    public function user(): ?Authenticatable
    {
        return $this->guard()->user();
    }

    /**
     * Check a policy ability against an optional model.
     *
     * @param  string|object|array<mixed>  $arguments
     */
    public function can(string $ability, mixed $arguments = []): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return app(Gate::class)->forUser($user)->check($ability, $arguments);
    }
}
