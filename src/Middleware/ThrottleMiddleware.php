<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Middleware;

use GraphQL\Error\Error;
use GraphQL\Type\Definition\ResolveInfo;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Rate-limits field resolutions using Laravel's built-in RateLimiter.
 *
 * Usage — apply per-field:
 * ```php
 * public function middleware(): array
 * {
 *     return [new ThrottleMiddleware(maxAttempts: 10, decaySeconds: 60)];
 * }
 * ```
 *
 * The rate-limit key is scoped to the field name and the authenticated user ID
 * (or the client IP address for guest requests).
 */
final class ThrottleMiddleware implements FieldMiddlewareInterface
{
    public function __construct(
        private readonly int $maxAttempts = 60,
        private readonly int $decaySeconds = 60,
    ) {}

    public function handle(
        mixed $root,
        array $args,
        mixed $context,
        ResolveInfo $info,
        callable $next,
    ): mixed {
        $key = $this->buildKey($info);

        if (RateLimiter::tooManyAttempts($key, $this->maxAttempts)) {
            $availableIn = RateLimiter::availableIn($key);
            throw new Error(
                "Too many requests for field [{$info->fieldName}]. Retry after {$availableIn}s."
            );
        }

        RateLimiter::hit($key, $this->decaySeconds);

        return $next();
    }

    private function buildKey(ResolveInfo $info): string
    {
        $userId = auth()->id() ?? request()->ip() ?? 'guest';

        return "laragraph_throttle:{$info->fieldName}:{$userId}";
    }
}
