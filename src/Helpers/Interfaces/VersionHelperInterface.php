<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers\Interfaces;

interface VersionHelperInterface
{
    /**
     * Add application.
     *
     * @param string $name The name of the application.
     * @param string $host The HTTP host of the application (can be a regex).
     * @param null|int|string $latestVersion The latest version of the application (default to 1).
     *
     * @return \EoneoPay\Framework\Helpers\Interfaces\VersionHelperInterface
     */
    public function addApplication(string $name, string $host, $latestVersion = null): self;

    /**
     * Returns controllers namespace based on version from request.
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function getControllersNamespace(): string;

    /**
     * Returns routes file base path based on version from request.
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function getRoutesFileBasePath(): string;
}
