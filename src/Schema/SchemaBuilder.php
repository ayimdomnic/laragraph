<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Schema;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\Field;
use Ayimdomnic\Laragraph\Support\Mutation;
use Ayimdomnic\Laragraph\Support\Query;
use Ayimdomnic\Laragraph\Support\Subscription;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Type\SchemaConfig;
use Illuminate\Contracts\Container\Container;

/**
 * Builds a GraphQL\Type\Schema from a Laragraph schema configuration array.
 */
class SchemaBuilder
{
    public function __construct(
        protected readonly Laragraph $manager,
        protected readonly Container $container,
    ) {}

    /**
     * Compile a full GraphQL Schema from a schema config array.
     *
     * Expected keys:
     *   'query'        => ['fieldName' => FQCN, ...]
     *   'mutation'     => ['fieldName' => FQCN, ...]
     *   'subscription' => ['fieldName' => FQCN, ...]
     *   'types'        => ['Alias' => FQCN, ...]
     *
     * @param array<string, mixed> $config
     */
    public function build(array $config): Schema
    {
        $aliases      = $this->registerTypes($config['types'] ?? []);
        $options      = $this->buildSchemaConfig($config, $aliases);
        $schemaConfig = SchemaConfig::create($options);
        $schema       = new Schema($schemaConfig);

        /** @var array<string, ObjectType> $roots */
        $roots = [];
        foreach (['query', 'mutation', 'subscription'] as $operation) {
            if (($options[$operation] ?? null) instanceof ObjectType) {
                $roots[$options[$operation]->name] = $options[$operation];
            }
        }
        $ownAliases = array_fill_keys($aliases, true);

        // Types are loaded lazily, by name, as a document needs them — a
        // request builds only the types it touches instead of the whole schema.
        //
        // Only *this* schema's types may answer: the Laragraph type registry
        // is shared by every schema, so resolving against all of it would
        // expose another schema's types here (e.g. an admin-only type
        // answering `__type(name: ...)` on the public schema). A name that is
        // not one of this schema's own aliases (a connection type, a type
        // registered under a different alias…) falls back to the schema's
        // full type map, which only ever contains types reachable from it.
        $schemaConfig->setTypeLoader(function (string $name) use ($schema, $roots, $ownAliases): ?Type {
            if (isset($roots[$name])) {
                return $roots[$name];
            }

            if (isset($ownAliases[$name])) {
                $type = $this->manager->type($name);

                if ($type instanceof NamedType && $type->name() === $name) {
                    return $type;
                }
            }

            return $schema->getTypeMap()[$name] ?? null;
        });

        return $schema;
    }

    // -------------------------------------------------------------------------
    // Schema config assembly
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $config
     * @param  list<string>|null     $typeAliases Registered types that belong to this schema (null: every registered type).
     * @return array<string, mixed>
     */
    protected function buildSchemaConfig(array $config, ?array $typeAliases = null): array
    {
        $schemaConfig = [];

        // Discovered fields are merged with (and overridden by) explicit config
        $queryFields = $this->buildFields(array_merge(
            $this->discoverFields('queries', Query::class),
            $config['query'] ?? [],
        ));
        if ($queryFields !== []) {
            $schemaConfig['query'] = new ObjectType(['name' => 'Query', 'fields' => $queryFields]);
        }

        $mutationFields = $this->buildFields(array_merge(
            $this->discoverFields('mutations', Mutation::class),
            $config['mutation'] ?? [],
        ));
        if ($mutationFields !== []) {
            $schemaConfig['mutation'] = new ObjectType(['name' => 'Mutation', 'fields' => $mutationFields]);
        }

        $subscriptionFields = $this->buildFields(array_merge(
            $this->discoverFields('subscriptions', Subscription::class),
            $config['subscription'] ?? [],
        ));
        if ($subscriptionFields !== []) {
            $schemaConfig['subscription'] = new ObjectType(['name' => 'Subscription', 'fields' => $subscriptionFields]);
        }

        // Resolved only when the full type map is needed (introspection,
        // interface implementations, schema validation), never per request.
        $schemaConfig['types'] = fn(): array => $this->resolveAllTypeInstances($typeAliases);

        // Declared up front so completing a scalar never makes webonyx scan
        // every type for overrides of String/Int/Float/Boolean/ID — that scan
        // would materialise the lazy type list above on every request.
        $schemaConfig['scalarOverrides'] = $this->scalarOverrides($typeAliases);

        return $schemaConfig;
    }

