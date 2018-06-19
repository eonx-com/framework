<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\EncoderGuesser;
use EoneoPay\ApiFormats\External\Libraries\Psr7\Psr7Factory;
use EoneoPay\Framework\Exceptions\EntityNotFoundException;
use EoneoPay\Framework\Exceptions\ExceptionHandler;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubNotFoundException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubValidationFailedException;
use Tests\EoneoPay\Framework\Exceptions\Stubs\CriticalExceptionStub;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Due to all eventual exceptions to handle
 */
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
            new CriticalExceptionStub(),
            new Exception(),
            new EntityNotFoundException(),
            new EntityStubNotFoundException(),
            new EntityStubValidationFailedException(null, null, null, ['error' => ['test' => true]]),
            new NotFoundHttpException()
        ];
        $exceptionHandler = new ExceptionHandler(new Psr7Factory(), new EncoderGuesser([]));

        foreach ($exceptions as $exception) {
            if ($exception instanceof NotFoundHttpException) {
                \putenv('APP_ENV=production');
            }

            $response = $exceptionHandler->render(new Request(), $exception);

            /** @noinspection UnnecessaryAssertionInspection Ensure correct class is returned */
            self::assertInstanceOf(Response::class, $response);
        }
    }
}
