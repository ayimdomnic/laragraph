<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Benchmarks;

use Ayimdomnic\Laragraph\Tests\Support\Blog\Blog;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Warmup;

/**
 * Warm execution — a long-running worker (Octane) or any request after the
 * first: the schema and caches are built, so this measures resolving,
 * batching, validation and serialisation.
 */
#[BeforeMethods(['bootApplication', 'warm'])]
#[Groups(['execution'])]
#[Warmup(1)]
#[Iterations(5)]
class ExecutionBench extends BenchCase
{
    public function warm(): void
    {
        foreach (['{ thing1 { id f0 } }', Blog::LIST, Blog::NESTED, Blog::MUTATION] as $query) {
            $this->execute($query);
        }
    }

    /** The smallest operation: the fixed cost Laragraph adds to every request. */
    #[Revs(200)]
    public function benchTinyQuery(): void
    {
        $this->execute('{ thing1 { id f0 } }');
    }

    /** A page of 50 Eloquent models with casts and a custom resolver. */
    #[Revs(50)]
    public function benchPaginatedList(): void
    {
        $this->execute(Blog::LIST);
    }

    /**
     * 20 organizations → 100 authors → 400 posts → their authors, through
     * batchRelation() and a custom DataLoader.
     */
    #[Revs(10)]
    public function benchNestedBatchedRelations(): void
    {
        $this->execute(Blog::NESTED);
    }

    /** A mutation with argument validation rules. */
    #[Revs(100)]
    public function benchValidatedMutation(): void
    {
        $this->execute(Blog::MUTATION);
    }

    /** The paginated list through the HTTP kernel: routing, parsing, JSON. */
    #[Revs(50)]
    public function benchHttpRequest(): void
    {
        $kernel  = $this->app->make(Kernel::class);
        $request = Request::create('/graphql', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['query' => Blog::LIST], JSON_THROW_ON_ERROR));

        $response = $kernel->handle($request);
        $kernel->terminate($request, $response);
    }
}
