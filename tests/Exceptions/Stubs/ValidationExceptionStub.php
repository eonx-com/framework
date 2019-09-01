<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Utils\Exceptions\ValidationException;

/**
 * @coversNothing
 */
class ValidationExceptionStub extends ValidationException
{
    /**
     * {@inheritdoc}
     */
    public function getErrorCode(): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorSubCode(): int
    {
        return 0;
    }
}
