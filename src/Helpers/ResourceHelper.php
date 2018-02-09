<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers;

use EoneoPay\Framework\Helpers\Exceptions\InvalidUsesException;
use EoneoPay\Framework\Helpers\Exceptions\UnsupportedResourceException;
use EoneoPay\Framework\Helpers\Interfaces\ResourceHelperInterface;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Router;

class ResourceHelper implements ResourceHelperInterface
{
    /**
     * @var string
     */
    private const HTTP_VERBS = 'GET|POST|PUT|DELETE|PATCH';

    /**
     * @var array
     */
    private $routes = [];

    /**
     * EndpointGuesser constructor.
     *
     * @param \Laravel\Lumen\Routing\Router $router
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\InvalidUsesException
     */
    public function __construct(Router $router)
    {
        $this->initRoutes($router);
    }

    /**
     * Get resource for given request.
     *
     * @param Request $request
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedResourceException
     */
    public function getResourceForRequest(Request $request): string
    {
        $pathInfo = \rtrim($request->getPathInfo(), '/');

        foreach ($this->routes as $regex => [$resource, $method]) {
            if (\preg_match($regex, $pathInfo) && \strtoupper($request->getMethod()) === \strtoupper($method)) {
                return $resource;
            }
        }

        throw new UnsupportedResourceException(\sprintf('Resource %s not supported', $pathInfo));
    }

    /**
     * Set routes array.
     *
     * @param \Laravel\Lumen\Routing\Router $router
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\InvalidUsesException
     */
    private function initRoutes(Router $router): void
    {
        $routes = [];

        foreach ($router->getRoutes() as $routeName => $route) {
            $method = $route['method'] ?? '-no_method-';
            $uses = $route['action']['uses'] ?? '';
            $resource = $this->usesToResource($route['action']['uses'] ?? '');

            if (!\class_exists($resource)) {
                throw new InvalidUsesException(\sprintf(
                    'Invalid uses "%s" for route: [%s]%s',
                    $uses,
                    $method,
                    $route['uri'] ?? '-no_uri-'
                ));
            }

            $routes[$this->routeToRegex($routeName)] = [$resource, $method];
        }

        \uksort($routes, function ($current, $next) {
            return \strlen($next) - \strlen($current);
        });

        $this->routes = $routes;
    }

    /**
     * Convert route to regex.
     *
     * @param string $route
     *
     * @return string
     */
    private function routeToRegex(string $route): string
    {
        $route = \preg_replace(\sprintf('/^(%s)/', self::HTTP_VERBS), '', $route);
        $route = \preg_replace('/\{\w+\}/', '[\w-]+', $route);
        $route = \preg_replace('/\{(\w+):(.+?)\}/', '\2', $route);

        return \sprintf('#^%s$#', $route);
    }

    /**
     * Get resource from uses string.
     *
     * @param string $uses
     *
     * @return null|string
     */
    private function usesToResource(string $uses): ?string
    {
        $split = \explode('@', $uses);

        return $split[0] ?? null;
    }
}
