<?php

namespace LaravelOptimized\Extension;

use Closure;
use InvalidArgumentException;

/**
 * Where packages hook into the parts of Laravel Optimized that Laravel does not have: the build
 * step and the compiled route table. Resolve it in a service provider and register hooks there:
 *
 *   public function boot(Extensions $extensions): void
 *   {
 *       // Compiled with the route table; read back per request with Router::routeData().
 *       $extensions->routeData('acme', fn (Route $route) => $route->getAction('acme'));
 *
 *       // Run by `extensions:cache` (and so by `optimize` / `optimized:build`) on a fresh boot
 *       // with the compiled route table; the clear step by `extensions:clear` / `optimize:clear`.
 *       $extensions->build('acme', fn (BuildContext $build) => ..., fn (BuildContext $build) => ...);
 *   }
 *
 * Laravel's own extension points (providers, routes, middleware, `extend()` / `afterResolving()`
 * on core services, `optimizes()`) work unchanged and are not duplicated here.
 */
final class Extensions
{
    /** @var array<string, Closure(\Illuminate\Routing\Route): mixed> */
    private array $routeData = [];

    /** @var array<string, array{build: Closure(BuildContext): mixed, clear: Closure(BuildContext): mixed|null}> */
    private array $builds = [];

    /**
     * Compute data for each route when the route table is compiled. The callback receives the
     * route and returns what to store for it, or null for nothing. The value is written into the
     * route cache, so it must be exportable: null, scalars, enums and arrays of those.
     *
     * @param  callable(\Illuminate\Routing\Route): mixed  $compiler
     */
    public function routeData(string $key, callable $compiler): void
    {
        $this->routeData[$key] = $compiler(...);
    }

    /**
     * A build step for `extensions:cache`, with an optional step for `extensions:clear`.
     *
     * @param  callable(BuildContext): mixed  $build
     * @param  (callable(BuildContext): mixed)|null  $clear
     */
    public function build(string $name, callable $build, ?callable $clear = null): void
    {
        if ($name === '') {
            throw new InvalidArgumentException('A build step needs a name.');
        }

        $this->builds[$name] = ['build' => $build(...), 'clear' => $clear === null ? null : $clear(...)];
    }

    /** @return array<string, Closure(\Illuminate\Routing\Route): mixed> */
    public function routeDataCompilers(): array
    {
        return $this->routeData;
    }

    /** @return array<string, array{build: Closure(BuildContext): mixed, clear: Closure(BuildContext): mixed|null}> */
    public function builds(): array
    {
        return $this->builds;
    }
}
