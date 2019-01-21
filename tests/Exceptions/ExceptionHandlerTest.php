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
use Illuminate\Http\Response;
use Illuminate\Translation\FileLoader;
use Illuminate\Translation\Translator as ContractedTranslator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubNotFoundException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubValidationFailedException;
use Tests\EoneoPay\Framework\Exceptions\Stubs\ClientExceptionStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\CriticalExceptionStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\LoggerStub;
use Tests\EoneoPay\Framework\Exceptions\Stubs\RuntimeExceptionStub;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @noinspection EfferentObjectCouplingInspection High coupling required to full test handler
 *
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
     * Test messages don't expose information in production
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
        self::assertSame('exceptions.messages.unknown', $content['message'] ?? 'error');

        // Reset environment
        $env->set('APP_ENV', $environment);
    }

    /**
     * Test default messages for client exceptions
     *
     * @return void
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    public function testDefaultMessagesForClientExceptions(): void
    {
        $codes = [
            400 => 'exceptions.messages.client_error',
            401 => 'exceptions.messages.unauthorised',
            403 => 'exceptions.messages.forbidden',
            404 => 'exceptions.messages.not_found'
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
     * Test invalid api response is handled correctly
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
                'keyb' => 'test2'
            ]
        ];

        $exceptionHandler = $this->createExceptionHandler();
        $request = new Request();

        // Build an xml response
        $response = new ApiResponse($data, 400, [], (new XmlConverter())->arrayToXml($data));
        $exception = new InvalidApiResponseException($response);
        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];

        self::assertIsArray($content);
        self::assertSame(\array_merge($data, ['@rootNode' => 'data']), $content['message'] ?? []);

        // Build a json response
        $response = new ApiResponse($data, 400, [], \json_encode($data) ?: '');
        $exception = new InvalidApiResponseException($response);
        $content = \json_decode($exceptionHandler->render($request, $exception)->content(), true) ?: [];

        self::assertIsArray($content);
        self::assertSame($data, $content['message'] ?? []);

        // Build a standard response
        $response = new ApiResponse($data, 400, [], 'Testing');
        $exception = new InvalidApiResponseException($response);
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
     * Create exception handler instance
     *
     * @return \EoneoPay\Framework\Exceptions\ExceptionHandler
     */
    private function createExceptionHandler(): ExceptionHandler
    {
        return new ExceptionHandler(
            new EncoderGuesser([]),
            $this->logger,
            new Psr7Factory(),
            new Translator(new ContractedTranslator(new FileLoader(new ContractedFilesystem(), __DIR__), 'en'))
        );
    }
}
