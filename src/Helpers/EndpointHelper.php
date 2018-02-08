<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers;

use EoneoPay\Framework\Helpers\Exceptions\UnsupportedEndpointException;
use EoneoPay\Framework\Helpers\Interfaces\EndpointHelperInterface;
use Laravel\Lumen\Routing\Router;

class EndpointHelper implements EndpointHelperInterface
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
     */
    public function __construct(Router $router)
    {
        $this->initRoutes($router);
    }
    
    /**
     * Get endpoint pattern for given path info.
     *
     * @param string $pathInfo
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedEndpointException
     */
    public function getPatternForPathInfo(string $pathInfo): string
    {
        $pathInfo = \rtrim($pathInfo, '/');

        foreach ($this->routes as $regex => $pattern) {
            if (\preg_match($regex, $pathInfo)) {
                return $pattern;
            }
        }

        throw new UnsupportedEndpointException(\sprintf('Endpoint %s not supported', $pathInfo));
    }

    /**
     * Set routes array.
     *
     * @param \Laravel\Lumen\Routing\Router $router
     */
    private function initRoutes(Router $router): void
    {
        $routes = [];

        foreach ($router->getRoutes() as $routeName => $route) {
            $routes[$this->routeToRegex($routeName)] = $route['uri'] ?? null;
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
}
