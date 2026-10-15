<?php

namespace LaravelOptimized\Extension;

use LaravelOptimized\Foundation\Application;
use LaravelOptimized\Routing\CompiledRoutes;

/**
 * What a build step gets: the application booted fresh from source (no config or route cache,
 * as `route:cache` sees it) and its compiled route table, with route data included.
 */
final class BuildContext
{
    public function __construct(
        public readonly Application $app,
        public readonly CompiledRoutes $routes,
    ) {
    }

    /**
     * A file in the application's bootstrap/cache directory, next to the compiled caches.
     */
    public function cachePath(string $file): string
    {
        return $this->app->bootstrapPath('cache'.DIRECTORY_SEPARATOR.ltrim($file, '\\/'));
    }
}
