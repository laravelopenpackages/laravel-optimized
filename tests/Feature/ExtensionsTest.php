<?php

namespace LaravelOptimized\Tests\Feature;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\Schema\Blueprint;
use LogicException;
use LaravelOptimized\Extension\Extensions;
use LaravelOptimized\Routing\InternalDispatcher;
use LaravelOptimized\Routing\RouteCompiler;
use LaravelOptimized\Tests\Fixtures\Classes\Post;
use LaravelOptimized\Tests\Fixtures\Classes\Terminable;
use LaravelOptimized\Tests\Fixtures\Package\AcmeExtensionServiceProvider;
use LaravelOptimized\Tests\Fixtures\Package\AcmePage;
use LaravelOptimized\Tests\Fixtures\Package\CountingMiddleware;
use LaravelOptimized\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

/**
 * The extension points, used by the fixture package's AcmeExtensionServiceProvider the way a
 * real package would: route data, build steps and internal dispatch.
 */
class ExtensionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CountingMiddleware::$runs = [];
        Terminable::$terminated = [];
        AcmeExtensionServiceProvider::$routeDataCompiled = 0;
        AcmeExtensionServiceProvider::$object = null;
    }

    #[DataProvider('modes')]
    public function test_route_data_is_compiled_with_the_table_and_read_back(bool $cached): void
    {
        $this->boot($cached);
        AcmeExtensionServiceProvider::$routeDataCompiled = 0;

        $this->assertSame(['page' => 'data-page'], json_decode($this->get('/acme/route-data')->getContent(), true));

        /** Cached: read from the route cache, the compiler does not run on a request. */
        $cached
            ? $this->assertSame(0, AcmeExtensionServiceProvider::$routeDataCompiled)
            : $this->assertGreaterThan(0, AcmeExtensionServiceProvider::$routeDataCompiled);
    }

    public function test_route_data_for_a_route_added_after_compilation_is_computed(): void
    {
        $app = $this->boot(true);
        $router = $app->make('router');

        $route = $router->get('/late', ['uses' => fn () => 'late', 'acme.page' => 'late-page']);

        $this->assertSame('late-page', $router->routeData($route, 'acme.page'));
        $this->assertNull($router->routeData($route, 'unknown'));
    }

    public function test_route_data_that_cannot_be_cached_fails_at_compile_time(): void
    {
        $app = $this->boot();
        $app->make(Extensions::class)->routeData('bad', fn () => new stdClass);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Route data [bad]');

        RouteCompiler::compile($app->make('router')->getRoutes(), $app->make('router'));
    }

    #[DataProvider('modes')]
    public function test_a_page_routed_to_its_own_mount_gets_implicit_binding(bool $cached): void
    {
        $app = $this->boot($cached);

        $app->make('db')->connection()->getSchemaBuilder()->create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
        });
        Post::create(['slug' => 'hello-world']);

        $this->assertSame(
            ['page' => AcmePage::class, 'post' => 'hello-world'],
            json_decode($this->get('/acme/pages/hello-world')->getContent(), true)
        );
        $this->assertSame(404, $this->get('/acme/pages/missing')->getStatusCode());
    }

    public function test_build_and_clear_steps_run_with_the_compiled_routes(): void
    {
        $app = $this->bootConsole();
        $kernel = $app->make(ConsoleKernel::class);
        $file = $app->bootstrapPath('cache/'.AcmeExtensionServiceProvider::PAGES_CACHE);

        $this->assertSame(0, $kernel->call('extensions:cache'), $kernel->output());
        $this->assertFileExists($file);
        $this->assertSame([
            ['uri' => 'acme/route-data', 'methods' => ['GET', 'HEAD'], 'name' => null, 'domain' => null, 'data' => 'data-page'],
            ['uri' => 'acme/pages/{post}', 'methods' => ['GET', 'HEAD'], 'name' => 'acme.page', 'domain' => null, 'data' => 'post-page'],
        ], require $file);

        $this->assertSame(0, $kernel->call('extensions:clear'), $kernel->output());
        $this->assertFileDoesNotExist($file);
    }

    public function test_optimize_and_optimize_clear_run_the_build_steps(): void
    {
        $app = $this->bootConsole();
        $kernel = $app->make(ConsoleKernel::class);
        $file = $app->bootstrapPath('cache/'.AcmeExtensionServiceProvider::PAGES_CACHE);

        $this->assertSame(0, $kernel->call('optimize'), $kernel->output());
        $this->assertFileExists($file);

        $this->assertSame(0, $kernel->call('optimize:clear'), $kernel->output());
        $this->assertFileDoesNotExist($file);
    }

    #[DataProvider('modes')]
    public function test_internal_dispatch_runs_routes_in_memory_inside_the_request(bool $cached): void
    {
        $this->boot($cached);

        $response = $this->get('/acme/internal/outer');
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getContent());
        $result = json_decode($response->getContent(), true);

        /** Shares the outer session (unsaved changes included), asks for JSON, is its own route. */
        $this->assertSame([
            'q' => 'x',
            'session' => 'set-by-outer',
            'internal' => true,
            'route' => 'acme.internal.data',
            'wants_json' => true,
        ], $result['data']);

        /** Short-circuited: the action's own return value, not a decoded copy. */
        $this->assertTrue($result['raw']);
        $this->assertTrue($result['same_object']);

        /** POST with a JSON body; CSRF did not stop it; validation errors come back as 422 JSON. */
        $this->assertSame([200, ['title' => 'Hi']], $result['stored']);
        $this->assertSame([422, ['title']], $result['invalid']);
        $this->assertSame(403, $result['forbidden']);
        $this->assertSame(404, $result['missing']);

        /** A string is data too; response() turns it into the response Laravel would send. */
        $this->assertTrue($result['text'][0]);
        $this->assertSame('plain text', $result['text'][1]);
        $this->assertStringStartsWith('text/html', $result['text'][2]);

        /** The outer request, route and URL generator are back as they were. */
        $this->assertSame([
            'route' => 'acme.internal.outer',
            'current' => 'acme.internal.outer',
            'request' => 'acme/internal/outer',
            'url' => 'http://localhost/acme/internal/outer',
            'session' => 'set-by-outer',
        ], $result['after']);

        /**
         * Middleware the outer request ran is not run again (CountingMiddleware, the web group,
         * terminable); the inner route's own `CountingMiddleware:strict` runs.
         */
        $this->assertSame(['default:acme/internal/outer', 'strict:acme/internal/other-middleware'], $result['runs']);
        $this->assertSame(['other' => true], $result['other']);

        /** The outer route's terminable middleware still terminates, once. */
        $this->assertSame(['acme/internal/outer'], Terminable::$terminated);
    }

    /**
     * StartSession saves the session when it finishes, and saving ages flash data. Were it run
     * again for the inner request, a message the outer request flashed would be aged now and
     * gone before the next request.
     */
    public function test_internal_dispatch_leaves_the_outer_flash_data_alone(): void
    {
        $app = $this->boot();
        $router = $app->make('router');

        $router->middleware('web')->get('/flash/outer', function (InternalDispatcher $internal) {
            session()->flash('status', 'saved');
            $internal->get('/flash/inner');

            return session('_flash');
        });
        $router->middleware('web')->get('/flash/inner', fn () => ['ok' => true]);

        $this->assertSame(['new' => ['status'], 'old' => []], json_decode($this->get('/flash/outer')->getContent(), true));
    }

    public function test_internal_dispatch_outside_an_http_request(): void
    {
        $app = $this->bootConsole();
        $consoleRequest = $app->make('request');

        $response = $app->make(InternalDispatcher::class)->get('/acme/internal/data', ['q' => 'cli']);

        $this->assertSame(200, $response->status());
        $this->assertSame('cli', $response->data()['q']);
        $this->assertSame('acme.internal.data', $response->data()['route']);
        $this->assertSame($consoleRequest, $app->make('request'));
        $this->assertNull($app->make('router')->current());
    }

    public function test_handle_without_raw_returns_the_response(): void
    {
        $app = $this->bootConsole();
        $internal = $app->make(InternalDispatcher::class);

        $response = $internal->handle($internal->request('GET', '/acme/internal/data'));

        $this->assertFalse($response->isRaw());
        $this->assertSame(200, $response->status());
        $this->assertStringStartsWith('application/json', $response->header('Content-Type'));
        $this->assertSame('acme.internal.data', $response->data()['route']);
    }
}
