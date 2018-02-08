<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers\Interfaces;

interface EndpointHelperInterface
{
    /**
     * Get endpoint pattern for given path info.
     *
     * @param string $pathInfo
     *
     * @return string
     */
    public function getPatternForPathInfo(string $pathInfo): string;
}
