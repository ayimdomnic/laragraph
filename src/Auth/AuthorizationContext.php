<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Auth;

use Illuminate\Container\Container;
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
        $this->resolvedGuard ??= auth()->guard($this->guardName);

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

        if (!$user instanceof Authenticatable) {
            return false;
        }

        return app(Gate::class)->forUser($user)->check($ability, $arguments);
    }

    /**
     * Check $ability on a policy, given either the policy class or the model
     * class it guards.
     *
     * - A model class with a registered (or auto-discovered) policy, and a
     *   policy class registered with the Gate, go through the Gate — so
     *   Gate::before() hooks and the policy's own before() apply.
     * - Any other policy class is resolved from the container and called
     *   directly (honouring its before() method).
     * - A name that is not a class is passed to the Gate as the ability's
     *   argument (backwards compatible with Gate::define() abilities).
     *
     * Guests are denied unless the policy method accepts a nullable user.
     *
     * @param string $policyOrModel Policy or model class name.
     */
    public function allowsPolicy(string $policyOrModel, string $ability): bool
    {
        $gate = app(Gate::class);

        if ($gate->getPolicyFor($policyOrModel) !== null) {
            return $this->can($ability, $policyOrModel);
        }

        $model = array_search($policyOrModel, $gate->policies(), true);

        if (is_string($model)) {
            return $this->can($ability, $model);
        }

        // Not a class at all: keep treating it as a Gate ability argument, as
        // earlier versions did (e.g. with an ability defined via Gate::define()).
        if (!class_exists($policyOrModel)) {
            return $this->can($ability, $policyOrModel);
        }

        $policy = Container::getInstance()->make($policyOrModel);
        $user   = $this->user();

        if (is_object($policy) && method_exists($policy, 'before')) {
            $result = $policy->before($user, $ability);

            if ($result !== null) {
                return (bool) $result;
            }
        }

        if (!method_exists($policy, $ability)) {
            return false;
        }

        // Like the Gate: guests only reach methods whose user parameter is nullable.
        $userParameter = (new \ReflectionMethod($policy, $ability))->getParameters()[0] ?? null;

        if (!$user instanceof Authenticatable && $userParameter?->allowsNull() !== true) {
            return false;
        }

        return (bool) $policy->{$ability}($user);
    }
}
