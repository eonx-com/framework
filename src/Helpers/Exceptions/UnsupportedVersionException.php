<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers\Exceptions;

use EoneoPay\Utils\Exceptions\RuntimeException;

class UnsupportedVersionException extends RuntimeException
{
    /**
     * @inheritdoc
     */
    public function getErrorCode(): int
    {
        return self::DEFAULT_ERROR_CODE_RUNTIME;
    }

    /**
     * @inheritdoc
     */
    public function getErrorSubCode(): int
    {
        return self::DEFAULT_ERROR_SUB_CODE;
    }
}
