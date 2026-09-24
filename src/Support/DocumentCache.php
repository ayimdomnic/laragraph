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
 * An AST takes a few hundred times the memory of its source text, so the
 * cache is bounded both by entries ({@see self::SIZE}) and by the combined
 * length of the cached documents ({@see self::MAX_BYTES}); least-recently-
 * used entries are evicted first. The latest document is always kept, so a
 * request never parses its own document twice, however large it is.
 */
final class DocumentCache
{
    public const SIZE = 100;

    /** Combined length of the cached documents' source text. */
    public const MAX_BYTES = 64 * 1024;

    /** @var array<string, DocumentNode|false> query hash => AST, or false for a syntax error */
    private static array $documents = [];

    /** @var array<string, int> query hash => source length */
    private static array $lengths = [];

    private static int $bytes = 0;

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

        $length = strlen($query);

        while (self::$documents !== [] && (count(self::$documents) >= self::SIZE || self::$bytes + $length > self::MAX_BYTES)) {
            $oldest = (string) array_key_first(self::$documents);

            self::$bytes -= self::$lengths[$oldest];
            unset(self::$documents[$oldest], self::$lengths[$oldest]);
        }

        self::$documents[$key] = $document;
        self::$lengths[$key]   = $length;
        self::$bytes          += $length;

        return $document ?: null;
    }

    public static function flush(): void
    {
        self::$documents = [];
        self::$lengths   = [];
        self::$bytes     = 0;
    }
}
