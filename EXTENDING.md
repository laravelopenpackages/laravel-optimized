# Extending Laravel Optimized

Packages extend an optimized application the way they extend Laravel: service providers
(discovered from `extra.laravel.providers`), routes, middleware, views, commands, `extend()` /
`afterResolving()` on core services, and `optimizes()` for build commands. All of that works
unchanged.

Laravel Optimized adds extension points for the parts it owns, the build step, the compiled route
table and dispatch:

| Extension point | API |
| --- | --- |
| Data compiled with each route | `Extensions::routeData()`, read with `Router::routeData()` / `CompiledRoutes::withData()` |
| Build and clear steps | `Extensions::build()`, run by `extensions:cache` / `extensions:clear` |
| Running a route inside the current request | `InternalDispatcher` |

`LaravelOptimized\Extension\Extensions` and `LaravelOptimized\Routing\InternalDispatcher` come from
the container. The package's own components (`LaravelOptimized\Components\Registry`) are the
framework's core services, not an extension point; a package's services belong in its own
provider.

## Route data

Compute something for each route once, when the route table is compiled, and read it on a
request without computing it again:

```php
use Illuminate\Routing\Route;
use LaravelOptimized\Extension\Extensions;

public function boot(Extensions $extensions): void
{
    $extensions->routeData('pages', fn (Route $route) => $route->getAction('page'));
}
```

The callback runs for every route and returns the value to store, or `null` for none. The value
is written into the route cache, so it must be `null`, a scalar, an enum or an array of those;
anything else fails when routes are compiled, cached or not.

```php
// While handling a request
$page = app('router')->routeData($request->route(), 'pages');

// In a build step: every route with data for the key
$build->routes->withData('pages'); // [['uri', 'methods', 'name', 'domain', 'data'], ...]
```

With `route:cache` the value is read from the cache. Without it, routes compile in memory on the
first request, as they always do with this package. A route added after the table was compiled
has its value computed when asked for.

A route can carry any keys in its action array (`Route::get('/x', ['uses' => ..., 'page' => ...])`);
they survive `route:cache` like the rest of the action.

## Build steps

```php
use LaravelOptimized\Extension\BuildContext;

$extensions->build(
    'pages',
    function (BuildContext $build) {
        file_put_contents($build->cachePath('pages.php'), '<?php return '.var_export($build->routes->withData('pages'), true).';');
    },
    fn (BuildContext $build) => @unlink($build->cachePath('pages.php')),
);
```

`php artisan extensions:cache` boots the application once, fresh from source (no config or route
cache), and runs every build step with that application (`$build->app`) and its compiled route
table, route data included (`$build->routes`). `php artisan extensions:clear` runs the clear
steps. `optimize` and `optimized:build` run `extensions:cache`, and `optimize:clear` runs
`extensions:clear`.

## Internal dispatch

Run one of the application's routes from inside the current request, in memory, without an
HTTP round trip:

```php
use LaravelOptimized\Routing\InternalDispatcher;

$response = app(InternalDispatcher::class)->get('/api/users', ['page' => 2]);

$response->status();   // 200
$response->data();     // what the action returned
$response->response(); // a Symfony response, when one is needed
```

`get()`, `post()` and `call($method, $uri, $data, $headers)` short-circuit. When the action returns
data (an array, a model, a collection, a string), `data()` is that value itself, not a JSON
encoding of it (`isRaw()` is true). When a middleware answers (a redirect, a 403) or the action
throws (a validation error, a 404), you get that response, rendered by the exception handler as
JSON, with its status. `handle($request)` dispatches a request you built with `request()` and
always returns the response.

The inner request:

- shares the current session and authenticated user, and carries the current cookies;
- asks for JSON, and is same-origin for CSRF protection;
- carries the `optimized.internal` request attribute;
- runs its route's middleware, except middleware the current request has already run with the
  same parameters. The session, cookies, CSRF check and `auth` are not run twice (running
  `StartSession` again would save the session early and age the current request's flash data),
  while middleware only the inner route has, such as `auth:sanctum` or `can:...`, still runs;
- does not run the global middleware, which ran for the current request.

Afterwards the current request, route and URL generator are what they were before. Outside an
HTTP request (a command, a queued job) every route middleware runs.
