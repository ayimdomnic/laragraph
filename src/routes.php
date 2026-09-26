<?php

declare(strict_types=1);

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */

use Ayimdomnic\Laragraph\Controllers\LaragraphController;
use Illuminate\Support\Facades\Route;

$routeConfig = array_filter([
    'prefix'     => config('laragraph.route.prefix', 'graphql'),
    'middleware' => config('laragraph.route.middleware', []),
]);

Route::group($routeConfig, function (): void {
    $methods  = (array) config('laragraph.route.methods', ['GET', 'POST']);
    $schemas  = (array) config('laragraph.schemas', []);
    $default  = (string) config('laragraph.default_schema', 'default');
    $graphiql = (bool) (config('laragraph.graphiql.enabled') ?? config('app.debug'));

    // Each schema's own `method` and `middleware` settings apply to its endpoint(s).
    $schemaMethods    = static fn(string $name): array => (array) ($schemas[$name]['method'] ?? $methods);
    $schemaMiddleware = static fn(string $name): array => (array) ($schemas[$name]['middleware'] ?? []);

    // Default schema endpoint: /graphql
    Route::match($schemaMethods($default), '/', [LaragraphController::class, 'query'])
        ->defaults('schemaName', $default)
        ->middleware($schemaMiddleware($default))
        ->name('laragraph.query');

    // GraphiQL browser IDE
    // null = automatic: only served while app.debug is on (never in production).
    if ($graphiql) {
        Route::get('/graphiql', [LaragraphController::class, 'graphiql'])
            ->middleware((array) config('laragraph.graphiql.middleware', []))
            ->name('laragraph.graphiql');
    }

    // Cancel a subscription: DELETE /graphql/subscriptions/{subscriberId}
    Route::delete('/subscriptions/{subscriberId}', [LaragraphController::class, 'unsubscribe'])
        ->name('laragraph.unsubscribe');

    // Server-Sent Events transport: GET /graphql/subscriptions/{subscriberId}/stream
    // (only reachable when laragraph.subscriptions.driver is 'sse').
    Route::get('/subscriptions/{subscriberId}/stream', [LaragraphController::class, 'stream'])
        ->name('laragraph.subscriptions.stream');

    // Named-schema endpoints: /graphql/{schemaName}, one route per configured
    // schema so that schema's middleware (e.g. ['auth:api']) guards it.
    foreach (array_keys($schemas) as $name) {
        if (!is_string($name) || preg_match('/^[a-zA-Z0-9_-]+$/', $name) !== 1 || ($graphiql && $name === 'graphiql')) {
            continue;
        }

        Route::match($schemaMethods($name), "/{$name}", [LaragraphController::class, 'query'])
            ->defaults('schemaName', $name)
            ->middleware($schemaMiddleware($name))
            ->name("laragraph.query.{$name}");
    }

    // Any other name: answered with a SCHEMA_NOT_FOUND error by the controller.
    // Configured names are excluded, so a request using a method a schema does
    // not allow gets a 405 instead of slipping past that schema's middleware.
    $configured = implode('|', array_map(
        static fn(int|string $name): string => preg_quote((string) $name, '#'),
        array_keys($schemas),
    ));

    Route::match($methods, '/{schemaName}', [LaragraphController::class, 'query'])
        ->name('laragraph.query.schema')
        ->where('schemaName', ($configured === '' ? '' : "(?!(?:{$configured})$)") . '[a-zA-Z0-9_-]+');
});
