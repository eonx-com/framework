<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\External\Libraries\Psr7\Psr7Factory;
use EoneoPay\ApiFormats\RequestEncoderGuesser;
use EoneoPay\Framework\Exceptions\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubNotFoundException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubValidationFailedException;
use Tests\EoneoPay\Framework\Exceptions\Stubs\CriticalExceptionStub;
use Tests\EoneoPay\Framework\TestCases\TestCase;

class ExceptionHandlerTest extends TestCase
{
    /**
     * Test exception handler always return right response.
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    public function testRender(): void
    {
        $exceptions = [
            new \Exception(),
            new EntityStubNotFoundException(),
            new EntityStubValidationFailedException(null, null, null, []),
            new CriticalExceptionStub()
        ];
        $exceptionHandler = new ExceptionHandler(new Psr7Factory(), new RequestEncoderGuesser([]));

        foreach ($exceptions as $exception) {
            $response = $exceptionHandler->render(new Request(), $exception);

            /** @noinspection UnnecessaryAssertionInspection Ensure correct class is returned */
            self::assertInstanceOf(Response::class, $response);
        }
    }
}
