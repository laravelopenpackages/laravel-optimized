<?php

namespace LaravelOptimized\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use LaravelOptimized\Support\Opcache;
use Symfony\Component\Console\Attribute\AsCommand;

/**
 * `php artisan optimized:clear`: undo `optimized:build`, back to a development setup.
 */
#[AsCommand(name: 'optimized:clear')]
class OptimizedClearCommand extends Command
{
    protected $signature = 'optimized:clear {--no-composer : Keep the current Composer autoloader}';

    protected $description = 'Remove every cache written by optimized:build and restore the development autoloader';

    public function handle(): int
    {
        $this->components->task('Framework caches (optimize:clear)', fn () => $this->callSilently('optimize:clear') === 0);

        $this->components->task('Warm views manifest', function () {
            @unlink($this->laravel->getCachedViewsManifestPath());

            return true;
        });

        if (! $this->option('no-composer')) {
            $this->components->task('Composer autoloader (development)', fn () => (new Composer($this->laravel['files'], $this->laravel->basePath()))
                ->dumpAutoloads(['--no-scripts']) === 0);
        }

        $this->components->task('Opcache reset (this CLI process)', fn () => Opcache::available() ? Opcache::reset() : true);

        $this->components->info('Cleared. Reload php-fpm (or restart `php artisan serve`) so the web server drops cached bytecode.');

        return self::SUCCESS;
    }
}
