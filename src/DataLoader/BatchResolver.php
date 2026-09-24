<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\DataLoader;

/**
 * Abstract base class for DataLoader batch resolvers.
 *
 * Extend this class and implement `batch()` to define how a group of keys
 * should be resolved in a single round-trip to your data source.
 *
 * The DataLoaderRegistry automatically wraps your batch function in an
 * `overblog/dataloader-php` DataLoader, providing deferred, batched loading
 * with per-key caching within each request.
 *
 * ## Example — batching Eloquent models
 *
 * ```php
 * namespace App\DataLoaders;
 *
 * use Ayimdomnic\Laragraph\DataLoader\BatchResolver;
 * use App\Models\User;
 *
 * class UserLoader extends BatchResolver
 * {
 *     /**
 *      * Receive an array of user IDs and return one User (or null) per id.
 *      *
 *      * IMPORTANT: return a list with exactly one value per key, in the
 *      * same order as $keys — DataLoader matches results by position.
 *      * {@literal @}param array<int|string> $keys
 *      * {@literal @}return array<mixed>
 *      *\/
 *     public function batch(array $keys): array
 *     {
 *         $users = User::whereIn('id', $keys)->get()->keyBy('id');
 *
 *         return array_map(fn($id) => $users->get($id), $keys);
 *     }
 * }
 * ```
 *
 * ## Wiring in a resolver
 *
 * ```php
 * use App\DataLoaders\UserLoader;
 *
 * public function resolve(mixed $root, array $args, mixed $context, ResolveInfo $info): mixed
 * {
 *     return $context->dataLoaders->get(UserLoader::class)->load($root->user_id);
 * }
 * ```
 *
 * @see DataLoaderRegistry
 */
abstract class BatchResolver
{
    /**
     * Resolve a batch of keys in a single call.
     *
     * @param  array<int|string>  $keys  Unique keys collected by the DataLoader.
     * @return array<mixed>             One resolved value per key, in the same
     *                                  order as $keys (null for a missing key).
     */
    abstract public function batch(array $keys): array;
}
