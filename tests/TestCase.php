<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Tests;

use Ayimdomnic\Laragraph\LaragraphServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    /**
     * Load the Laragraph service provider.
     */
    protected function getPackageProviders($app): array
    {
        return [
            LaragraphServiceProvider::class,
        ];
    }

    /**
     * Register the Laragraph facade alias.
     */
    protected function getPackageAliases($app): array
    {
        return [
            'Laragraph' => \Ayimdomnic\Laragraph\Facades\Laragraph::class,
        ];
    }

    /**
     * Define environment setup.
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('laragraph.default_schema', 'default');
        $app['config']->set('laragraph.schemas.default', [
            'query'        => [],
            'mutation'     => [],
            'subscription' => [],
            'middleware'   => [],
        ]);
        $app['config']->set('laragraph.types', []);
        $app['config']->set('laragraph.graphiql.enabled', true);
        $app['config']->set('app.debug', true);
    }

    /**
     * Helper: send a GraphQL request to the HTTP endpoint.
     */
    protected function graphql(string $query, array $variables = [], string $schema = 'default'): array
    {
        $path = $schema === 'default' ? '/graphql' : "/graphql/{$schema}";

        return $this->postJson($path, [
            'query'     => $query,
            'variables' => $variables,
        ])->json();
    }
}
