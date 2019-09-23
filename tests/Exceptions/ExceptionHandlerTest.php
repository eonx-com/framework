<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\EncoderGuesser;
use EoneoPay\ApiFormats\External\Libraries\Psr7\Psr7Factory;
use EoneoPay\Externals\Bridge\Laravel\Translator;
use EoneoPay\Externals\Environment\Env;
use EoneoPay\Externals\HttpClient\Exceptions\InvalidApiResponseException;
use EoneoPay\Externals\HttpClient\Response as ApiResponse;
use EoneoPay\Framework\Exceptions\EntityNotFoundException;
use EoneoPay\Framework\Exceptions\ExceptionHandler;
use EoneoPay\Utils\Exceptions\CriticalException;
use EoneoPay\Utils\Exceptions\RuntimeException;
use EoneoPay\Utils\XmlConverter;
use Exception;
use Illuminate\Filesystem\Filesystem as ContractedFilesystem;
use Illuminate\Http\Request;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator as ContractedTranslator;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubNotFoundException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubValidationFailedException;
use Tests\EoneoPay\Framework\Exceptions\Stubs\ClientExceptionStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\CriticalExceptionStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\ExceptionHandlerStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\LoggerStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\RuntimeExceptionStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\ValidationExceptionStub;
use Tests\EoneoPay\Framework\TestCases\TestCase;
use Zend\Diactoros\Request as PsrRequest;
use Zend\Diactoros\Response as PsrResponse;
use Zend\Diactoros\Stream;

/**
 * @noinspection EfferentObjectCouplingInspection High coupling required to full test handler
 *
 * @covers \EoneoPay\Framework\Exceptions\ExceptionHandler
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Due to all eventual exceptions to handle
 */
class ExceptionHandlerTest extends TestCase
{
    /**
     * Exceptions to test against.
     *
     * @var \Exception[]
     */
    private $exceptions;

    /**
     * Logger instance.
     *
     * @var \Tests\EoneoPay\Framework\Exceptions\Stubs\LoggerStub
     */
    private $logger;

    /**
     * Set up exception list.
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
            new EntityStubValidationFailedException(null, null, null, null, ['error' => ['test' => true]]),
            new NotFoundHttpException(),
            new RuntimeExceptionStub(),
        ];

        $this->logger = new LoggerStub();
    }

    /**
     * Test messages don't expose information in production.
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    public function testDefaultMessageUsedInProduction(): void
    {
        $exceptionHandler = $this->createExceptionHandler();
        $request = new Request();
        $exception = new RuntimeExceptionStub('Test message');

        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];
        self::assertSame('Test message', $content['message'] ?? 'error');

        // Switch to production and test again
        $env = new Env();
        $environment = $env->get('APP_ENV');
        $env->set('APP_ENV', 'production');

        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];
        self::assertSame('An unknown error occured.', $content['message'] ?? 'error');

        // Reset environment
        $env->set('APP_ENV', $environment);
    }

    /**
     * Test default messages for client exceptions.
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    public function testDefaultMessagesForClientExceptions(): void
    {
        $codes = [
            400 => 'Bad request.',
            401 => 'Unauthorised.',
            403 => 'Forbidden.',
            404 => 'Not found.',
            406 => 'Not acceptable.',
            409 => 'Conflict.',
        ];

        $exceptionHandler = $this->createExceptionHandler();
        $request = new Request();

        foreach ($codes as $code => $message) {
            $exception = new ClientExceptionStub();
            $exception->setStatusCode($code);

            $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];
            self::assertSame($message, $content['message'] ?? 'error');
        }
    }

    /**
     * Test invalid api response is handled correctly.
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidXmlTagException Inherited, if xml contains an invalid tag
     */
    public function testInvalidApiResponseException(): void
    {
        $data = [
            'key' => 'data',
            'subkey' => [
                'keya' => 'test',
                'keyb' => 'test2',
            ],
        ];

        $exceptionHandler = $this->createExceptionHandler();
        $request = new Request();

        // Build an xml response
        $response = $this->createApiResponse((new XmlConverter())->arrayToXml($data), 400);
        $exception = new InvalidApiResponseException(new PsrRequest(), $response);
        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];

        self::assertIsArray($content);
        self::assertSame(\array_merge($data, ['@rootNode' => 'data']), $content['message'] ?? []);

        // Build a json response
        $response = $this->createApiResponse(\json_encode($data) ?: '', 400);
        $exception = new InvalidApiResponseException(new PsrRequest(), $response);
        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];

        self::assertIsArray($content);
        self::assertSame($data, $content['message'] ?? []);

        // Build a standard response
        $response = $this->createApiResponse('Testing', 400);
        $exception = new InvalidApiResponseException(new PsrRequest(), $response);
        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];

        self::assertIsArray($content);
        self::assertSame('Testing', $content['message'] ?? []);
    }

    /**
     * Test exception handler always return right response.
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    public function testRender(): void
    {
        $exceptionHandler = $this->createExceptionHandler();

        foreach ($this->exceptions as $exception) {
            $exceptionHandler->render(new Request(), $exception);
        }

        // If exception wasn't thrown the test is good
        $this->addToAssertionCount(1);
    }

    /**
     * Test exception handler always return right response.
     *
     * @return void
     */
    public function testRenderForConsole(): void
    {
        $exceptionHandler = $this->createExceptionHandler();

        $output = new BufferedOutput();
        $exception = new ValidationExceptionStub(
            null,
            null,
            null,
            null,
            [
                'property' => [
                    'Property must not be null',
                ],
            ]
        );
        $exceptionHandler->renderForConsole($output, $exception);

        self::assertStringContainsString('property - "Property must not be null"', $output->fetch());
    }

    /**
     * Test exception handler always return right response.
     *
     * @return void
     */
    public function testRenderForConsoleEmptyException(): void
    {
        $exceptionHandler = $this->createExceptionHandler();

        $output = new BufferedOutput();
        $exception = new ValidationExceptionStub();
        $exceptionHandler->renderForConsole($output, $exception);

        self::assertStringContainsString('No validation errors in exception', $output->fetch());
    }

    /**
     * Test reporting an exception.
     *
     * @return void
     *
     * @throws \Exception
     */
    public function testReport(): void
    {
        $exceptionHandler = $this->createExceptionHandler();

        foreach ($this->exceptions as $exception) {
            try {
                $exceptionHandler->report($exception);
            } /** @noinspection BadExceptionsProcessingInspection */ catch (Exception $exception) {
                // Report will re-throw exception, ignore
            }

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
     * Create an api response object.
     *
     * @param string $content The content to set on the response
     * @param int $statusCode The status code to set on the response
     *
     * @return \EoneoPay\Externals\HttpClient\Response
     */
    private function createApiResponse(string $content, int $statusCode): ApiResponse
    {
        $stream = \fopen('php://temp', 'rb+');

        if (\is_resource($stream) === false) {
            self::fail('Unable to create stream for api response.');
        }

        if ($content !== '') {
            \fwrite($stream, $content);
            \fseek($stream, 0);
        }

        return new ApiResponse(new PsrResponse(new Stream($stream), $statusCode));
    }

    /**
     * Create exception handler instance.
     *
     * @return \EoneoPay\Framework\Exceptions\ExceptionHandler
     */
    private function createExceptionHandler(): ExceptionHandler
    {
        return new ExceptionHandlerStub(
            new EncoderGuesser([]),
            $this->logger,
            new Psr7Factory(),
            new Translator(new ContractedTranslator(new FileLoader(new ContractedFilesystem(), __DIR__), 'en'))
        );
    }
}