    /**
     * Discover fields from configured scan directories.
     *
     * @return array<string, string>  alias => FQCN (empty if discover is not configured)
     */
    protected function discoverFields(string $type, string $baseClass): array
    {
        $path = Discover::configuredPath($type);

        return $path === '' ? [] : Discover::scan($path, $baseClass);
    }

    // -------------------------------------------------------------------------
    // Field builders
    // -------------------------------------------------------------------------

    /**
     * Build a lazy GraphQL field map from a [fieldName => FQCN] config array.
     *
     * Each field class is instantiated and compiled only when the field is
     * first needed (a document selects it, or introspection lists it), so a
     * request pays for the fields it uses rather than for every operation in
     * the schema.
     *
     * @return array<string, \Closure(): array<string, mixed>>
     * @param array<string, class-string<Field>> $fieldClasses
     */
    protected function buildFields(array $fieldClasses): array
    {
        $fields = [];

        foreach ($fieldClasses as $name => $class) {
            $fields[$name] = fn(): array => $this->buildField($class);
        }

        return $fields;
    }

    /**
     * Compile one root field class into a field definition.
     *
     * @param  class-string<Field>  $class
     * @return array<string, mixed>
     */
    protected function buildField(string $class): array
    {
        /** @var Field $instance */
        $instance = $this->container->make($class);
        $field    = $instance->toArray();

        $cost = $instance->complexity();
        if ($cost !== null) {
            $field['complexity'] = fn(int $childrenComplexity): int => $childrenComplexity + $cost;
        }

        if (config('laragraph.tracing.enabled')) {
            $field['resolve'] = TracingCollector::wrap($field['resolve']);
        }

        return $field;
    }

    // -------------------------------------------------------------------------
    // Type registration
    // -------------------------------------------------------------------------

    /**
     * Register this schema's types (discovered + global + schema-specific)
     * and return the aliases they were registered under.
     *
     * @param  array<string|int, string|Type>  $typeClasses
     * @return list<string>
     */
    protected function registerTypes(array $typeClasses): array
    {
        $aliases = [];

        // Auto-discover types first, then merge with explicit config (explicit wins)
        $discoveredTypes = Discover::types(Discover::configuredPath('types'));

        // A class registered explicitly (possibly under a different alias) must
        // not also be registered by discovery, or it would be instantiated twice.
        $explicit = array_flip(array_filter($typeClasses, is_string(...)));

        foreach ($discoveredTypes as $alias => $class) {
            if (!isset($explicit[$class])) {
                $aliases[] = $this->manager->addType($class, $alias);
            }
        }

        foreach ($typeClasses as $alias => $class) {
            $aliases[] = $this->manager->addType($class, is_string($alias) ? $alias : null);
        }

        return array_values(array_unique($aliases));
    }

    /**
     * Registered types that replace a built-in scalar: a scalar registered
     * under the built-in's name (e.g. `'ID' => UuidIdType::class`).
     *
     * @param  list<string>|null $aliases Only these registered types (null: every registered type).
     * @return list<ScalarType>
     */
    protected function scalarOverrides(?array $aliases = null): array
    {
        $aliases   = array_fill_keys($aliases ?? array_keys($this->manager->getTypes()), true);
        $overrides = [];

        foreach (Type::builtInScalars() as $name => $builtIn) {
            if (!isset($aliases[$name])) {
                continue;
            }

            $type = $this->manager->type($name);

            if ($type instanceof ScalarType && $type->name === $name && $type !== $builtIn) {
                $overrides[] = $type;
            }
        }

        return $overrides;
    }

    /**
     * @param  list<string>|null $aliases Only these registered types (null: every registered type).
     * @return array<Type>
     */
    protected function resolveAllTypeInstances(?array $aliases = null): array
    {
        return array_map(
            fn(string $name): Type => $this->manager->type($name),
            $aliases ?? array_keys($this->manager->getTypes()),
        );
    }
}
