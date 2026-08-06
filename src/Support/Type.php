<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

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
                'fields' => function (): array {
                    return $this->buildFields();
                },
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
        $dataLoaders = is_array($context) ? ($context['dataLoaders'] ?? null) : ($context->dataLoaders ?? null);

        if (!$dataLoaders instanceof \Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry) {
            throw new \RuntimeException(
                'batchRelation() requires a DataLoaderRegistry on the execution context. '
                . 'This is attached automatically by Laragraph::executeQuery() — '
                . 'make sure $context is the value passed into resolve().'
            );
        }

        return $dataLoaders->relation($modelClass, $relation)->load($root->getKey());
    }
}
