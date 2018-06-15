<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions;

use EoneoPay\Framework\Exceptions\EntityNotFoundException;
use EoneoPay\Utils\Interfaces\BaseExceptionInterface;
use Tests\EoneoPay\Framework\TestCases\TestCase;

class EntityNotFoundExceptionTest extends TestCase
{
    /**
     * Test exception always return right codes.
     *
     * @return void
     */
    public function testExceptionCodes(): void
    {
        $exception = new EntityNotFoundException();

        self::assertSame(BaseExceptionInterface::DEFAULT_ERROR_CODE_NOT_FOUND, $exception->getErrorCode());
        self::assertSame(BaseExceptionInterface::DEFAULT_ERROR_SUB_CODE, $exception->getErrorSubCode());
    }
}
