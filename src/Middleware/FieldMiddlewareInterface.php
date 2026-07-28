<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Middleware;

use GraphQL\Type\Definition\ResolveInfo;

interface FieldMiddlewareInterface
{
    /**
     * Handle field resolution.
     *
     * Call `$next()` to continue down the pipeline and return its value.
     * Return early (without calling `$next`) to short-circuit resolution —
     * useful for throttling, per-field caching, or access control layers.
     *
     * @param callable(): mixed $next
     */
    public function handle(
        mixed $root,
        array $args,
        mixed $context,
        ResolveInfo $info,
        callable $next,
    ): mixed;
}
