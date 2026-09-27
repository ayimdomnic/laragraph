<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Benchmarks;

use Ayimdomnic\Laragraph\Execution\QueryExecutor;
use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Ayimdomnic\Laragraph\Schema\SchemaBuilder;
use Ayimdomnic\Laragraph\Schema\SchemaRegistry;
use Ayimdomnic\Laragraph\Support\DocumentCache;
use Ayimdomnic\Laragraph\Tests\Support\Blog\Blog;
use Ayimdomnic\Laragraph\Tests\Support\SyntheticSchema;
use Ayimdomnic\Laragraph\Types\TypeRegistry;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Facade;
use Orchestra\Testbench\Foundation\Application as Testbench;

/**
 * Boots a Laravel application with Laragraph for a benchmark: the blog
 * domain (on in-memory SQLite, seeded) plus a large synthetic schema, the
 * same fixtures the performance budget tests use.
 */
abstract class BenchCase
{
    protected const SYNTHETIC_TYPES = 300;

    protected Application $app;

    public function bootApplication(): void
    {
        $blog      = Blog::config();
        $synthetic = SyntheticSchema::define(static::SYNTHETIC_TYPES);

        $this->app = Testbench::create(options: ['extra' => ['providers' => [LaragraphServiceProvider::class]]]);

        config([
            'app.debug'                 => false,
            'cache.default'             => 'array',
            'database.default'          => 'sqlite',
            'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'laragraph.discover'        => ['types' => '', 'queries' => '', 'mutations' => '', 'subscriptions' => ''],
            'laragraph.types'           => [...$blog['types'], ...$synthetic['types']],
            'laragraph.schemas.default' => [
                'query'    => [...$blog['query'], ...$synthetic['query']],
                'mutation' => $blog['mutation'],
            ],
        ]);

        Blog::migrate();
        Blog::seed(organizations: 20, authors: 5, posts: 4);
    }

    /**
     * A fresh Laragraph, as a PHP-FPM request would have: no schema, no
     * types, no parsed or validated documents.
     *
     * Laragraph itself is a thin facade — its schema/type/document caches
     * live in the singleton collaborators the container hands it
     * (TypeRegistry, SchemaBuilder, SchemaRegistry, QueryExecutor), so
     * forgetting just Laragraph's own singleton isn't enough; every one of
     * those has to be forgotten too so the container rebuilds the whole
     * graph from scratch, exactly like a fresh worker would.
     */
    protected function freshLaragraph(): Laragraph
    {
        foreach ([
            'laragraph',
            TypeRegistry::class,
            SchemaBuilder::class,
            SchemaRegistry::class,
            QueryExecutor::class,
        ] as $abstract) {
            $this->app->forgetInstance($abstract);
        }

        Facade::clearResolvedInstance('laragraph');
        DocumentCache::flush();

        return $this->app->make('laragraph');
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(string $query): array
    {
        $result = $this->app->make('laragraph')->execute($query);

        if (!empty($result['errors'])) {
            throw new \RuntimeException('Benchmark operation failed: ' . json_encode($result['errors']));
        }

        return $result;
    }
}
