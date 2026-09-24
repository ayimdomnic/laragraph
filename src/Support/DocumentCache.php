<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Support;

use GraphQL\Error\SyntaxError;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\Parser;

/**
 * Parses GraphQL documents once and shares the AST.
 *
 * A request needs the parsed document several times — to classify the
 * operation (GET mutations, subscriptions, response caching) and to execute
 * it — and long-running workers (Octane, RoadRunner, FrankenPHP) see the
 * same documents over and over. The AST is never mutated by validation or
 * execution, so one parse can serve all of them.
 *
 * Least-recently-used entries are evicted beyond {@see self::SIZE}.
 */
final class DocumentCache
{
    public const SIZE = 100;

    /** @var array<string, DocumentNode|false> query hash => AST, or false for a syntax error */
    private static array $documents = [];

    /**
     * The parsed document, or null when $query is not valid GraphQL syntax.
     */
    public static function parse(string $query): ?DocumentNode
    {
        $key = hash('xxh128', $query);

        if (isset(self::$documents[$key])) {
            // Move to the end: most recently used.
            $document = self::$documents[$key];
            unset(self::$documents[$key]);
            self::$documents[$key] = $document;

            return $document ?: null;
        }

        try {
            $document = Parser::parse($query);
        } catch (SyntaxError) {
            $document = false;
        }

        if (count(self::$documents) >= self::SIZE) {
            unset(self::$documents[array_key_first(self::$documents)]);
        }

        self::$documents[$key] = $document;

        return $document ?: null;
    }

    public static function flush(): void
    {
        self::$documents = [];
    }
}
