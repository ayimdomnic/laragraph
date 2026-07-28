<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Middleware;

use GraphQL\Type\Definition\ResolveInfo;

/**
 * Executes a stack of {@see FieldMiddlewareInterface} implementations around
 * a field resolver callable.
 *
 * Middleware run in declared order: the first element is the outermost wrapper
 * (it runs before all others and sees the return value of all others).
 */
final class FieldMiddlewarePipeline
{
    /** @param list<FieldMiddlewareInterface> $middleware */
    public function __construct(private readonly array $middleware) {}

    /**
     * Run the middleware stack and ultimately invoke `$resolver`.
     *
     * @param callable(mixed, array<string,mixed>, mixed, ResolveInfo): mixed $resolver
     */
    public function run(
        mixed $root,
        array $args,
        mixed $context,
        ResolveInfo $info,
        callable $resolver,
    ): mixed {
        $pipeline = array_reduce(
            array_reverse($this->middleware),
            static function (callable $carry, FieldMiddlewareInterface $mw) use ($root, $args, $context, $info): callable {
                return static fn () => $mw->handle($root, $args, $context, $info, $carry);
            },
            static fn () => $resolver($root, $args, $context, $info),
        );

        return ($pipeline)();
    }
}
