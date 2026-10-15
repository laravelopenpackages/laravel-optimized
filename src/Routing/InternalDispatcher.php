<?php

namespace LaravelOptimized\Routing;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Contracts\Http\Kernel as HttpKernelContract;
use Illuminate\Http\Request;
use Illuminate\Routing\Router as BaseRouter;
use Illuminate\Support\Facades\Facade;
use LaravelOptimized\Foundation\Application;
use LaravelOptimized\Foundation\HttpKernel;
use Throwable;

/**
 * Run one of the application's own routes from inside the current request, in memory: no HTTP
 * round trip, and no second pass through the HTTP kernel.
 *
 *   $users = app(InternalDispatcher::class)->get('/api/users')->data();
 *
 * The inner request is the outer one's for everything that identifies the visitor: it shares
 * the session and the authenticated user, carries the cookies, and asks for JSON. It runs the
 * route's middleware except what the outer request has already run (the session, cookies,
 * CSRF, `auth`, ... are not run twice; `auth:sanctum` or `throttle:api` still run when the outer
 * request did not). The global middleware does not run: it ran for the outer request.
 *
 * call() short-circuits: when the action returns data (an array, a model, a collection, a
 * scalar) rather than a response, data() is that value, not a JSON encoding of it. A response
 * from a middleware (a redirect, a 403) or an exception (rendered by the exception handler, as
 * JSON) comes back as a response instead, with its status.
 *
 * Afterwards the outer request, route and URL generator state are what they were before.
 */
final class InternalDispatcher
{
    public function __construct(private readonly Application $app)
    {
    }

    public function get(string $uri, array $query = [], array $headers = []): InternalResponse
    {
        return $this->call('GET', $uri, $query, $headers);
    }

    public function post(string $uri, array $data = [], array $headers = []): InternalResponse
    {
        return $this->call('POST', $uri, $data, $headers);
    }

    public function call(string $method, string $uri, array $data = [], array $headers = []): InternalResponse
    {
        return $this->handle($this->request($method, $uri, $data, $headers), raw: true);
    }

    /**
     * Dispatch a request (from request() or built by hand). With $raw, data results are returned
     * as they are (see call()); without it, data() of the response decodes JSON.
     */
    public function handle(Request $request, bool $raw = false): InternalResponse
    {
        $router = $this->app->make('router');
        $outer = $this->app->bound('request') ? $this->app->make('request') : null;
        [$outerRoute, $outerRouteRequest] = $router->currentState();

        $kernel = $this->app->make(HttpKernelContract::class);
        $skip = $kernel instanceof HttpKernel ? $kernel->ranMiddleware() : [];

        $this->app->instance('request', $request);
        Facade::clearResolvedInstance('request');

        try {
            try {
                [$result, $isRaw, $pipeline] = $router->compiledDispatcher()->dispatchWithin($request, $skip, $raw);
            } catch (Throwable $e) {
                /** Thrown outside the route's pipeline: no route matched (404 / 405). */
                [$result, $isRaw, $pipeline] = [$this->render($request, $e), false, null];
            }

            $response = new InternalResponse($request, $result, $isRaw);

            foreach ($pipeline?->used ?? [] as $middleware) {
                if (method_exists($middleware, 'terminate')) {
                    $middleware->terminate($request, $response->response());
                }
            }

            return $response;
        } finally {
            $outer !== null
                ? $this->app->instance('request', $outer)
                : $this->app->forgetInstance('request');

            Facade::clearResolvedInstance('request');
            $router->restoreState($outerRoute, $outerRouteRequest);
        }
    }

    /**
     * An inner request for a route of this application, carrying the visitor's session, user
     * and cookies from the current request. Data is the query string for GET / HEAD, a JSON
     * body otherwise.
     */
    public function request(string $method, string $uri, array $data = [], array $headers = []): Request
    {
        $method = strtoupper($method);
        $outer = $this->app->bound('request') ? $this->app->make('request') : null;
        $reading = in_array($method, ['GET', 'HEAD'], true);

        /** The outer server variables (host, scheme, client), without its request headers. */
        $server = [];

        foreach ($outer?->server->all() ?? [] as $key => $value) {
            if (! str_starts_with($key, 'HTTP_') || $key === 'HTTP_HOST') {
                $server[$key] = $value;
            }
        }

        unset($server['CONTENT_TYPE'], $server['CONTENT_LENGTH'], $server['QUERY_STRING'], $server['REQUEST_URI']);

        $request = Request::create(
            $uri,
            $method,
            $reading ? $data : [],
            $outer?->cookies->all() ?? [],
            [],
            $server,
            $reading ? null : json_encode($data, JSON_THROW_ON_ERROR)
        );

        $request->headers->add([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
            /** A request from this application to itself: same-origin for CSRF protection. */
            'Sec-Fetch-Site' => 'same-origin',
        ] + ($reading ? [] : ['Content-Type' => 'application/json']));
        $request->headers->add($headers);

        if ($outer !== null) {
            if ($outer->hasSession()) {
                $request->setLaravelSession($outer->session());
            }

            $request->setUserResolver($outer->getUserResolver());
        }

        $request->attributes->set('optimized.internal', true);

        return $request;
    }

    private function render(Request $request, Throwable $e): mixed
    {
        if (! $this->app->bound(ExceptionHandler::class)) {
            throw $e;
        }

        $handler = $this->app->make(ExceptionHandler::class);
        $handler->report($e);

        return BaseRouter::toResponse($request, $handler->render($request, $e));
    }
}
