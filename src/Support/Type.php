<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use Ayimdomnic\Laragraph\Auth\FieldVisibility;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Relay\GlobalId;
use Ayimdomnic\Laragraph\Relay\NodeQuery;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use GraphQL\Type\Definition\ObjectType;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for GraphQL Object Types.
 *
 * Usage:
 *
 *   class UserType extends Type
 *   {
 *       protected array $attributes = [
 *           'name'        => 'User',
 *           'description' => 'A registered user.',
 *       ];
 *
 *       public function fields(): array
 *       {
 *           return [
 *               'id'    => ['type' => Type::nonNull(Type::id())],
 *               'name'  => ['type' => Type::string()],
 *               'email' => ['type' => Type::string()],
 *           ];
 *       }
 *
 *       // Optional per-field resolvers: resolve{FieldName}Field($root, $args)
 *       protected function resolveEmailField(mixed $root, array $args): string
 *       {
 *           return strtolower($root->email);
 *       }
 *   }
 *
 * ## Field-level visibility (`canSee`)
 *
 * Declare a `canSee` key on any field to redact it — resolve to `null`, never
 * an error — when the current viewer fails the check. This makes PII redaction
 * structural instead of a hand-written resolver you might forget:
 *
 * ```php
 * 'email' => ['type' => Type::string(), 'canSee' => 'viewEmail'],           // Gate ability
 * 'paidAt' => ['type' => ..., 'canSee' => ['viewBilling', fn ($u) => $u]],  // ability + subject
 * 'phone'  => ['type' => ..., 'canSee' => fn ($u, $args, $ctx) => $ctx->user()?->is($u)], // closure
 * ```
 *
 * Or wrap a resolver directly with {@see guard()}:
 *
 * ```php
 * 'phone' => ['type' => Type::string(), 'resolve' => $this->guard('viewPhone', fn (User $u) => $u->phone)],
 * ```
 *
 * @see FieldVisibility
 */
abstract class Type extends ObjectType
{
    /**
     * Describe the type using 'name' and optionally 'description' and
     * 'interfaces'.
     *
     * @var array<string, mixed>
     */
    protected array $attributes = [];

    public function __construct()
    {
        $config = array_merge(
            ['name' => class_basename(static::class)],
            $this->attributes,
            [
                'fields' => $this->buildFields(...),
            ],
        );

        parent::__construct($config);
    }

    /**
     * Return the field definitions for this type.
     *
     * Each entry may be:
     *  - A full field config array: ['type' => ..., 'description' => ..., ...]
     *  - A shorthand Type instance: \GraphQL\Type\Definition\Type::string()
     *
     * @return array<string, mixed>
     */
    abstract public function fields(): array;

    /**
     * Load this type's instance for Relay's `node(id: ID!): Node` root field
     * (see {@see NodeQuery}), given the local id
     * decoded from a {@see GlobalId}.
     *
     * Not wired to anything by default — override it on a type that
     * `implements Node` to make it re-fetchable. Bypasses the `Field`
     * authorize()/policy() pipeline entirely (this isn't a field), so an
     * override must apply its own visibility check and return null rather
     * than an object the current viewer shouldn't see.
     */
    public function resolveNode(string $id, mixed $context): ?object
    {
        return null;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    protected function buildFields(): array
    {
        $fields = $this->fields();

        foreach ($fields as $name => &$field) {
            // Shorthand: if value is a Type instance, wrap it
            if ($field instanceof \GraphQL\Type\Definition\Type) {
                $field = ['type' => $field];
            }

            // Auto-attach per-field resolver methods: resolveEmailField, etc.
            //
            // Bound via first-class callable syntax (not a raw [$this, $method]
            // array) so the resulting Closure captures this class's visibility
            // scope. That matters because these methods are conventionally
            // `protected` — a bare array callable is re-checked for visibility
            // by whichever code invokes it later (webonyx's executor, which has
            // no access to a protected method), and would fail at call time.
            if (!isset($field['resolve'])) {
                $method = 'resolve' . ucfirst($name) . 'Field';
                if (method_exists($this, $method)) {
                    $field['resolve'] = $this->{$method}(...);
                }
            }

            // Declarative field-level authorization: a 'canSee' rule redacts the
            // field (resolves to null, never an error) when the viewer fails the
            // check — see FieldVisibility. Popped from the config so webonyx
            // never sees the custom key; the fallback resolver is bound
            // explicitly because setting 'resolve' bypasses the executor's
            // global default.
            if (array_key_exists('canSee', $field)) {
                $field['resolve'] = FieldVisibility::wrap($field['canSee'], $field['resolve'] ?? null);
                unset($field['canSee']);
            }

            if (isset($field['resolve']) && config('laragraph.tracing.enabled')) {
                $field['resolve'] = TracingCollector::wrap($field['resolve']);
            }
        }

        return $fields;
    }

    /**
     * Resolve an Eloquent relation for $root through the request's
     * DataLoaderRegistry, collapsing what would otherwise be an N+1 query
     * per parent into a single batched query per relation.
     *
     * ```php
     * protected function resolveCommentsField(mixed $root, array $args, mixed $context): mixed
     * {
     *     return $this->batchRelation(Post::class, 'comments', $root, $context);
     * }
     * ```
     *
     * @param  class-string<Model>  $modelClass
     */
    protected function batchRelation(string $modelClass, string $relation, Model $root, mixed $context): mixed
    {
        $dataLoaders = DataLoaderRegistry::for($context);

        if (!$dataLoaders instanceof DataLoaderRegistry) {
            throw new \RuntimeException(
                'batchRelation() requires a DataLoaderRegistry on the execution context. '
                . 'This is attached automatically by Laragraph::executeQuery() — '
                . 'make sure $context is the value passed into resolve().',
            );
        }

        return $dataLoaders->relation($modelClass, $relation, $root)->load($root->getKey());
    }

    /**
     * Wrap a resolver in a field-level visibility check: the returned resolver
     * redacts the field (resolves to `null`, never an error) when the current
     * viewer fails $rule, and otherwise delegates to $resolver — falling back
     * to the default attribute lookup when no resolver is given.
     *
     * The `'canSee'` key on a field definition is shorthand for exactly this,
     * applied around the field's existing (or default) resolver.
     *
     * ```php
     * 'phone' => [
     *     'type'    => Type::string(),
     *     'resolve' => $this->guard('viewPhone', fn (User $u) => $u->phone),
     * ],
     * ```
     *
     * @param  string|array{0: string, 1?: mixed}|\Closure  $rule  Ability, [ability, subjectResolver], or closure.
     */
    protected function guard(string|array|\Closure $rule, ?\Closure $resolver = null): \Closure
    {
        return FieldVisibility::wrap($rule, $resolver);
    }

    /**
     * Evaluate a visibility rule imperatively — for checks that don't fit the
     * declarative `'canSee'` key (inside a custom resolver, say). Returns
     * whether the current viewer may see the given subject; guests and
     * unknown abilities fail closed.
     *
     * ```php
     * protected function resolvePhoneField(mixed $root, array $args, mixed $context): ?string
     * {
     *     return $this->canSee('viewPhone', $root, $args, $context) ? $root->phone : null;
     * }
     * ```
     *
     * @param  string|array{0: string, 1?: mixed}|\Closure  $rule
     * @param  array<string, mixed>  $args
     */
    protected function canSee(string|array|\Closure $rule, mixed $subject = null, array $args = [], mixed $context = null): bool
    {
        return FieldVisibility::allows($rule, $subject, $args, $context);
    }
}
