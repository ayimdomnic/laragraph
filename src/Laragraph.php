<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Events\QueryError;
use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Http\BatchProcessor;
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Events\QueryExecuting;
use Ayimdomnic\Laragraph\Events\SchemaBuilt;
use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\Extensions\QueryTimingExtension;
use Ayimdomnic\Laragraph\Extensions\RequestIdExtension;
use Ayimdomnic\Laragraph\Performance\ResponseCache;
use Ayimdomnic\Laragraph\Schema\SchemaBuilder;
use Ayimdomnic\Laragraph\Validation\MaxAliasesRule;
use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Arr;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
class Laragraph
{
    /** @var array<string, Schema> Built schema cache keyed by schema name. */
    protected array $schemas = [];

    /** @var array<string, string> Type class map: alias => FQCN. */
    protected array $types = [];

    /** @var array<string, Type> Resolved type instances keyed by alias. */
    protected array $typesInstances = [];

    protected ?SchemaBuilder $schemaBuilder = null;

    public function __construct(protected readonly Container $container) {}

    // -------------------------------------------------------------------------
    // Schema resolution
    // -------------------------------------------------------------------------

    /**
     * Get (and cache) a compiled GraphQL Schema by name.
     *
     * @throws SchemaException
     */
    public function schema(?string $name = null): Schema
    {
        $name ??= config('laragraph.default_schema', 'default');

        if (isset($this->schemas[$name])) {
            return $this->schemas[$name];
        }

        $schemaConfig = config("laragraph.schemas.{$name}");

        if ($schemaConfig === null) {
            throw new SchemaException("Schema [{$name}] not found in laragraph configuration.");
        }

        // Merge global types into every schema build
        $schemaConfig['types'] = array_merge(
            config('laragraph.types', []),
            $schemaConfig['types'] ?? [],
        );

        $schema = $this->schemas[$name] = $this->getSchemaBuilder()->build($schemaConfig);
        event(new SchemaBuilt($name, $schema));
        return $schema;
    }

    /**
     * Execute a batch of GraphQL operations and return an indexed array of results.
     *
     * Delegates to {@see BatchProcessor::process()}. Batching must be enabled via
     * `laragraph.batching.enabled` in the config or this method throws.
     *
     * @param  array<int, array{query?: string, variables?: mixed, operationName?: string|null}> $operations
     * @param  mixed  $context    Passed through to each individual execute() call.
     * @param  string $schemaName Schema to run all operations against.
     * @return array<int, array>  One result per input operation, preserving order.
     *
     * @throws BatchingDisabledException
     * @throws BatchLimitExceededException
     */
    public function executeBatch(
        array $operations,
        mixed $context = null,
        string $schemaName = 'default',
    ): array {
        return $this->container->make(BatchProcessor::class)->process($operations, $context, $schemaName);
    }

    /**
     * Execute a GraphQL query string and return the serialised result array.
     *
     * Response caching (configurable via `laragraph.cache.response`) is applied
     * to read-only query operations. Mutations and subscriptions bypass the cache.
     */
    public function execute(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
    ): array {
        $startMs            = microtime(true) * 1000;
        $resolvedSchemaName = $schemaName ?? config('laragraph.default_schema', 'default');

        // Response cache — only for read-only queries
        if (ResponseCache::enabled() && ResponseCache::isCacheable($query)) {
            $cacheKey = ResponseCache::key($query, $variables, $operationName);
            $cached   = ResponseCache::get($cacheKey);

            if ($cached !== null) {
                return $cached;
            }
        }

        event(new QueryExecuting($query, $variables, $operationName, $resolvedSchemaName));

        $result = $this->executeQuery($query, $context, $variables, $operationName, $schemaName);

        $debug = DebugFlag::NONE;
        if (config('app.debug')) {
            $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
        }

        $data = $result->toArray($debug);

        if (isset($cacheKey) && empty($data['errors'])) {
            ResponseCache::put($cacheKey, $data);
        }

        // Merge response-level extensions (built-ins + user-registered)
        $executionMs   = round(microtime(true) * 1000 - $startMs, 2);
        $extensionData = $this->buildResponseExtensions($executionMs);

        if (!empty($extensionData)) {
            $data['extensions'] = array_merge($data['extensions'] ?? [], $extensionData);
        }

        // Lifecycle events
        if (!empty($data['errors'])) {
            event(new QueryError($query, $variables, $operationName, $resolvedSchemaName, $data['errors']));
        }

        event(new QueryExecuted(
            query:         $query,
            variables:     $variables,
            operationName: $operationName,
            schemaName:    $resolvedSchemaName,
            result:        $data,
            executionMs:   $executionMs,
            hasErrors:     !empty($data['errors']),
        ));

        return $data;
    }

