<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Helpers\Exceptions;

use EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @covers \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
 */
class UnsupportedVersionExceptionTest extends TestCase
{
    /**
     * Test exception returns the correct error codes.
     *
     * @return void
     */
    public function testExceptionGetters(): void
    {
        $exception = new UnsupportedVersionException();

        self::assertSame(1100, $exception->getErrorCode());
        self::assertSame(0, $exception->getErrorSubCode());
    }
}
