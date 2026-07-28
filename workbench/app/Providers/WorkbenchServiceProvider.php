<?php

declare(strict_types=1);

namespace Workbench\App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Workbench service provider — registers example GraphQL schema for local dev.
 *
 * This file is EXCLUDED from Packagist releases (.gitattributes export-ignore).
 */
class WorkbenchServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        config([
            'laragraph.discover.types'     => 'workbench/app/GraphQL/Types',
            'laragraph.discover.queries'   => 'workbench/app/GraphQL/Queries',
            'laragraph.discover.mutations' => 'workbench/app/GraphQL/Mutations',
        ]);
    }
}
