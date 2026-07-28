<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Schema;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;
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
     */
    public function build(array $config): Schema
    {
        $this->registerTypes($config['types'] ?? []);
        $schemaConfig = $this->buildSchemaConfig($config);
        return new Schema($schemaConfig);
    }

    // -------------------------------------------------------------------------
    // Schema config assembly
    // -------------------------------------------------------------------------

    protected function buildSchemaConfig(array $config): array
    {
        $schemaConfig = [];

        // Discovered fields are merged with (and overridden by) explicit config
        $queryFields = $this->buildFields(array_merge(
            $this->discoverFields('queries', \Ayimdomnic\Laragraph\Support\Query::class),
            $config['query'] ?? [],
        ));
        if (!empty($queryFields)) {
            $schemaConfig['query'] = new ObjectType(['name' => 'Query', 'fields' => $queryFields]);
        }

        $mutationFields = $this->buildFields(array_merge(
            $this->discoverFields('mutations', \Ayimdomnic\Laragraph\Support\Mutation::class),
            $config['mutation'] ?? [],
        ));
        if (!empty($mutationFields)) {
            $schemaConfig['mutation'] = new ObjectType(['name' => 'Mutation', 'fields' => $mutationFields]);
        }

        $subscriptionFields = $this->buildFields(array_merge(
            $this->discoverFields('subscriptions', \Ayimdomnic\Laragraph\Support\Subscription::class),
            $config['subscription'] ?? [],
        ));
        if (!empty($subscriptionFields)) {
            $schemaConfig['subscription'] = new ObjectType(['name' => 'Subscription', 'fields' => $subscriptionFields]);
        }

        $schemaConfig['types']      = $this->resolveAllTypeInstances();
        $schemaConfig['typeLoader'] = fn (string $name): ?\GraphQL\Type\Definition\Type
            => $this->manager->hasType($name) ? $this->manager->type($name) : null;

        return $schemaConfig;
    }

    /**
     * Discover fields from configured scan directories.
     *
     * @return array<string, string>  alias => FQCN (empty if discover is not configured)
     */
    protected function discoverFields(string $type, string $baseClass): array
    {
        $path = config("laragraph.discover.{$type}", '');
        if (empty($path)) {
            return [];
        }
        return Discover::scan(is_array($path) ? ($path['path'] ?? '') : (string) $path, $baseClass);
    }

    // -------------------------------------------------------------------------
    // Field builders
    // -------------------------------------------------------------------------

    /**
     * Build a GraphQL field map from a [fieldName => FQCN] config array.
     *
     * @return array<string, mixed>
     */
    protected function buildFields(array $fieldClasses): array
    {
        $fields = [];

        foreach ($fieldClasses as $name => $class) {
            /** @var \Ayimdomnic\Laragraph\Support\Field $instance */
            $instance = $this->container->make($class);
            $field    = $instance->toArray();

            if (method_exists($instance, 'complexity') && ($cost = $instance->complexity()) !== null) {
                $field['complexity'] = fn (int $childrenComplexity) => $childrenComplexity + $cost;
            }

            $fields[$name] = $field;
        }

        return $fields;
    }

    // -------------------------------------------------------------------------
    // Type registration
    // -------------------------------------------------------------------------

    protected function registerTypes(array $typeClasses): void
    {
        // Auto-discover types first, then merge with explicit config (explicit wins)
        $discoveredTypes = Discover::types(
            (string) config('laragraph.discover.types', '')
        );

        foreach (array_merge($discoveredTypes, $typeClasses) as $alias => $class) {
            $this->manager->addType($class, is_string($alias) ? $alias : null);
        }
    }

    /**
     * @return array<\GraphQL\Type\Definition\Type>
     */
    protected function resolveAllTypeInstances(): array
    {
        return array_map(
            fn (string $name) => $this->manager->type($name),
            array_keys($this->manager->getTypes()),
        );
    }
}
