<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Unit\Support;

use Ayimdomnic\Laragraph\Support\DocumentCache;
use Ayimdomnic\Laragraph\Support\Operation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OperationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string|null, string|null}>
     */
    public static function documents(): iterable
    {
        yield 'shorthand query'           => ['{ users { id } }', null, Operation::QUERY];
        yield 'named query'               => ['query Users { users { id } }', null, Operation::QUERY];
        yield 'mutation'                  => ['mutation { createUser { id } }', null, Operation::MUTATION];
        yield 'subscription'              => ['subscription { onMessage }', null, Operation::SUBSCRIPTION];
        yield 'leading comment'           => ["# harmless?\nmutation { deleteAll }", null, Operation::MUTATION];
        yield 'fragment declared first'   => ['fragment F on User { id } mutation { a { ...F } }', null, Operation::MUTATION];
        yield 'selects named mutation'    => ['query A { a } mutation B { b }', 'B', Operation::MUTATION];
        yield 'selects named query'       => ['query A { a } mutation B { b }', 'A', Operation::QUERY];
        yield 'defaults to first'         => ['query A { a } mutation B { b }', null, Operation::QUERY];
        yield 'unknown operation name'    => ['query A { a }', 'Missing', null];
        yield 'syntax error'              => ['query {', null, null];
        yield 'fragments only'            => ['fragment F on User { id }', null, null];
        yield 'empty'                     => ['', null, null];
    }

    #[DataProvider('documents')]
    public function test_type(string $query, ?string $operationName, ?string $expected): void
    {
        $this->assertSame($expected, Operation::type($query, $operationName));
        // A second call is served from the memo and must agree.
        $this->assertSame($expected, Operation::type($query, $operationName));
    }

    public function test_predicates(): void
    {
        $this->assertTrue(Operation::isQuery('{ a }'));
        $this->assertFalse(Operation::isQuery('mutation { a }'));
        $this->assertTrue(Operation::isMutation('mutation { a }'));
        $this->assertFalse(Operation::isMutation('{ a }'));
        $this->assertTrue(Operation::isSubscription('subscription { a }'));
        $this->assertFalse(Operation::isSubscription('{ a }'));
    }

    public function test_documents_are_parsed_once_and_shared(): void
    {
        DocumentCache::flush();

        $this->assertSame(Operation::QUERY, Operation::type('{ shared }'));
        $this->assertSame(DocumentCache::parse('{ shared }'), DocumentCache::parse('{ shared }'));
    }

    public function test_the_document_cache_is_bounded_and_keeps_recent_documents(): void
    {
        DocumentCache::flush();
        $first = DocumentCache::parse('{ first }');

        for ($i = 0; $i < DocumentCache::SIZE * 2; $i++) {
            DocumentCache::parse("{ field{$i} }");
            DocumentCache::parse('{ first }'); // used constantly: never evicted
        }

        $documents = (new \ReflectionProperty(DocumentCache::class, 'documents'))->getValue();
        $this->assertCount(DocumentCache::SIZE, $documents);
        $this->assertSame($first, DocumentCache::parse('{ first }'));
    }

    public function test_syntax_errors_are_remembered_as_unparseable(): void
    {
        $this->assertNull(DocumentCache::parse('{ broken'));
        $this->assertNull(DocumentCache::parse('{ broken'));
        $this->assertNull(Operation::type('{ broken'));
    }
}
