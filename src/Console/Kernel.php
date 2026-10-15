<?php

namespace LaravelOptimized\Console;

use Illuminate\Foundation\Console\Kernel as BaseKernel;
use LaravelOptimized\Console\Commands\EloquentCacheCommand;
use LaravelOptimized\Console\Commands\EloquentClearCommand;
use LaravelOptimized\Console\Commands\ExtensionsCacheCommand;
use LaravelOptimized\Console\Commands\ExtensionsClearCommand;
use LaravelOptimized\Console\Commands\OptimizeClearCommand;
use LaravelOptimized\Console\Commands\OptimizeCommand;
use LaravelOptimized\Console\Commands\OptimizedBuildCommand;
use LaravelOptimized\Console\Commands\OptimizedClearCommand;
use LaravelOptimized\Console\Commands\RouteCacheCommand;
use LaravelOptimized\Console\Commands\RouteClearCommand;
use LaravelOptimized\Console\Commands\ViewWarmCommand;
use LaravelOptimized\Foundation\Bootstrap;

/**
 * Laravel's console kernel: `php artisan` with every framework command (make:*, migrate,
 * queue:*, schedule:*, vendor:publish, ...), routes/console.php and package commands.
 *
 * Laravel Optimized swaps in its bootstrap and its own `optimize` / `optimize:clear` /
 * `route:cache`, which build its compiled caches (routes, container factories, Eloquent).
 */
class Kernel extends BaseKernel
{
    protected $commands = [
        OptimizeCommand::class,
        OptimizeClearCommand::class,
        RouteCacheCommand::class,
        RouteClearCommand::class,
        EloquentCacheCommand::class,
        EloquentClearCommand::class,
        ExtensionsCacheCommand::class,
        ExtensionsClearCommand::class,
        ViewWarmCommand::class,
        OptimizedBuildCommand::class,
        OptimizedClearCommand::class,
    ];

    protected function bootstrappers()
    {
        return Bootstrap::bootstrappers(console: true);
    }

    /**
     * Laravel only discovers app/Console/Commands and routes/console.php when the kernel is its
     * own class (not an app subclass with a commands() method). This kernel is a drop-in.
     */
    protected function shouldDiscoverCommands()
    {
        return true;
    }
}
