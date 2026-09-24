<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Performance;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\Support\DocumentCache;
use Ayimdomnic\Laragraph\Tests\Support\Blog\Blog;

/**
 * Memory budgets for long-running workers (Octane, RoadRunner, FrankenPHP,
 * queue workers): repeating operations must not accumulate memory — no
 * DataLoaders, models or documents may be kept from one request to the next.
 */
class MemoryBudgetTest extends PerformanceTestCase
{
    /** Growth tolerated over the measured runs, in bytes. */
    private const TOLERANCE = 512 * 1024;

    private function retainedAfter(int $runs, \Closure $operation): int
    {
        for ($i = 0; $i < 20; $i++) {
            $operation($i); // warm up: fill caches, load classes
        }

        gc_collect_cycles();
        $before = memory_get_usage();

        for ($i = 0; $i < $runs; $i++) {
            $operation($i);
        }

        gc_collect_cycles();

        return memory_get_usage() - $before;
    }

    public function test_repeating_a_batched_operation_keeps_no_memory(): void
    {
        Blog::seed(organizations: 10);

        $retained = $this->retainedAfter(200, fn() => $this->execute(Blog::NESTED));

        $this->assertLessThan(self::TOLERANCE, $retained, "{$retained} bytes retained over 200 executions");
    }

    public function test_distinct_documents_are_bounded_by_the_caches(): void
    {
        $document = fn(int $i): string => "{ alias{$i}: thing1 { id } }";

        // Fill the parse and validation caches completely, then keep going:
        // every further document evicts an older one, so memory must stay flat.
        $i = 0;
        for (; $i < Laragraph::VALIDATED_DOCUMENTS + DocumentCache::SIZE; $i++) {
            $this->execute($document($i));
        }

        $retained = $this->retainedAfter(Laragraph::VALIDATED_DOCUMENTS, function () use (&$i, $document): void {
            $this->execute($document($i++));
        });

        $this->assertLessThan(self::TOLERANCE, $retained, "{$retained} bytes retained over new documents once the caches were full");
    }

    public function test_large_documents_are_bounded_by_their_size(): void
    {
        // ~2 KB documents: the parse cache holds MAX_BYTES of source, not SIZE entries.
        $fields   = implode(' ', array_map(fn(int $t): string => "thing{$t} { id f0 f1 f2 f3 f4 f5 f6 f7 next { id f0 } }", range(0, 34)));
        $document = fn(int $i): string => "query Large{$i} { {$fields} }";

        $i = 0;
        for (; $i < 50; $i++) {
            $this->execute($document($i));
        }

        $retained = $this->retainedAfter(100, function () use (&$i, $document): void {
            $this->execute($document($i++));
        });

        $this->assertLessThan(self::TOLERANCE, $retained, "{$retained} bytes retained over new large documents");
    }
}
