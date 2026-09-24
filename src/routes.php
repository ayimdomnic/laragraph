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
    $methods = config('laragraph.route.methods', ['GET', 'POST']);

    // Default schema endpoint
    Route::match($methods, '/', [LaragraphController::class, 'query'])
        ->name('laragraph.query');

    // GraphiQL browser IDE
    // null = automatic: only served while app.debug is on (never in production).
    if (config('laragraph.graphiql.enabled') ?? config('app.debug')) {
        Route::get('/graphiql', [LaragraphController::class, 'graphiql'])
            ->middleware((array) config('laragraph.graphiql.middleware', []))
            ->name('laragraph.graphiql');
    }

    // Cancel a subscription: DELETE /graphql/subscriptions/{subscriberId}
    Route::delete('/subscriptions/{subscriberId}', [LaragraphController::class, 'unsubscribe'])
        ->name('laragraph.unsubscribe');

    // Named-schema endpoints: /graphql/{schemaName}
    Route::match($methods, '/{schemaName}', [LaragraphController::class, 'query'])
        ->name('laragraph.query.schema')
        ->where('schemaName', '[a-zA-Z0-9_-]+');
});
