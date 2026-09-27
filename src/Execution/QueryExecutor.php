<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Execution;

use Ayimdomnic\Laragraph\Contracts\BatchProcessorInterface;
use Ayimdomnic\Laragraph\Contracts\ExtensionRegistryInterface;
use Ayimdomnic\Laragraph\Contracts\OtelSpanExporterInterface;
use Ayimdomnic\Laragraph\Contracts\QueryComplexityStateInterface;
use Ayimdomnic\Laragraph\Contracts\QueryExecutorInterface;
use Ayimdomnic\Laragraph\Contracts\SchemaRegistryInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Ayimdomnic\Laragraph\Contracts\TracingCollectorInterface;
use Ayimdomnic\Laragraph\Contracts\ValidationRuleRegistryInterface;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderPromiseAdapter;
use Ayimdomnic\Laragraph\DataLoader\DataLoaderRegistry;
use Ayimdomnic\Laragraph\Events\QueryError;
use Ayimdomnic\Laragraph\Events\QueryExecuted;
use Ayimdomnic\Laragraph\Events\QueryExecuting;
use Ayimdomnic\Laragraph\Exceptions\MissingOptionalDependencyException;
use Ayimdomnic\Laragraph\Extensions\QueryComplexityExtension;
use Ayimdomnic\Laragraph\Extensions\QueryTimingExtension;
use Ayimdomnic\Laragraph\Extensions\RequestIdExtension;
use Ayimdomnic\Laragraph\Http\GraphQLContext;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Performance\ResponseCache;
use Ayimdomnic\Laragraph\Support\DefaultFieldResolver;
use Ayimdomnic\Laragraph\Support\DocumentCache;
use Ayimdomnic\Laragraph\Support\ErrorLocaleResolver;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use Ayimdomnic\Laragraph\Tracing\TracingExtension;
use Ayimdomnic\Laragraph\Validation\MaxAliasesRule;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\GraphQL;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Type\Schema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use GraphQL\Validator\Rules\QueryComplexity;
use GraphQL\Validator\Rules\QueryDepth;
use GraphQL\Validator\Rules\ValidationRule;
use Illuminate\Http\Request;
use OpenTelemetry\API\Globals;

/**
 * The GraphQL query execution engine — extracted from the execution
 * responsibility {@see Laragraph} used to own directly.
 *
 * Deliberately has no dependency on
 * {@see BatchProcessorInterface} or
 * {@see SubscriptionManagerInterface} — both
 * of those depend on this class to run a query, so this class depending back
 * on either would create a circular constructor dependency the container
 * cannot resolve.
 */
final class QueryExecutor implements QueryExecutorInterface
{
    /** How many validated documents a worker remembers (a key is ~60 bytes). */
    public const VALIDATED_DOCUMENTS = 1000;

    /** @var array<string, true> Documents that passed the document-only validation rules, see prevalidate(). */
    private array $validated = [];

    public function __construct(
        private readonly SchemaRegistryInterface $schemas,
        private readonly ExtensionRegistryInterface $extensions,
        private readonly ValidationRuleRegistryInterface $validationRules,
        private readonly TracingCollectorInterface $tracing,
        private readonly OtelSpanExporterInterface $otel,
        private readonly QueryComplexityStateInterface $complexityState,
    ) {}

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
            $this->tracing->reset();
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
            // Errors are localized at construction/formatting time via the
            // ambient app locale — briefly switch to the negotiated one (if
            // any) so GraphQLException messages and formatError() come back
            // translated, then always restore it, even on failure.
            $locale         = ErrorLocaleResolver::resolve($context);
            $previousLocale = null;

            if ($locale !== null) {
                $previousLocale = app()->getLocale();
                app()->setLocale($locale);
            }

            try {
                $result = $this->executeQuery($query, $context, $variables, $operationName, $schemaName, $rootValue);

                $debug = DebugFlag::NONE;
                if (config('app.debug')) {
                    $debug = DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;
                }

                $data = $result->toArray($debug);
            } finally {
                if ($previousLocale !== null) {
                    app()->setLocale($previousLocale);
                }
            }

            if (config('laragraph.tracing.enabled') && config('laragraph.tracing.driver', 'apollo') === 'otel') {
                // open-telemetry/api is a suggested, not required, dependency —
                // only the 'otel' driver needs it. A clear error beats a raw
                // "Class not found" autoload fatal.
                if (!class_exists(Globals::class)) {
                    throw MissingOptionalDependencyException::forPackage(
                        'open-telemetry/api',
                        "The 'otel' tracing driver",
                    );
                }

                $this->otel->export(
                    $this->tracing,
                    $query,
                    $operationName,
                    $resolvedSchemaName,
                    !empty($data['errors']),
                );
            }

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

        // The 'otel' driver exports real spans out-of-band (see execute()) instead
        // of adding extensions.tracing to the response body.
        if (config('laragraph.tracing.enabled') && config('laragraph.tracing.driver', 'apollo') !== 'otel') {
            $ext = new TracingExtension($this->tracing);
            $extensions[$ext->key()] = $ext->get($context);
        }

        if (!empty($config['query_complexity'])) {
            $ext = new QueryComplexityExtension($this->complexityState);
            $extensions[$ext->key()] = $ext->get($context);
        }

        // User-registered custom extensions
        foreach ($this->extensions->collect($context) as $key => $data) {
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
        $schema  = $this->schemas->schema($schemaName);
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
            ? TracingCollector::wrap(DefaultFieldResolver::resolve(...))
            : DefaultFieldResolver::resolve(...);

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

        $result->setErrorFormatter(config('laragraph.error_formatter', [Laragraph::class, 'formatError']));
        $result->setErrorsHandler(config('laragraph.errors_handler', [Laragraph::class, 'handleErrors']));

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
    private function prevalidate(Schema $schema, string $query, ?DocumentNode $document, array $static, array &$rules): ?ExecutionResult
    {
        if (!$document instanceof DocumentNode) {
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

        if (count($this->validated) >= self::VALIDATED_DOCUMENTS) {
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
    private function partitionValidationRules(): array
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

        foreach ($this->validationRules->resolve() as $rule) {
            unset($static[$rule::class]);

            if (isset($seen[$rule::class])) {
                $dynamic[] = $rule;
            } else {
                $dynamic[$rule::class] = $rule;
                $seen[$rule::class]    = true;
            }
        }

        // Captured for QueryComplexityExtension — whichever instance actually
        // ends up in $dynamic, built-in or a user override of the same class.
        $complexityRule = $dynamic[QueryComplexity::class] ?? null;
        $this->complexityState->setCurrent($complexityRule instanceof QueryComplexity ? $complexityRule : null);

        return [array_values($static), array_values($dynamic)];
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
    private function wrapContext(mixed $context): mixed
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
}
