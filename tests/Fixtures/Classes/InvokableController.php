<?php

namespace LaravelOptimized\Tests\Fixtures\Classes;

class InvokableController
{
    public function __invoke()
    {
        return 'invoked';
    }
}
