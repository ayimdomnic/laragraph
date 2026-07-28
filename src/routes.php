<?php

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

Route::group($routeConfig, function () {
    $methods = config('laragraph.route.methods', ['GET', 'POST']);

    // Default schema endpoint
    Route::match($methods, '/', [LaragraphController::class, 'query'])
        ->name('laragraph.query');

    // GraphiQL browser IDE
    if (config('laragraph.graphiql.enabled', true)) {
        Route::get('/graphiql', [LaragraphController::class, 'graphiql'])
            ->middleware(config('laragraph.graphiql.middleware', []))
            ->name('laragraph.graphiql');
    }

    // Named-schema endpoints: /graphql/{schemaName}
    Route::match($methods, '/{schemaName}', [LaragraphController::class, 'query'])
        ->name('laragraph.query.schema')
        ->where('schemaName', '[a-zA-Z0-9_-]+');
});