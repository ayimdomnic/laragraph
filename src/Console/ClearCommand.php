<?php

declare(strict_types=1);

namespace Ayimdomnic\Laragraph\Console;

use Ayimdomnic\Laragraph\Discovery\Discover;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * Remove the discovery manifest written by `laragraph:cache`.
 * Registered with `php artisan optimize:clear` on Laravel 11.27+.
 */
#[AsCommand(name: 'laragraph:clear', description: 'Remove the Laragraph discovery cache')]
class ClearCommand extends Command
{
    protected $signature = 'laragraph:clear';

    protected $description = 'Remove the Laragraph discovery cache';

    public function handle(): int
    {
        Discover::clearCache();

        $this->components->info('Laragraph discovery cache cleared successfully.');

        return self::SUCCESS;
    }
}
