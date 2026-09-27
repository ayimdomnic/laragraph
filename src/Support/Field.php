<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use Ayimdomnic\Laragraph\Auth\AuthorizationContext;
use Ayimdomnic\Laragraph\Auth\GuardResolver;
use Ayimdomnic\Laragraph\Exceptions\AuthorizationException;
use Ayimdomnic\Laragraph\Exceptions\ValidationException;
use Ayimdomnic\Laragraph\Middleware\FieldMiddlewareInterface;
use Ayimdomnic\Laragraph\Middleware\FieldMiddlewarePipeline;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Base class for GraphQL query and mutation fields.
 *
 * Extend this in your Query / Mutation classes.
 *
 * ## Authorization
 *
 * Two authorization hooks are available — use whichever fits your needs:
 *
 * ### Simple boolean check (backward-compatible)
 * ```php
 * public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
 * {
 *     return auth()->check(); // or any other boolean expression
 * }
 * ```
 *
 * ### Guard-aware context check (recommended for multi-guard apps)
 * ```php
 * public function authorizeWithContext(AuthorizationContext $ctx): bool
 * {
 *     return $ctx->check() && $ctx->can('viewAny', Post::class);
 * }
 * ```
 *
 * ### Guard selection
 * Override `guards()` to restrict which guards may access this field:
 * ```php
 * public function guards(): array
 * {
 *     return ['sanctum', 'api'];  // first matching guard is used
 * }
 * ```
 *
 * ### Policy shortcut
 * Override `policy()` + `policyAbility()` to delegate to a Laravel policy:
 * ```php
 * public function policy(): ?string  { return PostPolicy::class; }
 * public function policyAbility(): string { return 'viewAny'; }
 * ```
 *
 * ## Validation
 * ```php
 * public function rules(array $args = []): array
 * {
 *     return ['email' => ['required', 'email']];
 * }
 * ```
 *
 * ## Complexity
 * ```php
 * public function complexity(): ?int { return 5; }  // cost for query-complexity limiting
 * ```
 *
 * ## Deprecation
 * ```php
 * public function deprecated(): ?string { return 'Use `newField` instead.'; }
 * ```
 */
abstract class Field
{
    /**
     * The GraphQL return type of this field.
     */
    abstract public function type(): Type;

    /**
     * Resolver — receives the parent value, arguments, shared context, and
     * resolve info.
     *
     * @param array<string, mixed> $args
     */
    abstract public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed;

    /**
     * Declare the field arguments.
     *
     * @return array<string, mixed>
     */
    public function args(): array
    {
        return [];
    }

    /**
     * Human-readable description shown in introspection.
     */
    public function description(): ?string
    {
        return null;
    }

    /**
     * Laravel validation rules applied to $args before the resolver is called.
     * Return an empty array to skip validation.
     *
     * @return array<string, mixed>
     * @param array<string, mixed> $args
     */
    public function rules(array $args = []): array
    {
        $formRequest = $this->formRequest();

        if ($formRequest === null) {
            return [];
        }

        return $this->callFormRequest($formRequest, 'rules', $args);
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $formRequest = $this->formRequest();

        if ($formRequest === null) {
            return [];
        }

        return $this->callFormRequest($formRequest, 'messages');
    }

    /**
     * Custom attribute names for validation messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $formRequest = $this->formRequest();

        if ($formRequest === null) {
            return [];
        }

        return $this->callFormRequest($formRequest, 'attributes');
    }

    /**
     * Reuse an existing FormRequest's `rules()` / `messages()` / `attributes()`
     * instead of defining them on the field directly. Return null (default) to
     * keep defining those methods here.
     *
     * Only these three methods are reused — {@see authorize()} is never called
     * on the FormRequest, since it typically inspects `$this->user()` or
     * `$this->route()`, neither of which apply to a GraphQL field. Keep
     * authorization on the field's own `authorize()` / `authorizeWithContext()`.
     *
     * For the same reason, `$this->route()` and `$this->user()` are
     * unsupported inside the reused methods — only `$this->input()` /
     * `$this->all()` are populated, from this field's GraphQL arguments:
     *
     * ```php
     * protected function formRequest(): ?string
     * {
     *     return RegisterRequest::class;
     * }
     * ```
     *
     * @return class-string<FormRequest>|null
     */
    protected function formRequest(): ?string
    {
        return null;
    }

