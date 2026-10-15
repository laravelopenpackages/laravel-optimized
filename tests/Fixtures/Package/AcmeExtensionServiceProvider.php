<?php

namespace LaravelOptimized\Tests\Fixtures\Package;

use Illuminate\Routing\Route;
use Illuminate\Support\ServiceProvider;
use LaravelOptimized\Extension\BuildContext;
use LaravelOptimized\Extension\Extensions;

/**
 * A package that uses the extension points (LaravelOptimized\Extension\Extensions), the way a
 * package such as a component layer would: route data compiled with the route table, a build step
 * that reads it, and routes that call each other through InternalDispatcher.
 */
class AcmeExtensionServiceProvider extends ServiceProvider
{
    public const PAGES_CACHE = 'acme-pages.php';

    public static int $routeDataCompiled = 0;

    /** What a route returned, to check InternalDispatcher hands back the same object. */
    public static ?object $object = null;

    public function boot(Extensions $extensions): void
    {
        $extensions->routeData('acme.page', function (Route $route) {
            static::$routeDataCompiled++;

            return $route->getAction('acme.page');
        });

        $extensions->build(
            'acme-pages',
            function (BuildContext $build) {
                file_put_contents(
                    $build->cachePath(self::PAGES_CACHE),
                    '<?php return '.var_export($build->routes->withData('acme.page'), true).';'
                );
            },
            fn (BuildContext $build) => @unlink($build->cachePath(self::PAGES_CACHE))
        );

        $this->loadRoutesFrom(__DIR__.'/extension-routes.php');
    }
}
