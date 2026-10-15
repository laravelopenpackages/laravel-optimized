<?php

namespace LaravelOptimized\Routing;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router as BaseRouter;
use Symfony\Component\HttpFoundation\Response;

/**
 * What InternalDispatcher returns: the action's data as it returned it (short-circuited), or the
 * response a route, middleware or exception produced.
 */
final class InternalResponse
{
    private ?Response $response = null;

    public function __construct(
        private readonly Request $request,
        private readonly mixed $result,
        private readonly bool $raw,
    ) {
        if (! $raw) {
            $this->response = $result;
        }
    }

    public function status(): int
    {
        return $this->raw ? 200 : $this->response->getStatusCode();
    }

    public function ok(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    /**
     * True when the action's return value came back as it is, without becoming a response.
     */
    public function isRaw(): bool
    {
        return $this->raw;
    }

    /**
     * The action's data as it returned it; for a response, its decoded JSON, or else its content.
     */
    public function data(): mixed
    {
        if ($this->raw) {
            return $this->result;
        }

        return $this->response instanceof JsonResponse
            ? $this->response->getData(true)
            : $this->response->getContent();
    }

    /**
     * A response in every case: short-circuited data becomes one as Laravel's router would make it.
     */
    public function response(): Response
    {
        return $this->response ??= BaseRouter::toResponse($this->request, $this->result);
    }

    public function header(string $name): ?string
    {
        return $this->raw ? null : $this->response->headers->get($name);
    }
}
