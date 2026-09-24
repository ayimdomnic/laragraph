<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser;

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
     * Parsed documents are memoised briefly (one request typically asks
     * several times). Eviction is first-in-first-out, not least-recently-used:
     * the memo only needs to survive the handful of lookups within a request.
     */
    private const MEMO_SIZE = 32;

    /** @var array<string, array<string|int, self::QUERY|self::MUTATION|self::SUBSCRIPTION>> query hash => [operation name|index => type] */
    private static array $memo = [];

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
        $key = hash('xxh128', $query);

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        try {
            $document = Parser::parse($query, ['noLocation' => true]);
        } catch (\Throwable) {
            return [];
        }

        $operations = [];

        foreach ($document->definitions as $index => $definition) {
            if ($definition instanceof OperationDefinitionNode) {
                $operations[$definition->name->value ?? $index] = match ($definition->operation) {
                    self::MUTATION     => self::MUTATION,
                    self::SUBSCRIPTION => self::SUBSCRIPTION,
                    default            => self::QUERY,
                };
            }
        }

        if (count(self::$memo) >= self::MEMO_SIZE) {
            array_shift(self::$memo);
        }

        return self::$memo[$key] = $operations;
    }
}
