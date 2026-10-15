<?php

namespace LaravelOptimized\Console\Commands;

use Illuminate\Console\Command;
use LaravelOptimized\Console\Optimizer;
use LaravelOptimized\Extension\BuildContext;
use LaravelOptimized\Extension\Extensions;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan extensions:cache`: runs the build steps packages register (Extensions::build()),
 * on one fresh boot of the application with its compiled route table. Part of `optimize`.
 */
#[AsCommand(name: 'extensions:cache')]
class ExtensionsCacheCommand extends Command
{
    protected $signature = 'extensions:cache';

    protected $description = 'Run the build steps packages register with Laravel Optimized';

    public function handle(): int
    {
        return self::runSteps($this, 'build');
    }

    /**
     * Shared with extensions:clear: boot fresh, then run each package's step of one kind.
     */
    public static function runSteps(Command $command, string $kind): int
    {
        $optimizer = new Optimizer($command->getLaravel());

        try {
            $fresh = $optimizer->fresh();
            $steps = array_filter($fresh->make(Extensions::class)->builds(), fn (array $step) => $step[$kind] !== null);

            if ($steps === []) {
                $command->components->info('No package registers a '.$kind.' step.');

                return self::SUCCESS;
            }

            $context = new BuildContext($fresh, $fresh->make('router')->compiledRoutes());

            foreach ($steps as $name => $step) {
                $command->components->task($name, fn () => $step[$kind]($context) !== false);
            }
        } finally {
            $optimizer->restore();
        }

        return self::SUCCESS;
    }
}
