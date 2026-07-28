<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\DataLoader;

use Overblog\DataLoader\DataLoader;
use Overblog\PromiseAdapter\Adapter\WebonyxGraphQLSyncPromiseAdapter;

/**
 * Per-request registry of named DataLoader instances.
 *
 * Injected into the GraphQL execution context automatically so resolvers can
 * batch their database calls without N+1 queries.
 *
 * ## Usage inside a resolver
 *
 * ```php
 * public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
 * {
 *     // $context->dataLoaders is the DataLoaderRegistry
 *     return $context->dataLoaders->get(UserLoader::class)->load($root->user_id);
 * }
 * ```
 *
 * ## Defining a loader
 *
 * Extend {@see BatchResolver} and bind it:
 *
 * ```php
 * class UserLoader extends BatchResolver
 * {
 *     public function batch(array $ids): array
 *     {
 *         return User::whereIn('id', $ids)
 *             ->get()
 *             ->keyBy('id')
 *             ->toArray();
 *     }
 * }
 * ```
 *
 * @see BatchResolver
 */
final class DataLoaderRegistry
{
    /** @var array<string, DataLoader> */
    private array $loaders = [];

    /**
     * Retrieve (or lazily create) a named DataLoader.
     *
     * The first call with a given FQCN will instantiate the loader; subsequent
     * calls within the same request return the same instance.
     *
     * @param  class-string<BatchResolver>  $class  FQCN of a BatchResolver subclass.
     */
    public function get(string $class): DataLoader
    {
        if (!isset($this->loaders[$class])) {
            $resolver = app($class);
            $adapter  = new WebonyxGraphQLSyncPromiseAdapter();

            $this->loaders[$class] = new DataLoader(
                function (array $keys) use ($resolver, $adapter) {
                    return $adapter->createAll($resolver->batch($keys));
                },
                $adapter,
            );
        }

        return $this->loaders[$class];
    }

    /**
     * Clear all loader caches (call between requests or in tests).
     */
    public function clear(): void
    {
        $this->loaders = [];
    }
}
