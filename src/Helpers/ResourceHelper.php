<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers;

use EoneoPay\Framework\Helpers\Exceptions\InvalidUsesException;
use EoneoPay\Framework\Helpers\Exceptions\UnsupportedResourceException;
use EoneoPay\Framework\Helpers\Interfaces\ResourceHelperInterface;
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
     * Get resource for given path info.
     *
     * @param string $pathInfo
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedResourceException
     */
    public function getResourceForPathInfo(string $pathInfo): string
    {
        $pathInfo = \rtrim($pathInfo, '/');

        foreach ($this->routes as $regex => $resource) {
            if (\preg_match($regex, $pathInfo)) {
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
            $uses = $this->usesToResource($route['action']['uses'] ?? '');

            if (null === $uses) {
                throw new InvalidUsesException(\sprintf(
                    'Invalid uses "%s" for route: [%s]%s',
                    $uses,
                    $route['method'] ?? '-no_method-',
                    $route['uri'] ?? '-no_uri-'
                ));
            }

            $routes[$this->routeToRegex($routeName)] = $uses;
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
