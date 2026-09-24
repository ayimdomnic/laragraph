<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Cache the auto-discovered GraphQL classes so production requests never scan
 * the filesystem. Registered with `php artisan optimize` on Laravel 11.27+.
 */
#[AsCommand(name: 'laragraph:cache', description: 'Cache the auto-discovered GraphQL types, queries, mutations and subscriptions')]
class CacheCommand extends Command
{
    protected $signature = 'laragraph:cache';

    protected $description = 'Cache the auto-discovered GraphQL types, queries, mutations and subscriptions';

    public function handle(): int
    {
        $manifest = Discover::cache();
        $count    = array_sum(array_map(count(...), $manifest));

        $this->components->info("Laragraph discovery cached successfully ({$count} classes).");

        return self::SUCCESS;
    }
}
