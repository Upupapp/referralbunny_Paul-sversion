<?php

namespace App\Services\Stitch;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

/**
 * Dispatches GET requests through the application's real HTTP kernel
 * in-process — same middleware, routing, and exception handling as a real
 * request, without a network round trip. Used by stitch:map (BFS crawl) and
 * stitch:contracts (rendering critical-flow pages for the guest role).
 */
class InternalDispatcher
{
    public function __construct(private Kernel $kernel)
    {
    }

    /**
     * @return array{status: int, body: string, location: ?string, route: ?string}
     */
    public function get(string $uri): array
    {
        $request = Request::create($uri, 'GET');

        $response = $this->kernel->handle($request);

        $result = [
            'status' => $response->getStatusCode(),
            'body' => $response->getContent() ?: '',
            'location' => $response->headers->get('Location'),
            'route' => $request->route()?->getName(),
        ];

        $this->kernel->terminate($request, $response);

        return $result;
    }
}
