<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers\Interfaces;

interface VersionHelperInterface
{
    /**
     * Returns controllers namespace based on version from request.
     *
     * @return string
     */
    public function getControllersNamespace(): string;

    /**
     * Returns routes file base path based on version from request.
     *
     * @return string
     */
    public function getRoutesFileBasePath(): string;
}
