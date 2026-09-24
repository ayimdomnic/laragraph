<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Language\AST\OperationDefinitionNode;

/**
 * Determines which kind of operation a GraphQL document will execute.
 *
 * The answer comes from the parsed AST — not from string matching — so
 * comments, whitespace, fragments declared first and multi-operation
 * documents selected via `operationName` are all classified correctly.
 */
final class Operation
{
    public const QUERY        = 'query';
    public const MUTATION     = 'mutation';
    public const SUBSCRIPTION = 'subscription';

    /**
     * The type of the operation that $operationName selects in $query — or of
     * the first operation when no name is given. Null when the document does
     * not parse or the named operation does not exist.
     *
     * @return self::QUERY|self::MUTATION|self::SUBSCRIPTION|null
     */
    public static function type(string $query, ?string $operationName = null): ?string
    {
        $operations = self::operations($query);

        if ($operationName === null) {
            return $operations === [] ? null : $operations[array_key_first($operations)];
        }

        return $operations[$operationName] ?? null;
    }

    public static function isQuery(string $query, ?string $operationName = null): bool
    {
        return self::type($query, $operationName) === self::QUERY;
    }

    public static function isMutation(string $query, ?string $operationName = null): bool
    {
        return self::type($query, $operationName) === self::MUTATION;
    }

    public static function isSubscription(string $query, ?string $operationName = null): bool
    {
        return self::type($query, $operationName) === self::SUBSCRIPTION;
    }

    /**
     * @return array<string|int, self::QUERY|self::MUTATION|self::SUBSCRIPTION> Operation name (or position, when anonymous) => type.
     */
    private static function operations(string $query): array
    {
        $operations = [];

        foreach (DocumentCache::parse($query)->definitions ?? [] as $index => $definition) {
            if ($definition instanceof OperationDefinitionNode) {
                $operations[$definition->name->value ?? $index] = match ($definition->operation) {
                    self::MUTATION     => self::MUTATION,
                    self::SUBSCRIPTION => self::SUBSCRIPTION,
                    default            => self::QUERY,
                };
            }
        }

        return $operations;
    }
}
