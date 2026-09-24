<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Ayimdomnic\Laragraph\Console\CacheCommand;
use Ayimdomnic\Laragraph\Console\ClearCommand;
use Ayimdomnic\Laragraph\Console\ExportSchemaCommand;
use Ayimdomnic\Laragraph\Console\InputMakeCommand;
use Ayimdomnic\Laragraph\Console\MutationMakeCommand;
use Ayimdomnic\Laragraph\Console\QueryMakeCommand;
use Ayimdomnic\Laragraph\Console\ScaffoldCommand;
use Ayimdomnic\Laragraph\Console\SubscriptionMakeCommand;
use Ayimdomnic\Laragraph\Console\TypeMakeCommand;
use Ayimdomnic\Laragraph\Console\ValidateSchemaCommand;
use Ayimdomnic\Laragraph\Discovery\Discover;
use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\PersistedQuery\ArrayPersistedQueryStore;
use Ayimdomnic\Laragraph\PersistedQuery\CachePersistedQueryStore;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryStoreInterface;
use Ayimdomnic\Laragraph\Scalars\Database\DatabasePreset;
use Ayimdomnic\Laragraph\Subscriptions\CacheSubscriberStore;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberChannel;
use Ayimdomnic\Laragraph\Subscriptions\SubscriberStoreInterface;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
use Composer\InstalledVersions;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Console\AboutCommand;
use Illuminate\Support\ServiceProvider;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 */
class LaragraphServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laragraph.php', 'laragraph');

        $this->app->singleton('laragraph', fn($app): Laragraph => new Laragraph($app));

        $this->app->alias('laragraph', Laragraph::class);

        $this->app->singleton(ExtensionRegistry::class, fn(): ExtensionRegistry => new ExtensionRegistry());

        $this->app->singleton(TracingCollector::class, fn(): TracingCollector => new TracingCollector());

        $this->app->singleton(ValidationRuleRegistry::class, function ($app): ValidationRuleRegistry {
            $registry = new ValidationRuleRegistry();

            foreach ((array) config('laragraph.validation.rules', []) as $rule) {
                $registry->add($rule);
            }

            return $registry;
        });

        $this->app->singleton(PersistedQueryStoreInterface::class, function ($app): ArrayPersistedQueryStore|CachePersistedQueryStore {
            $driver = config('laragraph.persisted_queries.store', 'cache');

            if ($driver === 'array') {
                return new ArrayPersistedQueryStore(
                    (array) config('laragraph.persisted_queries.map', []),
                );
            }

            return new CachePersistedQueryStore(
                $app['cache']->store(),
                (int) config('laragraph.persisted_queries.ttl', 3600) ?: null,
            );
        });

        $this->app->singleton(SubscriberStoreInterface::class, fn($app): CacheSubscriberStore => new CacheSubscriberStore(
            $app['cache']->store(config('laragraph.subscriptions.cache_store')),
            (int) config('laragraph.subscriptions.ttl', 3600) ?: null,
        ));
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->mergePresetTypes();
        $this->authorizeSubscriberChannels();
        $this->warmUnderOctane();

        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/views', 'laragraph');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/laragraph.php' => config_path('laragraph.php'),
            ], 'laragraph-config');

            $this->publishes([
                __DIR__ . '/views' => resource_path('views/vendor/laragraph'),
            ], 'laragraph-views');

            $this->commands([
                CacheCommand::class,
                ClearCommand::class,
                ValidateSchemaCommand::class,
                TypeMakeCommand::class,
                QueryMakeCommand::class,
                MutationMakeCommand::class,
                SubscriptionMakeCommand::class,
                InputMakeCommand::class,
                ScaffoldCommand::class,
                ExportSchemaCommand::class,
            ]);

            // `php artisan optimize` / `optimize:clear` integration (Laravel 11.27+).
            if (method_exists($this, 'optimizes')) { // @phpstan-ignore function.alreadyNarrowedType (absent before Laravel 11.27)
                $this->optimizes(optimize: 'laragraph:cache', clear: 'laragraph:clear', key: 'laragraph');
            }

            AboutCommand::add('Laragraph', $this->aboutInformation(...));
        }
    }

    /**
     * Only the user who created a subscription may listen on its private
     * channel. Registered when the broadcaster is first resolved, so apps
     * that never broadcast are unaffected.
     */
    protected function authorizeSubscriberChannels(): void
    {
        if (!config('laragraph.subscriptions.enabled') || !config('laragraph.subscriptions.authorize_channel', true)) {
            return;
        }

        $this->callAfterResolving(BroadcastManager::class, static function (BroadcastManager $broadcast): void {
            $prefix = (string) config('laragraph.subscriptions.channel_prefix', 'graphql-subscriber');

            $broadcast->channel($prefix . '.{subscriberId}', SubscriberChannel::class);
        });
    }

    /**
     * Keep Laragraph alive across requests under Laravel Octane.
     *
     * Octane handles every request in a fresh clone of the application, and
     * a singleton first resolved during a request is discarded with that
     * clone — so the compiled schema and the validation cache would be
     * rebuilt on every request. Services in `octane.warm` are resolved once
     * per worker, before the first request, and shared by all of them.
     *
     * This runs at boot, after every provider has registered, so Octane's own
     * default `warm` list is already in the config (Octane reads it after
     * boot). Set `laragraph.octane.warm` to false to opt out.
     */
    protected function warmUnderOctane(): void
    {
        $warm = config('octane.warm');

        if (!is_array($warm) || !config('laragraph.octane.warm', true) || in_array('laragraph', $warm, true)) {
            return;
        }

        config(['octane.warm' => [...$warm, 'laragraph']]);
    }

    /**
     * The section Laragraph contributes to `php artisan about`.
     *
     * @return array<string, string>
     */
    protected function aboutInformation(): array
    {
        $on  = '<fg=green;options=bold>ENABLED</>';
        $off = 'OFF';

        return [
            'Version'           => $this->installedVersion(),
            'Endpoint'          => '/' . trim((string) config('laragraph.route.prefix', 'graphql'), '/'),
            'Schemas'           => implode(', ', array_keys((array) config('laragraph.schemas', []))),
            'Discovery'         => Discover::isCached() ? '<fg=green;options=bold>CACHED</>' : '<fg=yellow;options=bold>NOT CACHED</>',
            'GraphiQL'          => (config('laragraph.graphiql.enabled') ?? config('app.debug')) ? $on : $off,
            'Introspection'     => (config('laragraph.security.disable_introspection') ?? !config('app.debug')) ? $off : $on,
            'Response cache'    => config('laragraph.cache.response.enabled') ? $on : $off,
            'Persisted queries' => config('laragraph.persisted_queries.enabled') ? $on : $off,
            'Subscriptions'     => config('laragraph.subscriptions.enabled') ? $on : $off,
            'Tracing'           => config('laragraph.tracing.enabled') ? $on : $off,
        ];
    }

    /**
     * @param list<string> $packages Composer names this package has been published under.
     */
    protected function installedVersion(array $packages = ['ayimdomnic/laragraph', 'ayimdomnic/graph-ql-l5.3']): string
    {
        foreach ($packages as $package) {
            if (InstalledVersions::isInstalled($package)) {
                return (string) InstalledVersions::getPrettyVersion($package);
            }
        }

        return 'unknown';
    }

    /**
     * Get the services provided by the provider.
     *
     * @return list<string>
     */
    public function provides(): array
    {
        return ['laragraph', Laragraph::class];
    }

    /**
     * Merge database preset + custom scalars into `laragraph.types` at boot
     * so all schemas automatically see them without manual config entries.
     */
    private function mergePresetTypes(): void
    {
        $preset = config('laragraph.database_types.preset');
        $custom = (array) config('laragraph.database_types.custom', []);

        $presetTypes = ($preset !== null && $preset !== '')
            ? DatabasePreset::types((string) $preset)
            : [];

        if ($presetTypes === [] && $custom === []) {
            return;
        }

        config(['laragraph.types' => array_merge(
            config('laragraph.types', []),
            $presetTypes,
            $custom,
        )]);
    }
}
