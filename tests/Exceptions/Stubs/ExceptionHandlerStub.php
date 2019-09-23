<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Framework\Exceptions\ExceptionHandler;

class ExceptionHandlerStub extends ExceptionHandler
{
    /**
     * Initiate the list of exceptions to suppress from the report method.
     *
     * @return string[]
     */
    protected function getNonReportableExceptions(): array
    {
        return [];
    }
}
