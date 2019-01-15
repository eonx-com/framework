<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Utils\Exceptions\CriticalException;

class CriticalExceptionStub extends CriticalException
{
    /**
     * @inheritdoc
     */
    public function getErrorCode(): int
    {
        return self::DEFAULT_ERROR_CODE_CRITICAL;
    }

    /**
     * @inheritdoc
     */
    public function getErrorMessage(): string
    {
        return 'Critical exception';
    }

    /**
     * @inheritdoc
     */
    public function getErrorSubCode(): int
    {
        return self::DEFAULT_ERROR_SUB_CODE;
    }
}
