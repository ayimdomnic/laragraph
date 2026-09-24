<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Benchmarks;

use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * The per-request cost under PHP-FPM, where every request starts with no
 * schema: each revolution uses a fresh Laragraph on a 300-type schema.
 */
#[BeforeMethods('bootApplication')]
#[Groups(['schema'])]
#[Warmup(1)]
#[Iterations(5)]
class SchemaBench extends BenchCase
{
    /** Creating the schema object — lazy, so it should cost almost nothing. */
    #[Revs(50)]
    public function benchBuildSchema(): void
    {
        $this->freshLaragraph()->schema();
    }

    /** A cold request: build the schema and run one small operation on it. */
    #[Revs(30)]
    public function benchColdRequest(): void
    {
        $this->freshLaragraph();
        $this->execute('{ thing7 { id f0 next { id f1 } } }');
    }

    /**
     * Building every type: what introspection, `laragraph:validate` and
     * `laragraph:schema:export` pay.
     */
    #[Revs(3)]
    public function benchFullTypeMap(): void
    {
        $this->freshLaragraph()->schema()->getTypeMap();
    }
}
