<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph;

use Illuminate\Support\ServiceProvider;

/**
 * This file is part of the Laragraph package.
 *
 * (c) Odhiambo Dormnic <ayimdomnic@gmail.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class LaragraphServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/laragraph.php', 'laragraph');

        $this->app->singleton('laragraph', function () {
            return new Laragraph;
        });
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__ . '/routes.php');
        $this->loadViewsFrom(__DIR__ . '/views', 'laragraph');
        $this->publishes([
            __DIR__ . '/../config/laragraph.php' => config_path('laragraph.php'),
        ], 'laragraph-config');
    }
}