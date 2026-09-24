<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Benchmarks;

use Ayimdomnic\Laragraph\Laragraph;
use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Ayimdomnic\Laragraph\Support\DocumentCache;
use Ayimdomnic\Laragraph\Tests\Support\Blog\Blog;
use Ayimdomnic\Laragraph\Tests\Support\SyntheticSchema;
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
     */
    protected function freshLaragraph(): Laragraph
    {
        $laragraph = new Laragraph($this->app);

        $this->app->instance('laragraph', $laragraph);
        Facade::clearResolvedInstance('laragraph');
        DocumentCache::flush();

        return $laragraph;
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
