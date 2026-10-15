<?php

use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use LaravelOptimized\Foundation\Application;
use LaravelOptimized\Tests\Fixtures\Classes\AddHeader;
use LaravelOptimized\Tests\Fixtures\Classes\GlobalHeader;
use LaravelOptimized\Tests\Fixtures\Classes\Terminable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(GlobalHeader::class);
        $middleware->alias([
            'header' => AddHeader::class,
            'terminable' => Terminable::class,
        ]);
        $middleware->validateCsrfTokens(except: ['form', 'match']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
