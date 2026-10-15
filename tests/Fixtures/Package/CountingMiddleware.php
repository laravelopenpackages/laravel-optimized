<?php

namespace LaravelOptimized\Tests\Fixtures\Package;

use Closure;

class CountingMiddleware
{
    /** @var list<string> "mode:path" for each run */
    public static array $runs = [];

    public function handle($request, Closure $next, string $mode = 'default')
    {
        static::$runs[] = $mode.':'.$request->path();

        return $next($request);
    }
}