    /**
     * Call a FormRequest method without resolving it through the container.
     *
     * Laravel's `FormRequestServiceProvider` hooks container resolution of
     * every `FormRequest` to copy in the current HTTP request, then
     * immediately calls `authorize()` and validates it — against the raw
     * GraphQL request body, not this field's arguments, and before we ever
     * get a chance to call `rules()` ourselves. Building the instance with
     * `new` instead of `app()->make()` sidesteps that hook entirely; only
     * `setContainer()` is applied afterwards, so a `rules()`/`messages()`/
     * `attributes()` body may still resolve container bindings if it needs
     * to.
     *
     * @param class-string<FormRequest> $formRequest
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    private function callFormRequest(string $formRequest, string $method, array $args = []): array
    {
        $request = new $formRequest();
        $request->setContainer(app());

        if ($args !== []) {
            $request->merge($args);
        }

        if ($method === 'rules') {
            return method_exists($request, 'rules') ? (array) app()->call([$request, 'rules']) : [];
        }

        return match ($method) {
            'messages'   => (array) app()->call($request->messages(...)),
            'attributes' => (array) app()->call($request->attributes(...)),
            default      => [],
        };
    }

    /**
     * Simple boolean authorization gate (backward-compatible).
     *
     * Return `false` to throw an {@see AuthorizationException}.
     * For guard-aware checks, override {@see authorizeWithContext()} instead.
     *
     * @param array<string, mixed> $args
     */
    public function authorize(mixed $root, array $args, mixed $context, ResolveInfo $info): bool
    {
        return true;
    }

    /**
     * Guard-aware authorization gate.
     *
     * This is called in addition to {@see authorize()} when it is overridden.
     * The {@see AuthorizationContext} carries the resolved guard, the current
     * user, and a `can()` helper for policy checks.
     *
     * Example:
     *   public function authorizeWithContext(AuthorizationContext $ctx): bool
     *   {
     *       return $ctx->check() && $ctx->can('viewAny', Post::class);
     *   }
     */
    public function authorizeWithContext(AuthorizationContext $ctx): bool
    {
        return true;
    }

    /**
     * Guard names that must authenticate the request for this field.
     *
     * Return an empty array (default) to use `laragraph.auth.default_guard`.
     *
     * Example — require Sanctum for this field:
     *   public function guards(): array { return ['sanctum']; }
     *
     * @return array<string>
     */
    public function guards(): array
    {
        return [];
    }

    /**
     * Optional Laravel policy to authorize against: the policy class, or the
     * model class it guards.
     *
     * When set, `policyAbility()` is checked before the resolver is called
     * (see {@see AuthorizationContext::allowsPolicy()}).
     *
     * Example:
     *   public function policy(): ?string { return PostPolicy::class; }  // or Post::class
     */
    public function policy(): ?string
    {
        return null;
    }

    /**
     * The policy ability to check (default: `'view'`).
     *
     * Only relevant when {@see policy()} returns a non-null value.
     */
    public function policyAbility(): string
    {
        return 'view';
    }

    /**
     * Middleware stack applied around this field's resolver, after auth and
     * validation have already passed.
     *
     * Return class name strings (resolved via the container) or instances:
     * ```php
     * public function middleware(): array
     * {
     *     return [
     *         new \Ayimdomnic\Laragraph\Middleware\ThrottleMiddleware(maxAttempts: 10),
     *         \App\GraphQL\Middleware\AuditMiddleware::class,
     *     ];
     * }
     * ```
     *
     * Global middleware declared in `laragraph.middleware` runs before
     * per-field middleware.
     *
     * @return array<string|FieldMiddlewareInterface>
     */
    public function middleware(): array
    {
        return [];
    }

    /**
     * Optional: an integer representing the query-complexity cost of this field.
     *
     * Used by the QueryComplexity validation rule when
     * `laragraph.security.query_max_complexity` is set.
     *
     * Example:
     *   public function complexity(): ?int { return 5; }
     */
    public function complexity(): ?int
    {
        return null;
    }

