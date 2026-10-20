<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Auth;

use Ayimdomnic\Laragraph\Support\DefaultFieldResolver;
use Ayimdomnic\Laragraph\Support\Type;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Evaluates declarative field-level `canSee` visibility rules at resolve time.
 *
 * A field that fails its `canSee` rule is redacted — it resolves to `null`,
 * never an error — so a denied value is indistinguishable from an empty one
 * while the field's *existence* stays honest in introspection. Non-nullable
 * fields follow GraphQL's standard null propagation, bubbling up to the
 * nearest nullable parent.
 *
 * Three rule shapes are accepted (declared on fields returned by
 * {@see Type::fields()}, or via
 * {@see Type::guard()}):
 *
 *  - **String** — an ability checked against the parent: `'canSee' => 'viewPhone'`
 *    runs `Gate::allows('viewPhone', $parent)`.
 *  - **Array** — `[ability, subjectResolver]`; the second element returns the
 *    object to authorize against, for when the ability targets a related model
 *    rather than the parent. A non-callable second element is the subject itself.
 *  - **Closure** — `fn ($parent, $args, $context) => bool`, evaluated as-is.
 *
 * Guests and unknown abilities fail closed (redacted); an unknown ability logs
 * one warning per request so a forgotten policy method is caught during
 * development without leaking anything to the client. Ability-based checks are
 * memoised per execution context, so repeated `(ability, subject)` pairs never
 * re-hit the Gate.
 */
final class FieldVisibility
{
    /** @var \WeakMap<object, array<string, bool>>|null Per-context (guard, ability, subject) → granted. */
    private static ?\WeakMap $cache = null;

    /** @var \WeakMap<object, array<string, true>>|null Per-context abilities already warned about. */
    private static ?\WeakMap $warned = null;

    /**
     * Drop every memo. Test-only helper — in production both WeakMaps key on
     * the execution context and are released with it.
     */
    public static function reset(): void
    {
        self::$cache = null;
        self::$warned = null;
    }

    /**
     * Wrap a field resolver so it is redacted (resolves to null) when the
     * current viewer fails the `canSee` rule, and otherwise delegates to the
     * field's own resolver — falling back to the default resolver when it has
     * none.
     *
     * @param  string|array{0: string, 1?: mixed}|\Closure  $rule
     * @param  \Closure|null  $inner  The field's resolver, or null for the default.
     */
    public static function wrap(string|array|\Closure $rule, ?\Closure $inner): \Closure
    {
        return static function (mixed $root, array $args, mixed $context, ResolveInfo $info) use ($rule, $inner): mixed {
            if (!self::allows($rule, $root, $args, $context)) {
                return null;
            }

            return $inner !== null
                ? $inner($root, $args, $context, $info)
                : DefaultFieldResolver::resolve($root, $args, $context, $info);
        };
    }

    /**
     * Evaluate a `canSee` rule against the current viewer.
     *
     * @param  string|array{0: string, 1?: mixed}|\Closure  $rule
     * @param  array<string, mixed>  $args
     */
    public static function allows(string|array|\Closure $rule, mixed $root, array $args = [], mixed $context = null): bool
    {
        if ($rule instanceof \Closure) {
            return (bool) $rule($root, $args, $context);
        }

        if (is_array($rule)) {
            $ability = (string) $rule[0];
            $subject = self::resolveSubject($rule[1] ?? null, $root, $args, $context);
        } else {
            $ability = (string) $rule;
            $subject = $root;
        }

        return self::check($ability, $subject, $context);
    }

    /**
     * The object an ability is checked against: the parent when no resolver is
     * given, else the resolver's return value.
     *
     * @param  array<string, mixed>  $args
     */
    private static function resolveSubject(mixed $resolver, mixed $root, array $args, mixed $context): mixed
    {
        if ($resolver === null) {
            return $root;
        }

        return $resolver instanceof \Closure ? $resolver($root, $args, $context) : $resolver;
    }

