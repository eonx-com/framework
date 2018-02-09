<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers\Interfaces;

interface ResourceHelperInterface
{
    /**
     * Get resource for given path info.
     *
     * @param string $pathInfo
     *
     * @return string
     */
    public function getResourceForPathInfo(string $pathInfo): string;
}
