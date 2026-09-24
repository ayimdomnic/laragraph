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
use GraphQL\Type\Definition\ObjectType;
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
        $schemaConfig = SchemaConfig::create($this->buildSchemaConfig($config, $aliases));
        $schema       = new Schema($schemaConfig);

        // Resolve names only against *this* schema's type map (its root types,
        // its own registered types, and everything they reference). The
        // Laragraph type registry is shared by every schema, so resolving
        // against it would expose another schema's types here — e.g. an
        // admin-only type answering `__type(name: ...)` on the public schema.
        $schemaConfig->setTypeLoader(static fn(string $name): ?Type => $schema->getTypeMap()[$name] ?? null);

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

        $schemaConfig['types'] = $this->resolveAllTypeInstances($typeAliases);

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
     * Build a GraphQL field map from a [fieldName => FQCN] config array.
     *
     * @return array<string, mixed>
     * @param array<string, class-string<Field>> $fieldClasses
     */
    protected function buildFields(array $fieldClasses): array
    {
        $fields = [];

        foreach ($fieldClasses as $name => $class) {
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

            $fields[$name] = $field;
        }

        return $fields;
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
