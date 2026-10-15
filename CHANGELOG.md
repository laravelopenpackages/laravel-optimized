# Changelog

Notable changes to Laravel Optimized.

## v1.0.1

- Switching an application back to stock Laravel works: a configuration cached by
  `config:cache` keeps Laravel's full `app.providers` list, so stock Laravel boots from it and its
  `optimize:clear` runs. Before, the cached list lacked the providers whose services are
  components (cache, session, views, ...), and stock Laravel failed to boot until
  `bootstrap/cache` was cleared by hand.

## v1.0.0

The first release.

- `LaravelOptimized\Foundation\Application`: Laravel's application with the core services built on
  demand from a component table instead of registered by service providers, and its own HTTP and
  console kernels and bootstrap sequence.
- Compiled routing: `route:cache` writes a route table (an exact-path map and grouped regular
  expressions), each route's middleware stack, argument plan and implicit bindings, and container
  factories for the controllers and middleware the routes build.
- Compiled URLs: `route()` fills in each named route's URL template for routes without a domain,
  optional parameters, binding fields or a scheme of their own; every other call uses Laravel's
  URL generator.
- Compiled Eloquent: `eloquent:cache` compiles Laravel's Model so that each model's boot methods
  and initializers, class attributes, `#[ObservedBy]` observers and `#[ScopedBy]` scopes are read
  from a plan made at build time. Only methods identical to the ones the package was written
  against are replaced, and the compiled Model is used only while the installed Laravel Model and
  PHP version are the ones it was made with.
- Extension points for packages, documented in [EXTENDING.md](EXTENDING.md): route data compiled
  with the route table, build and clear steps (`extensions:cache` / `extensions:clear`), and
  `InternalDispatcher`, which runs one of the application's routes inside the current request.
- Commands: `optimize [--profile]`, `optimize:clear`, `view:warm`, `optimized:build` (every
  production cache in one pass, with a production checklist) and `optimized:clear`.
- `config/optimized.php` turns each compilation on or off (`compile.eloquent`, `compile.urls`) and
  lists where models are found (`eloquent.paths`).