    /**
     * Collect data for `response.extensions` from built-in and user-registered sources.
     *
     * @param  float $executionMs Total execute() wall-clock time in milliseconds.
     * @return array<string, array<string, mixed>>
     */
    private function buildResponseExtensions(float $executionMs): array
    {
        $extensions = [];
        $config     = config('laragraph.extensions', []);
        $context    = ['execution_ms' => $executionMs];

        if (!empty($config['request_id'])) {
            $ext = new RequestIdExtension();
            $extensions[$ext->key()] = $ext->get($context);
        }

        if (!empty($config['query_timing'])) {
            $ext = new QueryTimingExtension();
            $extensions[$ext->key()] = $ext->get($context);
        }

        // User-registered custom extensions
        foreach ($this->container->make(ExtensionRegistry::class)->collect($context) as $key => $data) {
            $extensions[$key] = $data;
        }

        return $extensions;
    }

    /**
     * Execute a GraphQL query and return the raw ExecutionResult.
     *
     * A fresh {@see DataLoaderRegistry} is attached to the context for each
     * execution so resolvers can batch N+1 database calls.
     */
    public function executeQuery(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
    ): ExecutionResult {
        $schema  = $this->schema($schemaName);
        $context = $this->wrapContext($context ?? request());

        $result = GraphQL::executeQuery(
            schema:          $schema,
            source:          $query,
            contextValue:    $context,
            variableValues:  $variables ?: null,
            operationName:   $operationName,
            fieldResolver:   null,
            validationRules: $this->buildValidationRules(),
        );

        $errorFormatter = config('laragraph.error_formatter', [static::class, 'formatError']);
        $result->setErrorFormatter($errorFormatter);

        return $result;
    }

    /**
     * Build the set of validation rules for this execution.
     *
     * Rules are composed per-execution rather than mutating global state, so
     * different schemas / requests can have different security settings.
     *
     * @return array<\GraphQL\Validator\Rules\ValidationRule>
     */
    protected function buildValidationRules(): array
    {
        $rules    = DocumentValidator::allRules();
        $security = config('laragraph.security', []);

        if (!empty($security['query_max_complexity'])) {
            $rules['queryComplexity'] = new QueryComplexity((int) $security['query_max_complexity']);
        }

        if (!empty($security['query_max_depth'])) {
            $rules['queryDepth'] = new QueryDepth((int) $security['query_max_depth']);
        }

        if (!empty($security['disable_introspection'])) {
            $rules['disableIntrospection'] = new DisableIntrospection(DisableIntrospection::ENABLED);
        }

        if (!empty($security['max_aliases'])) {
            $rules['maxAliases'] = new MaxAliasesRule((int) $security['max_aliases']);
        }

        // User-registered custom validation rules
        $registry = $this->container->make(ValidationRuleRegistry::class);
        if (!$registry->isEmpty()) {
            foreach ($registry->resolve() as $rule) {
                $rules[get_class($rule)] = $rule;
            }
        }

        return array_values($rules);
    }

    /**
     * Register a custom GraphQL validation rule.
     *
     * The rule is added to the {@see ValidationRuleRegistry} singleton and will
     * be applied to every subsequent execution.
     *
     * @param  string|\GraphQL\Validator\Rules\ValidationRule $rule  FQCN or instance.
     */
    public function addValidationRule(string|\GraphQL\Validator\Rules\ValidationRule $rule): void
    {
        $this->container->make(ValidationRuleRegistry::class)->add($rule);
    }

    // -------------------------------------------------------------------------
    // Type registry
    // -------------------------------------------------------------------------

