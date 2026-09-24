<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests\Performance;

use Ayimdomnic\Laragraph\Tests\Support\Blog\Blog;
use Ayimdomnic\Laragraph\Tests\Support\SyntheticSchema;
use Ayimdomnic\Laragraph\Tests\TestCase;
use Illuminate\Support\Facades\DB;

/**
 * Performance budgets: tests that pin down how much *work* an operation
 * does — SQL queries, classes built, memory kept — rather than how long it
 * takes. Work counts are deterministic, so these never flake, and they run
 * with the rest of the suite; the timing benchmarks in /benchmarks
 * complement them.
 *
 * The default schema is the blog domain plus a 300-type synthetic schema,
 * on an in-memory SQLite database.
 */
abstract class PerformanceTestCase extends TestCase
{
    protected const SYNTHETIC_TYPES = 300;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $blog      = Blog::config();
        $synthetic = SyntheticSchema::define(self::SYNTHETIC_TYPES);

        $app['config']->set('app.debug', false);
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('laragraph.types', [...$blog['types'], ...$synthetic['types']]);
        $app['config']->set('laragraph.schemas.default', [
            'query'    => [...$blog['query'], ...$synthetic['query']],
            'mutation' => $blog['mutation'],
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Blog::migrate();
        SyntheticSchema::$constructed = [];
    }

    /**
     * Run $callback and return the SQL statements it executed.
     *
     * @return list<string>
     */
    protected function queriesDuring(\Closure $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $callback();
        } finally {
            DB::disableQueryLog();
        }

        return array_column(DB::getQueryLog(), 'query');
    }

    /**
     * @param array<string, mixed> $variables
     * @return array<string, mixed>
     */
    protected function execute(string $query, array $variables = []): array
    {
        $result = app('laragraph')->execute($query, variables: $variables);

        $this->assertArrayNotHasKey('errors', $result, json_encode($result['errors'] ?? []) ?: '');

        return $result;
    }
}
