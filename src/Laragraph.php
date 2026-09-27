<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Ayimdomnic\Laragraph\Contracts\BatchProcessorInterface;
use Ayimdomnic\Laragraph\Contracts\QueryExecutorInterface;
use Ayimdomnic\Laragraph\Contracts\SchemaRegistryInterface;
use Ayimdomnic\Laragraph\Contracts\SubscriptionManagerInterface;
use Ayimdomnic\Laragraph\Contracts\TypeRegistryInterface;
use Ayimdomnic\Laragraph\Contracts\ValidationRuleRegistryInterface;
use Ayimdomnic\Laragraph\Errors\ErrorFormatter;
use Ayimdomnic\Laragraph\Exceptions\BatchingDisabledException;
use Ayimdomnic\Laragraph\Exceptions\BatchLimitExceededException;
use Ayimdomnic\Laragraph\Exceptions\SchemaException;
use Ayimdomnic\Laragraph\Execution\QueryExecutor;
use Ayimdomnic\Laragraph\Subscriptions\BroadcastSubscriptionUpdates;
use GraphQL\Error\Error;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ValidationRule;
use Illuminate\Contracts\Container\Container;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
class Laragraph
{
    /**
     * How many validated documents a worker remembers (a key is ~60 bytes).
     *
     * @see QueryExecutor::VALIDATED_DOCUMENTS the canonical definition —
     *      kept accessible here too since it predates the execution engine
     *      moving into its own class.
     */
    public const VALIDATED_DOCUMENTS = QueryExecutor::VALIDATED_DOCUMENTS;

    public function __construct(
        protected readonly Container $container,
        protected readonly SchemaRegistryInterface $schemaRegistry,
        protected readonly TypeRegistryInterface $typeRegistry,
        protected readonly QueryExecutorInterface $queryExecutor,
    ) {}

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
        return $this->schemaRegistry->schema($name);
    }

    /**
     * Execute a batch of GraphQL operations and return an indexed array of results.
     *
     * Delegates to {@see BatchProcessorInterface::process()}. Batching must be
     * enabled via `laragraph.batching.enabled` in the config or this method throws.
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
        return $this->container->make(BatchProcessorInterface::class)->process($operations, $context, $schemaName);
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
        return $this->queryExecutor->execute($query, $context, $variables, $operationName, $schemaName, $rootValue);
    }

    /**
     * Execute a GraphQL query and return the raw ExecutionResult.
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
        return $this->queryExecutor->executeQuery($query, $context, $variables, $operationName, $schemaName, $rootValue);
    }

    /**
     * Re-execute every subscriber's original query on a channel with
     * $payload as the root value, and push each result to that subscriber.
     *
     * @return int  The number of subscribers notified.
     */
    public function broadcast(string $channel, mixed $payload = null): int
    {
        return $this->container->make(SubscriptionManagerInterface::class)->broadcast($channel, $payload);
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
        return $this->container->make(SubscriptionManagerInterface::class)->unsubscribe($subscriberId);
    }

    /**
     * Register a custom GraphQL validation rule.
     *
     * The rule is added to the {@see ValidationRuleRegistryInterface} singleton
     * and will be applied to every subsequent execution.
     *
     * @param string|ValidationRule $rule FQCN or instance.
     */
    public function addValidationRule(string|ValidationRule $rule): void
    {
        $this->container->make(ValidationRuleRegistryInterface::class)->add($rule);
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
        return $this->typeRegistry->addType($class, $alias);
    }

    /**
     * Resolve a type instance by alias/name.
     *
     * @throws \InvalidArgumentException
     */
    public function type(string $name, bool $fresh = false): Type
    {
        return $this->typeRegistry->type($name, $fresh);
    }

    /**
     * Resolve a registered type by its GraphQL name rather than its alias.
     */
    public function typeByName(string $graphqlName): ?Type
    {
        return $this->typeRegistry->typeByName($graphqlName);
    }

    /** Return all registered type class aliases.
     *
     * @return array<string, string>
     */
    public function getTypes(): array
    {
        return $this->typeRegistry->getTypes();
    }

    /** Check whether a type name is registered. */
    public function hasType(string $name): bool
    {
        return $this->typeRegistry->hasType($name);
    }

    // -------------------------------------------------------------------------
    // Error handling (static — usable as callables in config)
    // -------------------------------------------------------------------------

    /**
     * Default error formatter — exposed via config('laragraph.error_formatter').
     *
     * Kept as a public static method under this exact name because
     * config/laragraph.php is published verbatim into every consumer app as
     * `'error_formatter' => [Laragraph::class, 'formatError']` — the actual
     * logic lives in {@see ErrorFormatter}, independently unit-testable.
     *
     * @return array<string, mixed>
     */
    public static function formatError(Error $error): array
    {
        return ErrorFormatter::format($error);
    }

    /**
     * Default errors handler — called once with the full errors array.
     *
     * Kept under this exact name for the same reason as {@see formatError()}.
     *
     * @param array<int, Error> $errors
     * @param callable(Error):array<string, mixed> $formatter
     * @return array<int, array<string, mixed>>
     */
    public static function handleErrors(array $errors, callable $formatter): array
    {
        return ErrorFormatter::handle($errors, $formatter);
    }
}
