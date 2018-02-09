<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers\Interfaces;

use Illuminate\Http\Request;

interface ResourceHelperInterface
{
    /**
     * Get resource for given request.
     *
     * @param Request $request
     *
     * @return string
     */
    public function getResourceForRequest(Request $request): string;
}
