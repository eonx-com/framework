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
     * Requested version.
     *
     * @var string
     */
    private $version;

    /**
     * Requested application.
     *
     * @var null|string
     */
    private $application;

    /**
     * VersionHelper constructor.
     *
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     * @param string $basePath
     */
    public function __construct(RequestInterface $request, string $basePath, ?array $domainRoutingConfig = null)
    {
        $this->basePath = $basePath;
        $this->version = $this->guessVersionFromRequest($request);
        $this->application = $this->getApplicationType($request, $domainRoutingConfig);
    }

    /**
     * Get application type based on the current hostname and provided configuration array.
     *
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     * @param string[] $domainRoutingConfig
     *
     * @return string
     */
    public function getApplicationType(RequestInterface $request, ?array $domainRoutingConfig = null): ?string
    {
        if ($domainRoutingConfig === null) {
            return null;
        }

        $host = $request->getHost();
        if (\array_key_exists($request->getHost(), $domainRoutingConfig)) {
            return (new Str())->studly($domainRoutingConfig[$request->getHost()]);
        }
        return null;
    }

    /**
     * Returns controllers namespace based on version from request.
     *
     * @return string
     */
    public function getControllersNamespace(): string
    {
        $namespace = \sprintf('App\Http\Controllers\%s', $this->version);
        if ($this->application === null) {
            return $namespace;
        }
        return \sprintf('%s\%s', $namespace, $this->application);
    }

    private function getGeneratedPath(string $version, string $extra = null)
    {
        $path = $version;
        if ($extra === null) {
            return $path;
        }

        return sprintf('%s/%s', $version, $extra);
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
        $path = \sprintf('%s/app/Http/Routes/%s.php', $this->basePath, $this->getGeneratedPath($this->version, $this->application));

        if (\file_exists($path) === false) {
            $path = \sprintf('%s/app/Http/Routes/%s.php', $this->basePath, $this->getGeneratedPath($this->getLatestVersion(), $this->application));
        }

        if (\file_exists($path) === false) {
            throw new UnsupportedVersionException(\sprintf(
                'Version %s requested, fallback to %s but files does not exist',
                $this->version,
                $this->getLatestVersion()
            ));
        }

        return $path;
    }

    /**
     * Get latest version.
     *
     * @return string
     */
    private function getLatestVersion(): string
    {
        return \env('APP_LATEST_VERSION', 'V1');
    }

    /**
     * Guess version from request.
     *
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     *
     * @return string
     */
    private function guessVersionFromRequest(RequestInterface $request): string
    {
        \preg_match('#vnd.eoneopay.(v\d+)\+#i', $request->getHeader('accept', ''), $matches);

        $version = $matches[1] ?? $this->getLatestVersion();

        return \mb_strtoupper($version);
    }
}