    /**
     * Register a type class (or instance) with an optional alias.
     */
    public function addType(string|Type $class, ?string $alias = null): void
    {
        if ($class instanceof Type) {
            if ($alias === null) {
                if (!$class instanceof NamedType) {
                    throw new \InvalidArgumentException('An alias is required when registering a type that is not a NamedType.');
                }

                $alias = $class->name();
            }

            $this->typesInstances[$alias] = $class;
            return;
        }

        $alias ??= $this->resolveTypeName($class);
        $this->types[$alias] = $class;
        unset($this->typesInstances[$alias]); // invalidate cached instance
    }

    /**
     * Resolve a type instance by alias/name.
     *
     * @throws \InvalidArgumentException
     */
    public function type(string $name, bool $fresh = false): Type
    {
        if (!$fresh && isset($this->typesInstances[$name])) {
            return $this->typesInstances[$name];
        }

        if (!isset($this->types[$name])) {
            throw new \InvalidArgumentException(
                "Type [{$name}] is not registered. Add it to laragraph.types in your config."
            );
        }

        $type = $this->container->make($this->types[$name]);
        $this->typesInstances[$name] = $type;

        return $type;
    }

    /** Return all registered type class aliases.
     *
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /** Check whether a type name is registered. */
    public function hasType(string $name): bool
    {
        return isset($this->types[$name]) || isset($this->typesInstances[$name]);
    }

    // -------------------------------------------------------------------------
    // Error handling (static — usable as callables in config)
    // -------------------------------------------------------------------------

    /**
     * Default error formatter — exposed via config('laragraph.error_formatter').
     */
    public static function formatError(Error $error): array
    {
        $formatted = [
            'message'   => $error->getMessage() ?: 'An unexpected error occurred.',
            'locations' => $error->getLocations()
                ? array_map(
                    fn ($loc) => ['line' => $loc->line, 'column' => $loc->column],
                    $error->getLocations(),
                )
                : null,
            'path'       => $error->getPath(),
            'extensions' => [],
        ];

        $previous = $error->getPrevious();

        if ($previous instanceof \Ayimdomnic\Laragraph\Exceptions\ValidationException) {
            $formatted['extensions']['category']   = 'validation';
            $formatted['extensions']['validation'] = $previous->getValidationErrors();
        } elseif ($previous instanceof \Ayimdomnic\Laragraph\Exceptions\AuthorizationException) {
            $formatted['extensions']['category'] = 'authorization';
        } elseif ($error->isClientSafe()) {
            $formatted['extensions']['category'] = 'graphql';
        } else {
            $formatted['extensions']['category'] = 'internal';
        }

        if (config('app.debug') && $previous !== null) {
            $formatted['extensions']['debugMessage'] = $previous->getMessage();
            $formatted['extensions']['trace'] = array_map(
                fn ($frame) => Arr::only($frame, ['file', 'line', 'function', 'class']),
                array_slice($previous->getTrace(), 0, 10),
            );
        }

        return array_filter($formatted, fn ($v) => $v !== null);
    }

    /**
     * Default errors handler — called once with the full errors array.
     *
     * @param  array<int, \GraphQL\Error\Error>  $errors
     * @param  callable(\GraphQL\Error\Error): array<string, mixed>  $formatter
     * @return array<int, array<string, mixed>>
     */
    public static function handleErrors(array $errors, callable $formatter): array
    {
        return array_map($formatter, $errors);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    protected function getSchemaBuilder(): SchemaBuilder
    {
        if ($this->schemaBuilder === null) {
            $this->schemaBuilder = new SchemaBuilder($this, $this->container);
        }

        return $this->schemaBuilder;
    }

    /**
     * Attach a DataLoaderRegistry to the execution context.
     *
     * If $context is a plain object, the `dataLoaders` property is added
     * directly. If it is an array, the key is added. Otherwise a lightweight
     * anonymous object carrying both the original context and the registry is
     * returned.
     */
    protected function wrapContext(mixed $context): mixed
    {
        if (is_object($context)) {
            $context->dataLoaders = new DataLoaderRegistry();
            return $context;
        }

        if (is_array($context)) {
            $context['dataLoaders'] = new DataLoaderRegistry();
            return $context;
        }

        return $context;
    }

    protected function resolveTypeName(string $class): string
    {
        // Try to get the name without instantiating (cheaper)
        if (defined("{$class}::NAME")) {
            return $class::NAME;
        }

        $instance = $this->container->make($class);

        if (property_exists($instance, 'name')) {
            return $instance->name;
        }

        return class_basename($class);
    }
}
