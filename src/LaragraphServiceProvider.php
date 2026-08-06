<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Ayimdomnic\Laragraph\Console\ExportSchemaCommand;
use Ayimdomnic\Laragraph\Console\InputMakeCommand;
use Ayimdomnic\Laragraph\Console\MutationMakeCommand;
use Ayimdomnic\Laragraph\Console\QueryMakeCommand;
use Ayimdomnic\Laragraph\Console\ScaffoldCommand;
use Ayimdomnic\Laragraph\Console\SubscriptionMakeCommand;
use Ayimdomnic\Laragraph\Console\TypeMakeCommand;
use Ayimdomnic\Laragraph\Extensions\ExtensionRegistry;
use Ayimdomnic\Laragraph\PersistedQuery\ArrayPersistedQueryStore;
use Ayimdomnic\Laragraph\PersistedQuery\CachePersistedQueryStore;
use Ayimdomnic\Laragraph\PersistedQuery\PersistedQueryStoreInterface;
use Ayimdomnic\Laragraph\Scalars\Database\DatabasePreset;
use Ayimdomnic\Laragraph\Tracing\TracingCollector;
use Ayimdomnic\Laragraph\Validation\ValidationRuleRegistry;
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

        $this->app->singleton('laragraph', function ($app) {
            return new Laragraph($app);
        });

        $this->app->alias('laragraph', Laragraph::class);

        $this->app->singleton(ExtensionRegistry::class, fn () => new ExtensionRegistry());

        $this->app->singleton(TracingCollector::class, fn () => new TracingCollector());

        $this->app->singleton(ValidationRuleRegistry::class, function ($app) {
            $registry = new ValidationRuleRegistry();

            foreach ((array) config('laragraph.validation.rules', []) as $rule) {
                $registry->add($rule);
            }

            return $registry;
        });

        $this->app->singleton(PersistedQueryStoreInterface::class, function ($app) {
            $driver = config('laragraph.persisted_queries.store', 'cache');

            if ($driver === 'array') {
                return new ArrayPersistedQueryStore(
                    (array) config('laragraph.persisted_queries.map', [])
                );
            }

            return new CachePersistedQueryStore(
                $app['cache']->store(),
                (int) config('laragraph.persisted_queries.ttl', 3600) ?: null,
            );
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->mergePresetTypes();

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
                TypeMakeCommand::class,
                QueryMakeCommand::class,
                MutationMakeCommand::class,
                SubscriptionMakeCommand::class,
                InputMakeCommand::class,
                ScaffoldCommand::class,
                ExportSchemaCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['laragraph', \Ayimdomnic\Laragraph\Laragraph::class];
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