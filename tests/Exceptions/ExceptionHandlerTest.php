<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\EncoderGuesser;
use EoneoPay\ApiFormats\External\Libraries\Psr7\Psr7Factory;
use EoneoPay\Framework\Exceptions\EntityNotFoundException;
use EoneoPay\Framework\Exceptions\ExceptionHandler;
use EoneoPay\Utils\Exceptions\CriticalException;
use EoneoPay\Utils\Exceptions\RuntimeException;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubNotFoundException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubValidationFailedException;
use Tests\EoneoPay\Framework\Exceptions\Stubs\CriticalExceptionStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\LoggerStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\RuntimeExceptionStub;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Due to all eventual exceptions to handle
 */
class ExceptionHandlerTest extends TestCase
{
    /**
     * Exceptions to test against
     *
     * @var \Exception[]
     */
    private $exceptions;

    /**
     * Logger instance
     *
     * @var \Tests\EoneoPay\Framework\Exceptions\Stubs\LoggerStub
     */
    private $logger;

    /**
     * Set up exception list
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->exceptions = [
            new CriticalExceptionStub(),
            new Exception(),
            new EntityNotFoundException(),
            new EntityStubNotFoundException(),
            new EntityStubValidationFailedException(null, null, null, ['error' => ['test' => true]]),
            new NotFoundHttpException(),
            new RuntimeExceptionStub()
        ];

        $this->logger = new LoggerStub();
    }

    /**
     * Test exception handler always return right response.
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    public function testRender(): void
    {
        $exceptionHandler = $this->createExceptionHandler();

        foreach ($this->exceptions as $exception) {
            if ($exception instanceof NotFoundHttpException) {
                \putenv('APP_ENV=production');
            }

            $response = $exceptionHandler->render(new Request(), $exception);

            /** @noinspection UnnecessaryAssertionInspection Ensure correct class is returned */
            self::assertInstanceOf(Response::class, $response);
        }
    }

    /**
     * Test reporting an exception
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testReport(): void
    {
        $exceptionHandler = $this->createExceptionHandler();

        foreach ($this->exceptions as $exception) {
            $exceptionHandler->report($exception);

            // Check logger
            if ($exception instanceof CriticalException) {
                self::assertSame('critical', $this->logger->getLogLevel());
                continue;
            }

            if ($exception instanceof RuntimeException) {
                self::assertSame('error', $this->logger->getLogLevel());
                continue;
            }

            /** @noinspection DisconnectedForeachInstructionInspection Fall through if type is unknown */
            self::assertSame('notice', $this->logger->getLogLevel());
        }
    }

    /**
     * Create exception handler instance
     *
     * @return \EoneoPay\Framework\Exceptions\ExceptionHandler
     */
    private function createExceptionHandler(): ExceptionHandler
    {
        return new ExceptionHandler(new EncoderGuesser([]), $this->logger, new Psr7Factory());
    }
}
