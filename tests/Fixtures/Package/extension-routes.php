<?php

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use LaravelOptimized\Routing\InternalDispatcher;
use LaravelOptimized\Tests\Fixtures\Package\AcmeExtensionServiceProvider;
use LaravelOptimized\Tests\Fixtures\Package\AcmePage;
use LaravelOptimized\Tests\Fixtures\Package\CountingMiddleware;

Route::middleware('web')->group(function () {
    // Route data, compiled with the table and read back per request.
    Route::get('/acme/route-data', [
        'uses' => fn (Request $request) => ['page' => app('router')->routeData($request->route(), 'acme.page')],
        'acme.page' => 'data-page',
    ]);

    // A page routed to its own mount(Post $post), with route data.
    Route::get('/acme/pages/{post:slug}', ['uses' => AcmePage::class.'@mount', 'acme.page' => 'post-page'])->name('acme.page');
});

Route::middleware(['web', CountingMiddleware::class, 'terminable'])->prefix('acme/internal')->group(function () {
    Route::get('/outer', function (InternalDispatcher $internal, Request $request) {
        /** Not saved yet: the inner requests must see it, not a copy reloaded from the store. */
        session(['outer' => 'set-by-outer']);

        $data = $internal->get('/acme/internal/data', ['q' => 'x']);
        $object = $internal->get('/acme/internal/object');
        $other = $internal->get('/acme/internal/other-middleware');
        $stored = $internal->post('/acme/internal/store', ['title' => 'Hi']);
        $invalid = $internal->post('/acme/internal/store', []);
        $forbidden = $internal->get('/acme/internal/forbidden');
        $missing = $internal->get('/acme/internal/nothing-here');
        $view = $internal->get('/acme/internal/text');

        return [
            'data' => $data->data(),
            'raw' => $data->isRaw(),
            'same_object' => $object->data() === AcmeExtensionServiceProvider::$object,
            'other' => $other->data(),
            'stored' => [$stored->status(), $stored->data()],
            'invalid' => [$invalid->status(), array_keys($invalid->data()['errors'] ?? [])],
            'forbidden' => $forbidden->status(),
            'missing' => $missing->status(),
            'text' => [$view->isRaw(), $view->data(), $view->response()->headers->get('Content-Type')],
            'after' => [
                'route' => $request->route()->getName(),
                'current' => Route::currentRouteName(),
                'request' => request()->path(),
                'url' => url()->current(),
                'session' => session('outer'),
            ],
            'runs' => CountingMiddleware::$runs,
        ];
    })->name('acme.internal.outer');

    Route::get('/data', fn (Request $request) => [
        'q' => $request->query('q'),
        'session' => session('outer'),
        'internal' => $request->attributes->get('optimized.internal'),
        'route' => Route::currentRouteName(),
        'wants_json' => $request->expectsJson(),
    ])->name('acme.internal.data');

    Route::get('/object', fn () => AcmeExtensionServiceProvider::$object = new Collection(['a' => 1]));
    Route::get('/other-middleware', fn () => ['other' => true])->middleware(CountingMiddleware::class.':strict');
    Route::post('/store', fn (Request $request) => $request->validate(['title' => 'required']));
    Route::get('/forbidden', fn () => abort(403));
    Route::get('/text', fn () => 'plain text');
});
