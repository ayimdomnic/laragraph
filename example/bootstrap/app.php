<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // /broadcasting/auth for private channels. Laragraph registers the rule for
    // its subscriber channels itself; clients authenticate with their JWT.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', ['middleware' => ['auth:api']])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Errors raised by route middleware (e.g. auth on the admin schema) as JSON.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'graphql', 'graphql/*'),
        );
    })->create();
