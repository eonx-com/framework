<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers;

use EoneoPay\Externals\Request\Interfaces\RequestInterface;
use EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException;
use EoneoPay\Framework\Helpers\Interfaces\VersionHelperInterface;
use EoneoPay\Utils\Str;

class VersionHelper implements VersionHelperInterface
{
    /**
     * Application base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * @var string
     */
    private $controllerNamespace;

    /**
     * Default application version (used for mono-domain app).
     *
     * @var int|string
     */
    private $defaultVersion;

    /**
     * Mapping array for multi-domains application.
     *
     * @var string[]
     */
    private $hosts = [];

    /**
     * @var \EoneoPay\Externals\Request\Interfaces\RequestInterface
     */
    private $request;

    /**
     * Requested application name.
     *
     * @var null|string
     */
    private $requestApplication;

    /**
     * Requested version.
     *
     * @var string
     */
    private $requestedVersion;

    /**
     * @var string
     */
    private $routeFilePath;

    /**
     * @var int[]|string[]
     */
    private $versions = [];

    /**
     * VersionHelper constructor.
     *
     * @param string $basePath
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     * @param null|int|string $defaultVersion
     */
    public function __construct(
        string $basePath,
        RequestInterface $request,
        $defaultVersion = null
    ) {
        $this->basePath = \realpath($basePath);
        $this->defaultVersion = $this->formatVersion($defaultVersion ?? '1');
        $this->request = $request;
    }

    /**
     * Add application.
     *
     * @param string $name The name of the application.
     * @param string $host The HTTP host of the application.
     * @param null|int|string $latestVersion The latest version of the application (default to 1).
     *
     * @return \EoneoPay\Framework\Helpers\Interfaces\VersionHelperInterface
     */
    public function addApplication(string $name, string $host, $latestVersion = null): VersionHelperInterface
    {
        $name = (new Str())->studly($name);

        // $host is the key to allow one application to support multi-hosts
        $this->hosts[$host] = $name;
        $this->versions[$name] = $latestVersion ?? 1;

        return $this;
    }

    /**
     * Returns controllers namespace based on version from request.
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function getControllersNamespace(): string
    {
        if ($this->controllerNamespace !== null) {
            return $this->controllerNamespace;
        }

        // Call routes file path generation to ensure consistency about unsupported versions
        $this->getRoutesFileBasePath();

        $namespace = \sprintf('App\Http\Controllers\%s', $this->guessVersionFromRequest());

        if ($this->getApplicationName() === null) {
            return $this->controllerNamespace = $namespace;
        }

        return $this->controllerNamespace = \sprintf('%s\%s', $namespace, $this->getApplicationName());
    }

    /**
     * Returns routes file base path based on version from request.
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function getRoutesFileBasePath(): string
    {
        if ($this->routeFilePath !== null) {
            return $this->routeFilePath;
        }

        $application = $this->getApplicationName();
        $pattern = '%s/app/Http/Routes/%s.php';
        $version = $this->guessVersionFromRequest();

        // Try path with requested version
        $path = \sprintf(
            $pattern,
            $this->basePath,
            $this->generateRouteFilePath($version, $application)
        );

        // If doesn't exist fallback to latest version
        if (\file_exists($path) === false) {
            $version = $this->getLatestVersion();

            $path = \sprintf(
                $pattern,
                $this->basePath,
                $this->generateRouteFilePath($version, $application)
            );
        }
        // If still doesn't exist throw exception
        if (\file_exists($path) === false) {
            throw new UnsupportedVersionException(\sprintf(
                'App[%s]: Version %s requested, fallback to %s but files does not exist',
                $application ?? '<default>',
                $this->guessVersionFromRequest(),
                $this->getLatestVersion()
            ));
        }

        // Set requested version to last value assigned to ensure consistency
        // between controllers namespace and routes file
        $this->requestedVersion = $version;

        return $this->routeFilePath = $path;
    }

    /**
     * Get formatted version string.
     *
     * @param int|string $version
     *
     * @return string
     */
    private function formatVersion($version): string
    {
        return \sprintf('V%s', $version);
    }

    /**
     * Generate route file path for given version and application.
     *
     * @param string $version
     * @param null|string $application
     *
     * @return string
     */
    private function generateRouteFilePath(string $version, ?string $application = null): string
    {
        if ($application === null) {
            return $version;
        }

        return \sprintf('%s/%s', $version, $application);
    }

    /**
     * Get application name based on configured hosts and current request.
     *
     * @return null|string
     */
    private function getApplicationName(): ?string
    {
        if ($this->requestApplication !== null) {
            return $this->requestApplication;
        }

        return $this->requestApplication = $this->hosts[$this->request->getHost()] ?? null;
    }

    /**
     * Get latest version.
     *
     * @return string
     */
    private function getLatestVersion(): string
    {
        if ($this->getApplicationName() === null || isset($this->versions[$this->getApplicationName()]) === false) {
            return (string)$this->defaultVersion;
        }

        return $this->formatVersion($this->versions[$this->getApplicationName()]);
    }

    /**
     * Guess version from request.
     *
     * @return string
     */
    private function guessVersionFromRequest(): string
    {
        if ($this->requestedVersion !== null) {
            return $this->requestedVersion;
        }

        \preg_match('#\.v([\d]+)[\.\+]#i', $this->request->getHeader('accept', ''), $matches);

        if (isset($matches[1]) === false) {
            return $this->getLatestVersion();
        }

        return $this->requestedVersion = $this->formatVersion($matches[1]);
    }
}
