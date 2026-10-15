<?php

namespace LaravelOptimized\Tests\Fixtures\Package;

use Closure;

class AcmeWebMiddleware
{
    public function handle($request, Closure $next)
    {
        return $next($request)->header('X-Acme-Web', 'yes');
    }
}