    /**
     * Mark this field as deprecated.
     *
     * Return a non-null string to include a deprecation reason in the schema.
     * The reason will be visible in introspection and GraphQL tools.
     *
     * Example:
     *   public function deprecated(): ?string { return 'Use `newField` instead.'; }
     */
    public function deprecated(): ?string
    {
        return null;
    }

    /**
     * Compile this field into a GraphQL field definition array ready to be
     * placed inside an ObjectType's fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $definition = [
            'type'        => $this->type(),
            'args'        => $this->args(),
            'description' => $this->description(),
            'resolve'     => $this->buildResolver(),
        ];

        if ($this->deprecated() !== null) {
            $definition['deprecationReason'] = $this->deprecated();
        }

        return $definition;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function buildResolver(): \Closure
    {
        return function (mixed $root, array $args, mixed $context, ResolveInfo $info): mixed {
            // 1. Simple boolean authorization (backward-compatible)
            if (!$this->authorize($root, $args, $context, $info)) {
                throw new AuthorizationException(
                    trans('laragraph::errors.authorization.field', ['field' => class_basename(static::class)]),
                );
            }

            // 2. Guard-aware context authorization
            $ctx = GuardResolver::buildContext($this->authenticatedGuard());

            if (!$this->authorizeWithContext($ctx)) {
                throw new AuthorizationException(
                    trans('laragraph::errors.authorization.field', ['field' => class_basename(static::class)]),
                );
            }

            // 3. Policy check
            $policy = $this->policy();
            if ($policy !== null) {
                if (!$ctx->allowsPolicy($policy, $this->policyAbility())) {
                    throw new AuthorizationException(
                        trans('laragraph::errors.authorization.policy', ['field' => class_basename(static::class)]),
                    );
                }
            }

            // 4. Validate arguments
            $rules = $this->rules($args);
            if ($rules !== []) {
                $validator = Validator::make($args, $rules, $this->messages(), $this->attributes());
                if ($validator->fails()) {
                    throw new ValidationException($validator);
                }
            }

            // 5. Middleware pipeline (global + per-field), then resolve
            $middleware = $this->resolveMiddlewareInstances(
                array_merge(
                    (array) config('laragraph.middleware', []),
                    $this->middleware(),
                ),
            );

            if ($middleware !== []) {
                return (new FieldMiddlewarePipeline($middleware))
                    ->run($root, $args, $context, $info, fn($r, array $a, $c, ResolveInfo $i): mixed => $this->handleField($r, $a, $c, $i));
            }

            // 6. Resolve (no middleware)
            return $this->handleField($root, $args, $context, $info);
        };
    }

    /**
     * The first of {@see guards()} that authenticates the current request —
     * or, when none does, the first one listed (so checks run, and fail, as
     * a guest of that guard). Null when no guards are declared.
     */
    protected function authenticatedGuard(): ?string
    {
        $guards = $this->guards();

        foreach ($guards as $guard) {
            if (auth()->guard($guard)->check()) {
                return $guard;
            }
        }

        return $guards[0] ?? null;
    }

    /**
     * The final step of the resolver pipeline, after auth/validation/middleware
     * have all passed. Defaults to calling {@see resolve()} — overridden by
     * {@see Subscription} to branch between registering a subscriber and
     * resolving a live update, without duplicating the pipeline above.
     *
     * @param array<string, mixed> $args
     */
    protected function handleField(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
    {
        return $this->resolve($root, $args, $context, $info);
    }

    /**
     * Resolve middleware entries: string class names are resolved through the
     * container; instances are passed through as-is.
     *
     * @param  array<string|FieldMiddlewareInterface> $middleware
     * @return list<FieldMiddlewareInterface>
     */
    private function resolveMiddlewareInstances(array $middleware): array
    {
        return array_values(array_map(
            fn(string|FieldMiddlewareInterface $mw): FieldMiddlewareInterface => is_string($mw) ? app($mw) : $mw,
            $middleware,
        ));
    }
}
