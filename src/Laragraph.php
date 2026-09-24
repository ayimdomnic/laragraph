<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Ayimdomnic\Laragraph\DataLoader\DataLoaderPromiseAdapter;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Events\QueryError;
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Events\QueryExecuting;
use Ayimdomnic\Laragraph\Events\SchemaBuilt;
use Ayimdomnic\Laragraph\Exceptions\AuthorizationException;
use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Exceptions\ValidationException;
use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\Extensions\QueryTimingExtension;
use Ayimdomnic\Laragraph\Extensions\RequestIdExtension;
use Ayimdomnic\Laragraph\Http\BatchProcessor;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Performance\ResponseCache;
use Ayimdomnic\Laragraph\Schema\SchemaBuilder;
use Ayimdomnic\Laragraph\Subscriptions\BroadcastSubscriptionUpdates;
use Ayimdomnic\Laragraph\Subscriptions\SubscriptionManager;
use Ayimdomnic\Laragraph\Support\DocumentCache;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use Ayimdomnic\Laragraph\Tracing\TracingExtension;
use Ayimdomnic\Laragraph\Validation\MaxAliasesRule;
use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Executor;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Definition\NamedType;
use GraphQL\Type\Definition\PhpEnumType;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\ValidationRule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
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

    /** @var array<string, true> Documents that passed the document-only validation rules, see prevalidate(). */
    protected array $validated = [];

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
     * @return array<int, array<string, mixed>> One result per input operation, preserving order.
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
     *
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    public function execute(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
        mixed $rootValue = null,
    ): array {
        $startMs            = microtime(true) * 1000;
        $resolvedSchemaName = $schemaName ?? config('laragraph.default_schema', 'default');

        if (config('laragraph.tracing.enabled')) {
            $this->container->make(TracingCollector::class)->reset();
        }

        event(new QueryExecuting($query, $variables, $operationName, $resolvedSchemaName));

        // Response cache — only for read-only queries
        $cacheKey = null;
        $data     = null;

        if (ResponseCache::enabled() && ResponseCache::isCacheable($query, $operationName)) {
            $cacheKey = ResponseCache::key(
                $query,
                $variables,
                $operationName,
                $resolvedSchemaName,
                ResponseCache::scope(),
            );
            $data = ResponseCache::get($cacheKey);
        }

        $cached = $data !== null;

        if ($data === null) {
            $result = $this->executeQuery($query, $context, $variables, $operationName, $schemaName, $rootValue);

            $debug = DebugFlag::NONE;
            if (config('app.debug')) {
                $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
            }

            $data = $result->toArray($debug);

            if ($cacheKey !== null && empty($data['errors'])) {
                ResponseCache::put($cacheKey, $data);
            }
        }

        // Response-level extensions (built-ins + user-registered) are computed
        // per response — never cached — so request ids and timings stay accurate.
        $executionMs   = round(microtime(true) * 1000 - $startMs, 2);
        $extensionData = $this->buildResponseExtensions($executionMs);

        if ($extensionData !== []) {
            $data['extensions'] = array_merge($data['extensions'] ?? [], $extensionData);
        }

        // Lifecycle events — fired for cache hits too, so auditing and metrics see every request.
        if (!empty($data['errors'])) {
            event(new QueryError($query, $variables, $operationName, $resolvedSchemaName, $data['errors']));
        }

        event(new QueryExecuted(
            query: $query,
            variables: $variables,
            operationName: $operationName,
            schemaName: $resolvedSchemaName,
            result: $data,
            executionMs: $executionMs,
            hasErrors: !empty($data['errors']),
            cached: $cached,
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

        if (config('laragraph.tracing.enabled')) {
            $ext = new TracingExtension($this->container->make(TracingCollector::class));
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
     *
     * @param array<string, mixed> $variables
     */
    public function executeQuery(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
        mixed $rootValue = null,
    ): ExecutionResult {
        $schema  = $this->schema($schemaName);
        $context = $this->wrapContext($context ?? request());

        // A custom promise adapter is required (rather than GraphQL::executeQuery(),
        // which always builds its own plain SyncPromiseAdapter internally) so that
        // resolvers returning DataLoader promises actually settle — see
        // DataLoaderPromiseAdapter for why this is necessary.
        $promiseAdapter = new DataLoaderPromiseAdapter();

        // Only fields with no explicit resolver reach this fallback (fields
        // with one — root Query/Mutation/Subscription fields, and Type
        // fields with a custom or convention-bound resolver — are already
        // wrapped for tracing where they're compiled; see SchemaBuilder and
        // Support\Type).
        $fieldResolver = config('laragraph.tracing.enabled')
            ? TracingCollector::wrap(Executor::defaultFieldResolver(...))
            : null;

        // Parsed once per document and shared with operation detection; a
        // syntax error is left for webonyx to report from the raw source.
        $document       = DocumentCache::parse($query);
        [$static, $rules] = $this->partitionValidationRules();

        try {
            $result = $this->prevalidate($schema, $query, $document, $static, $rules)
                ?? $promiseAdapter->wait(GraphQL::promiseToExecute(
                    promiseAdapter: $promiseAdapter,
                    schema: $schema,
                    source: $document ?? $query,
                    rootValue: $rootValue,
                    context: $context,
                    variableValues: $variables ?: null,
                    operationName: $operationName,
                    fieldResolver: $fieldResolver,
                    validationRules: $rules,
                ));
        } finally {
            // Release this execution's loaders; see DataLoaderRegistry::clear().
            DataLoaderRegistry::for($context)?->clear();
        }

        $result->setErrorFormatter(config('laragraph.error_formatter', [static::class, 'formatError']));
        $result->setErrorsHandler(config('laragraph.errors_handler', [static::class, 'handleErrors']));

        return $result;
    }

    /**
     * Validate $document against the rules that depend only on the document
     * and the schema, remembering documents that pass.
     *
     * Those rules — the spec's, plus depth, alias and introspection limits —
     * give the same answer for the same document every time, so a worker
     * that has validated a document once skips them afterwards. When the
     * document cannot be pre-validated (a syntax error), every rule is moved
     * into $rules for webonyx to run as usual.
     *
     * @param  list<ValidationRule> $static Rules whose result depends only on the document.
     * @param  list<ValidationRule> $rules  Rules webonyx must still run; widened when nothing was pre-validated.
     * @return ExecutionResult|null         The validation failure, or null to execute.
     */
    protected function prevalidate(Schema $schema, string $query, ?DocumentNode $document, array $static, array &$rules): ?ExecutionResult
    {
        if ($document === null) {
            $rules = [...$static, ...$rules]; // webonyx reports the syntax error

            return null;
        }

        $key = spl_object_id($schema) . ':' . hash('xxh128', implode('|', array_map($this->ruleSignature(...), $static)) . "\n" . $query);

        if (isset($this->validated[$key])) {
            return null;
        }

        $errors = DocumentValidator::validate($schema, $document, $static);

        if ($errors !== []) {
            return new ExecutionResult(null, $errors);
        }

        if (count($this->validated) >= DocumentCache::SIZE) {
            unset($this->validated[array_key_first($this->validated)]);
        }

        $this->validated[$key] = true;

        return null;
    }

    /**
     * Identifies a rule and its configuration, so a document validated
     * under one setting (e.g. a depth limit of 10) is not trusted under
     * another.
     */
    private function ruleSignature(ValidationRule $rule): string
    {
        return match (true) {
            $rule instanceof QueryDepth     => 'depth:' . $rule->getMaxQueryDepth(),
            $rule instanceof MaxAliasesRule => 'aliases:' . $rule->getMaxAliases(),
            default                         => $rule::class,
        };
    }

    /**
     * This execution's validation rules, split by whether their result
     * depends only on the document.
     *
     * Rules are composed per-execution rather than mutating global state, so
     * different schemas / requests can have different security settings.
     *
     * Query complexity reads the variables (`@include(if: $x)`, list sizes),
     * and application rules may look at anything, so those run on every
     * execution; the rest can be answered once per document.
     *
     * @return array{list<ValidationRule>, list<ValidationRule>} [document-only rules, per-execution rules]
     */
    protected function partitionValidationRules(): array
    {
        $static   = DocumentValidator::allRules();
        $dynamic  = [];
        $security = config('laragraph.security', []);

        // webonyx's own security rules ship switched off (limit 0, introspection
        // allowed); they are replaced below only when a limit is configured,
        // so a rule's presence always means it is enforced.
        unset($static[QueryComplexity::class], $static[QueryDepth::class], $static[DisableIntrospection::class]);

        if (!empty($security['query_max_complexity'])) {
            $dynamic[QueryComplexity::class] = new QueryComplexity((int) $security['query_max_complexity']);
        }

        if (!empty($security['query_max_depth'])) {
            $static[QueryDepth::class] = new QueryDepth((int) $security['query_max_depth']);
        }

        // null = automatic: introspection is only available while app.debug is on.
        if ($security['disable_introspection'] ?? !config('app.debug')) {
            $static[DisableIntrospection::class] = new DisableIntrospection(DisableIntrospection::ENABLED);
        }

        if (!empty($security['max_aliases'])) {
            $static[MaxAliasesRule::class] = new MaxAliasesRule((int) $security['max_aliases']);
        }

        // User-registered custom validation rules. The first custom rule of a
        // class replaces the built-in rule of that class; further instances
        // (e.g. differently configured) are added alongside.
        $seen = [];

        foreach ($this->container->make(ValidationRuleRegistry::class)->resolve() as $rule) {
            unset($static[$rule::class]);

            if (isset($seen[$rule::class])) {
                $dynamic[] = $rule;
            } else {
                $dynamic[$rule::class] = $rule;
                $seen[$rule::class]    = true;
            }
        }

        return [array_values($static), array_values($dynamic)];
    }

    /**
     * Re-execute every subscriber's original query on a channel with
     * $payload as the root value, and push each result to that subscriber.
     *
     * @see SubscriptionManager::broadcast()
     * @return int  The number of subscribers notified.
     */
    public function broadcast(string $channel, mixed $payload = null): int
    {
        return $this->container->make(SubscriptionManager::class)->broadcast($channel, $payload);
    }

    /**
     * Like {@see broadcast()}, but runs the fan-out on the queue so the
     * request that triggered the event does not wait for every subscriber's
     * query. Uses `laragraph.subscriptions.queue.{connection,queue}`.
     */
    public function broadcastLater(string $channel, mixed $payload = null): void
    {
        BroadcastSubscriptionUpdates::dispatch($channel, $payload)
            ->onConnection(config('laragraph.subscriptions.queue.connection'))
            ->onQueue(config('laragraph.subscriptions.queue.queue'));
    }

    /**
     * Remove a subscriber from every channel it subscribed to.
     *
     * @return bool False when the subscriber is unknown.
     */
    public function unsubscribe(string $subscriberId): bool
    {
        return $this->container->make(SubscriptionManager::class)->unsubscribe($subscriberId);
    }

    /**
     * Register a custom GraphQL validation rule.
     *
     * The rule is added to the {@see ValidationRuleRegistry} singleton and will
     * be applied to every subsequent execution.
     *
     * @param string|ValidationRule $rule FQCN or instance.
     */
    public function addValidationRule(string|ValidationRule $rule): void
    {
        $this->container->make(ValidationRuleRegistry::class)->add($rule);
    }

    // -------------------------------------------------------------------------
    // Type registry
    // -------------------------------------------------------------------------

    /**
     * Register a type class (or instance) with an optional alias.
     *
     * @return string The alias the type was registered under.
     */
    public function addType(string|Type $class, ?string $alias = null): string
    {
        if ($class instanceof Type) {
            if ($alias === null) {
                if (!$class instanceof NamedType) {
                    throw new \InvalidArgumentException('An alias is required when registering a type that is not a NamedType.');
                }

                $alias = $class->name();
            }

            $this->typesInstances[$alias] = $class;

            return $alias;
        }

        $alias ??= $this->resolveTypeName($class);
        $this->types[$alias] = $class;
        unset($this->typesInstances[$alias]); // invalidate cached instance

        return $alias;
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
                "Type [{$name}] is not registered. Add it to laragraph.types in your config.",
            );
        }

        $class = $this->types[$name];
        $type  = enum_exists($class)
            ? new PhpEnumType($class, $name)
            : $this->container->make($class);

        $this->typesInstances[$name] = $type;

        return $type;
    }

    /**
     * Resolve a registered type by its GraphQL name rather than its alias.
     *
     * Aliases usually match the GraphQL name, but need not (e.g. an alias of
     * `UserInput` for an input type named `CreateUserInput`). The schema's
     * type loader is always asked by GraphQL name, so it goes through here.
     */
    public function typeByName(string $graphqlName): ?Type
    {
        if ($this->hasType($graphqlName)) {
            $type = $this->type($graphqlName);

            if ($type instanceof NamedType && $type->name() === $graphqlName) {
                return $type;
            }
        }

        foreach (array_unique([...array_keys($this->types), ...array_keys($this->typesInstances)]) as $alias) {
            $type = $this->type((string) $alias);

            if ($type instanceof NamedType && $type->name() === $graphqlName) {
                return $type;
            }
        }

        return null;
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
     *
     * @return array<string, mixed>
     */
    public static function formatError(Error $error): array
    {
        // Only client-safe errors keep their message: anything else (a failed
        // query, a missing file…) could leak SQL, paths or secrets. In debug
        // mode the real message is still available as extensions.debugMessage.
        $message = $error->isClientSafe() ? $error->getMessage() : 'Internal server error';

        $formatted = [
            'message'   => $message ?: 'An unexpected error occurred.',
            'locations' => $error->getLocations()
                ? array_map(
                    fn($loc): array => ['line' => $loc->line, 'column' => $loc->column],
                    $error->getLocations(),
                )
                : null,
            'path'       => $error->getPath(),
            'extensions' => [],
        ];

        $previous = $error->getPrevious();

        if ($previous instanceof ValidationException) {
            $formatted['extensions']['category']   = 'validation';
            $formatted['extensions']['validation'] = $previous->getValidationErrors();
        } elseif ($previous instanceof AuthorizationException) {
            $formatted['extensions']['category'] = 'authorization';
        } elseif ($error->isClientSafe()) {
            $formatted['extensions']['category'] = 'graphql';
        } else {
            $formatted['extensions']['category'] = 'internal';
        }

        if (config('app.debug') && $previous instanceof \Throwable) {
            $formatted['extensions']['debugMessage'] = $previous->getMessage();
            $formatted['extensions']['trace'] = array_map(
                fn(array $frame) => Arr::only($frame, ['file', 'line', 'function', 'class']),
                array_slice($previous->getTrace(), 0, 10),
            );
        }

        return array_filter($formatted, fn(string|array|null $v): bool => $v !== null);
    }

    /**
     * Default errors handler — called once with the full errors array.
     *
     * @param array<int, Error> $errors
     * @param callable(Error):array<string, mixed> $formatter
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
        $this->schemaBuilder ??= new SchemaBuilder($this, $this->container);

        return $this->schemaBuilder;
    }

    /**
     * Prepare the execution context and attach a fresh DataLoaderRegistry.
     *
     * - An HTTP {@see Request} is wrapped in a {@see GraphQLContext}, which
     *   declares `dataLoaders` (and the subscription slots) instead of adding
     *   dynamic properties to the framework's Request.
     * - An array gets a `dataLoaders` key.
     * - Any other object is registered via {@see DataLoaderRegistry::attach()}.
     * - Scalars pass through untouched.
     */
    protected function wrapContext(mixed $context): mixed
    {
        if ($context instanceof Request) {
            $context = GraphQLContext::fromRequest($context);
        }

        if (is_array($context)) {
            $context['dataLoaders'] = new DataLoaderRegistry();

            return $context;
        }

        if (is_object($context)) {
            DataLoaderRegistry::attach($context, new DataLoaderRegistry());
        }

        return $context;
    }

    protected function resolveTypeName(string $class): string
    {
        // Native PHP enums are exposed under their short class name.
        if (enum_exists($class)) {
            return class_basename($class);
        }

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
