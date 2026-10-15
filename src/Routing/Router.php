<?php

namespace LaravelOptimized\Routing;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\ImplicitRouteBinding;
use Illuminate\Routing\Route as BaseRoute;
use Illuminate\Routing\RouteCollection;
use Illuminate\Routing\Router as BaseRouter;
use LaravelOptimized\Extension\Extensions;

/**
 * Laravel's router, used for route *registration* (so every package that type-hints
 * Illuminate\Routing\Router or uses the Route facade works unchanged).
 *
 * Matching and dispatch are delegated to LaravelOptimized\Routing\Dispatcher, which works from the
 * compiled route table instead of Symfony's matcher and the reflection-based dispatchers.
 */
class Router extends BaseRouter
{
    protected ?Dispatcher $compiledDispatcher = null;

    public function newRoute($methods, $uri, $action)
    {
        return (new Route($methods, $uri, $action))
            ->setRouter($this)
            ->setContainer($this->container);
    }

    public function useCompiledRoutes(CompiledRoutes $routes): void
    {
        $this->routes = $routes;

        if ($this->container->resolved('url')) {
            $this->container->instance('routes', $routes);
        }
    }

    /**
     * The compiled table for dispatch. Uncached apps compile in memory on first use, as do
     * apps whose route cache was written by stock Laravel. Its CompiledRouteCollection builds
     * new Route objects on every read, so its routes are read once into a RouteCollection.
     */
    public function compiledRoutes(): CompiledRoutes
    {
        if (! $this->routes instanceof CompiledRoutes) {
            $routes = $this->routes;

            if ($routes instanceof CompiledRouteCollection) {
                $routes = new RouteCollection;

                foreach ($this->routes->getRoutes() as $route) {
                    $routes->add($route);
                }
            }

            $this->useCompiledRoutes(CompiledRoutes::fromCollection($routes, $this));
        }

        return $this->routes;
    }

    public function setCurrentRoute(BaseRoute $route, Request $request): void
    {
        $this->current = $route;
        $this->currentRequest = $request;
    }

    /** The container routes are currently resolved from (Octane swaps it per request). */
    public function container(): Container
    {
        return $this->container;
    }

    public function compiledDispatcher(): Dispatcher
    {
        return $this->compiledDispatcher ??= new Dispatcher($this->container, $this);
    }

    public function dispatch(Request $request)
    {
        $this->currentRequest = $request;

        return $this->compiledDispatcher()->dispatch($request);
    }

    public function dispatchToRoute(Request $request)
    {
        return $this->dispatch($request);
    }

    /**
     * Implicit model / backed-enum binding from the compiled signature map (no reflection).
     * Mirrors Illuminate\Routing\ImplicitRouteBinding::resolveForRoute().
     */
    public function substituteImplicitBindings($route)
    {
        $bindings = $route instanceof Route && $route->compiledIndex !== null && $this->routes instanceof CompiledRoutes
            ? $this->routes->implicitBindings($route->compiledIndex)
            : null;

        $default = $bindings === null
            ? fn () => ImplicitRouteBinding::resolveForRoute($this->container, $route)
            : fn () => ImplicitBinder::resolve($this->container, $route, $bindings);

        return call_user_func($this->implicitBindingCallback ?? $default, $this->container, $route, $default);
    }

    /**
     * A route's data for one key (Extensions::routeData()): read from the compiled table, or
     * computed now for a route that is not in it (one added after the table was compiled).
     */
    public function routeData(BaseRoute $route, string $key): mixed
    {
        if ($route instanceof Route && $route->compiledIndex !== null && $this->routes instanceof CompiledRoutes) {
            return $this->routes->data($route->compiledIndex, $key);
        }

        $compiler = $this->container->bound(Extensions::class)
            ? $this->container->make(Extensions::class)->routeDataCompilers()[$key] ?? null
            : null;

        return $compiler === null ? null : $compiler($route);
    }

    /**
     * The current route and request, and set them back: InternalDispatcher runs a route inside
     * another and restores the outer one afterwards.
     *
     * @return array{0: BaseRoute|null, 1: Request|null}
     */
    public function currentState(): array
    {
        return [$this->current, $this->currentRequest];
    }

    public function restoreState(?BaseRoute $route, ?Request $request): void
    {
        $this->current = $route;
        $this->currentRequest = $request;
    }

    public function getMiddlewarePriority(): array
    {
        return $this->middlewarePriority;
    }
}
