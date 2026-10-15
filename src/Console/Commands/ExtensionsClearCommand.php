<?php

namespace LaravelOptimized\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan extensions:clear`: runs the clear steps packages register (Extensions::build()).
 * Part of `optimize:clear`.
 */
#[AsCommand(name: 'extensions:clear')]
class ExtensionsClearCommand extends Command
{
    protected $signature = 'extensions:clear';

    protected $description = 'Run the clear steps packages register with Laravel Optimized';

    public function handle(): int
    {
        return ExtensionsCacheCommand::runSteps($this, 'clear');
    }
}
