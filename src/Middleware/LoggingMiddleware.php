<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Middleware;

use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\Log;

/**
 * Logs each field resolution (field name + elapsed time) to a Laravel log channel.
 *
 * Enable globally via config so every field is logged:
 * ```php
 * 'middleware' => [
 *     \Ayimdomnic\Laragraph\Middleware\LoggingMiddleware::class,
 * ],
 * ```
 *
 * Or apply per-field:
 * ```php
 * public function middleware(): array
 * {
 *     return [new \Ayimdomnic\Laragraph\Middleware\LoggingMiddleware()];
 * }
 * ```
 *
 * Configure the target channel via `laragraph.logging.channel`; `null` means
 * the application's default log channel.
 */
final class LoggingMiddleware implements FieldMiddlewareInterface
{
    public function handle(
        mixed $root,
        array $args,
        mixed $context,
        ResolveInfo $info,
        callable $next,
    ): mixed {
        $start  = microtime(true);
        $result = $next();
        $ms     = round((microtime(true) - $start) * 1000, 2);

        $message = "GraphQL: [{$info->fieldName}] resolved in {$ms}ms";
        $context = ['field' => $info->fieldName, 'elapsed_ms' => $ms];

        $channel = config('laragraph.logging.channel');

        if ($channel !== null && $channel !== '') {
            Log::channel($channel)->debug($message, $context);
        } else {
            Log::debug($message, $context);
        }

        return $result;
    }
}
