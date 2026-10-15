<?php

namespace LaravelOptimized\Tests\Fixtures\Package;

use LaravelOptimized\Tests\Fixtures\Classes\Post;

/**
 * A page component routed to its own mount(): Laravel's and the compiled dispatchers call callAction()
 * when a controller has one, and implicit binding reads mount()'s signature.
 */
class AcmePage
{
    public ?Post $post = null;

    public function mount(Post $post): void
    {
        $this->post = $post;
    }

    public function callAction(string $method, array $parameters): array
    {
        $this->{$method}(...array_values($parameters));

        return ['page' => static::class, 'post' => $this->post->slug];
    }
}
