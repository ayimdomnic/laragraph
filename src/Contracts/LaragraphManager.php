<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Contracts;

use Ayimdomnic\Laragraph\Laragraph;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use GraphQL\Validator\Rules\ValidationRule;

/**
 * Mirrors {@see Laragraph}'s public instance API.
 *
 * Optional: application code that wants to depend on an abstraction rather
 * than the concrete manager class may type-hint this instead — it is bound
 * as a container alias for `Laragraph::class` (see `LaragraphServiceProvider`).
 * The `Laragraph` facade and the concrete class are unaffected either way.
 */
interface LaragraphManager
{
    public function schema(?string $name = null): Schema;

    /**
     * @param array<int, array{query?: string, variables?: mixed, operationName?: string|null}> $operations
     * @return array<int, array<string, mixed>>
     */
    public function executeBatch(array $operations, mixed $context = null, string $schemaName = 'default'): array;

    /**
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
    ): array;

    /**
     * @param array<string, mixed> $variables
     */
    public function executeQuery(
        string $query,
        mixed $context = null,
        array $variables = [],
        ?string $operationName = null,
        ?string $schemaName = null,
        mixed $rootValue = null,
    ): ExecutionResult;

    public function broadcast(string $channel, mixed $payload = null): int;

    public function broadcastLater(string $channel, mixed $payload = null): void;

    public function unsubscribe(string $subscriberId): bool;

    public function addValidationRule(string|ValidationRule $rule): void;

    public function addType(string|Type $class, ?string $alias = null): string;

    public function type(string $name, bool $fresh = false): Type;

    public function typeByName(string $graphqlName): ?Type;

    /**
     * @return array<string, string>
     */
    public function getTypes(): array;

    public function hasType(string $name): bool;
}
