<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * Parsing and validation of a large document (20 root fields, 40
 * fragments, a variable): once with the document already seen by the worker, and once
 * with a document it has never seen.
 */
#[BeforeMethods(['bootApplication', 'warm'])]
#[Groups(['documents'])]
#[Warmup(1)]
#[Iterations(5)]
class DocumentBench extends BenchCase
{
    private int $counter = 0;

    public function warm(): void
    {
        $this->execute($this->document('warm'));
    }

    private function document(string $suffix): string
    {
        $fields = $fragments = '';

        for ($i = 0; $i < 20; $i++) {
            $next       = $i + 1;
            $fields    .= "  r{$i}_{$suffix}: thing{$i}(id: \$id) { ...T{$i} next { id f0 } }\n";
            $fragments .= "fragment T{$i} on Thing{$i} { id f0 f1 f2 f3 f4 f5 f6 f7 next { ...N{$next} } }\n"
                . "fragment N{$next} on Thing{$next} { id f1 }\n";
        }

        return "query Large_{$suffix}(\$id: ID) {\n{$fields}}\n{$fragments}";
    }

    /** The common case: clients send the same operations again and again. */
    #[Revs(50)]
    public function benchRepeatedDocument(): void
    {
        $this->execute($this->document('repeated'));
    }

    /** A document the worker has never seen: parsed and fully validated. */
    #[Revs(50)]
    public function benchNewDocument(): void
    {
        $this->execute($this->document((string) ++$this->counter));
    }
}
