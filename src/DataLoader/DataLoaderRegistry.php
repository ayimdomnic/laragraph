<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\DataLoader;

use Illuminate\Database\Eloquent\Model;
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
 * Or, for any kind of context (including custom objects that forbid dynamic
 * properties), look the registry up explicitly:
 *
 * ```php
 * DataLoaderRegistry::for($context)?->get(UserLoader::class)->load($root->user_id);
 * ```
 *
 * @see BatchResolver
 */
final class DataLoaderRegistry
{
    /** @var array<string, DataLoader> */
    private array $loaders = [];

    /** @var \WeakMap<object, self>|null Registries attached to object contexts. */
    private static ?\WeakMap $attached = null;

    /** @var array<class-string, bool> Memoised "may this class take a dataLoaders property?" checks. */
    private static array $writable = [];

    /**
     * Attach $registry to an object execution context.
     *
     * The registry is always retrievable through {@see for()}. It is also
     * exposed as `$context->dataLoaders` when the context declares that
     * property or permits dynamic properties (stdClass, #[AllowDynamicProperties]);
     * other objects are never given a dynamic property, which PHP 8.2+ deprecates.
     */
    public static function attach(object $context, self $registry): void
    {
        self::$attached ??= new \WeakMap();
        self::$attached[$context] = $registry;

        if (self::acceptsProperty($context)) {
            // acceptsProperty() guarantees the property is declared or dynamic properties are allowed.
            $context->dataLoaders = $registry; // @phpstan-ignore property.notFound
        }
    }

    /**
     * The registry attached to an execution context, or null when there is none.
     */
    public static function for(mixed $context): ?self
    {
        if (is_array($context)) {
            $registry = $context['dataLoaders'] ?? null;

            return $registry instanceof self ? $registry : null;
        }

        if (!is_object($context)) {
            return null;
        }

        return self::$attached[$context] ?? null;
    }

    private static function acceptsProperty(object $context): bool
    {
        $class = $context::class;

        if (isset(self::$writable[$class])) {
            return self::$writable[$class];
        }

        if (property_exists($context, 'dataLoaders')) {
            return self::$writable[$class] = true;
        }

        for ($reflection = new \ReflectionClass($context); $reflection !== false; $reflection = $reflection->getParentClass()) {
            if ($reflection->getName() === \stdClass::class || $reflection->getAttributes(\AllowDynamicProperties::class) !== []) {
                return self::$writable[$class] = true;
            }
        }

        return self::$writable[$class] = false;
    }

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
        return $this->getOrRegister($class, fn() => app($class));
    }

    /**
     * Retrieve (or lazily create) a DataLoader for an arbitrary cache key.
     *
     * Unlike {@see get()}, the resolver instance is built by an explicit
     * factory rather than resolved from the container with no arguments — use
     * this for parameterized loaders (e.g. one loader per model+relation
     * combination) that {@see get()} cannot construct on its own.
     *
     * @param  \Closure(): BatchResolver  $factory
     */
    public function getOrRegister(string $key, \Closure $factory): DataLoader
    {
        if (!isset($this->loaders[$key])) {
            $resolver = $factory();
            $adapter  = new WebonyxGraphQLSyncPromiseAdapter();

            $this->loaders[$key] = new DataLoader(
                fn(array $keys) => $adapter->createAll($resolver->batch($keys)),
                $adapter,
            );
        }

        return $this->loaders[$key];
    }

    /**
     * Retrieve (or lazily create) a DataLoader that batches an Eloquent
     * relation via the model's own eager-loading machinery, keyed by parent
     * primary key. See {@see EloquentRelationLoader}.
     *
     * @param class-string<Model> $modelClass
     */
    public function relation(string $modelClass, string $relation): DataLoader
    {
        return $this->getOrRegister(
            "relation::{$modelClass}::{$relation}",
            fn(): EloquentRelationLoader => new EloquentRelationLoader($modelClass, $relation),
        );
    }

    /**
     * Release every loader and its cached results.
     *
     * overblog/dataloader-php tracks each DataLoader in a static list that is
     * only pruned from DataLoader::__destruct() — which never runs on its own,
     * because that static list still references the loader. Without an
     * explicit release every request leaks its loaders (and their cached
     * rows), and DataLoader::await() walks an ever-growing list. Called
     * automatically when Laragraph finishes an execution.
     */
    public function clear(): void
    {
        foreach ($this->loaders as $loader) {
            $loader->clearAll();
            $loader->__destruct();
        }

        $this->loaders = [];
    }
}