    /**
     * Gate check, memoised per execution context so repeated identical pairs
     * never re-hit the Gate.
     */
    private static function check(string $ability, mixed $subject, mixed $context): bool
    {
        $fingerprint = self::subjectKey($subject);
        $key         = $fingerprint === null
            ? null
            : GuardResolver::resolve(null) . '|' . $ability . '|' . $fingerprint;

        if ($key !== null) {
            $memo = self::memoFor($context);

            if (array_key_exists($key, $memo)) {
                return $memo[$key];
            }
        }

        $ctx = GuardResolver::buildContext(null);

        // Guests fail closed, silently — the ability may be perfectly
        // well-defined, there's simply no viewer to check it for.
        $granted = $ctx->check() && self::evaluate($ctx, $ability, $subject, $context);

        if ($key !== null && is_object($context)) {
            self::$cache ??= new \WeakMap();

            $memo[$key] = $granted;
            self::$cache[$context] = $memo;
        }

        return $granted;
    }

    private static function evaluate(AuthorizationContext $ctx, string $ability, mixed $subject, mixed $context): bool
    {
        if (!self::abilityDefined($ability, $subject)) {
            self::warnUnknown($context, $ability, $subject);
        }

        // Gate::check() already fails closed (returns false) for a missing
        // ability or policy method — the warning above is purely diagnostic.
        return $ctx->can($ability, $subject);
    }

    /**
     * Whether the ability resolves to *something* — a Gate::define()d ability,
     * or a policy for the subject declaring the method (or a before() hook).
     * Used only to decide whether to log; never to grant.
     */
    private static function abilityDefined(string $ability, mixed $subject): bool
    {
        $gate = app(Gate::class);

        if ($gate->has($ability)) {
            return true;
        }

        if (!is_object($subject) && !is_string($subject)) {
            return false;
        }

        $policy = $gate->getPolicyFor($subject);

        if ($policy === null) {
            return false;
        }

        $method = Str::contains($ability, '-') ? Str::camel($ability) : $ability;

        return method_exists($policy, $method) || method_exists($policy, 'before');
    }

    /**
     * Log that $ability matches no Gate ability and no policy method — at most
     * once per ability per request.
     */
    private static function warnUnknown(mixed $context, string $ability, mixed $subject): void
    {
        if (is_object($context)) {
            $seen = self::$warned === null ? [] : (self::$warned[$context] ?? []);

            if (isset($seen[$ability])) {
                return;
            }

            self::$warned ??= new \WeakMap();

            $seen[$ability] = true;
            self::$warned[$context] = $seen;
        }

        $message = "GraphQL: canSee ability [{$ability}] is neither a defined Gate ability nor a policy method for "
            . (is_object($subject) ? $subject::class : gettype($subject))
            . ' — the field is redacted (fail-closed). Check for a missing ability or a typo.';

        $channel = config('laragraph.logging.channel');

        if ($channel !== null && $channel !== '') {
            Log::channel($channel)->warning($message, ['ability' => $ability]);
        } else {
            Log::warning($message, ['ability' => $ability]);
        }
    }

    /**
     * The current context's memo table — empty when the context isn't an
     * object (un-keyable) or nothing has been memoised yet.
     *
     * @return array<string, bool>
     */
    private static function memoFor(mixed $context): array
    {
        if (!is_object($context) || self::$cache === null) {
            return [];
        }

        return self::$cache[$context] ?? [];
    }

    /**
     * A stable fingerprint so two checks of the same model row collapse to one
     * cache entry, while distinct subjects don't collide. Null for subjects
     * that can't be fingerprinted stably (arrays, resources) — those simply
     * aren't cached.
     */
    private static function subjectKey(mixed $subject): ?string
    {
        if ($subject instanceof Model) {
            return $subject->getKey() === null ? null : 'model:' . $subject::class . ':' . $subject->getKey();
        }

        if (is_object($subject)) {
            return 'object:' . $subject::class . ':' . spl_object_id($subject);
        }

        if (is_string($subject) || is_int($subject) || $subject === null) {
            return 'scalar:' . var_export($subject, true);
        }

        return null;
    }
}
