<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\Bridge\Laravel\Traits\LaravelResponseTrait;
use EoneoPay\ApiFormats\External\Interfaces\Psr7\Psr7FactoryInterface;
use EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface;
use EoneoPay\ApiFormats\Interfaces\EncoderInterface;
use EoneoPay\Externals\Logger\Interfaces\LoggerInterface;
use EoneoPay\Utils\Exceptions\CriticalException;
use EoneoPay\Utils\Exceptions\NotFoundException;
use EoneoPay\Utils\Exceptions\RuntimeException;
use EoneoPay\Utils\Exceptions\ValidationException;
use EoneoPay\Utils\Interfaces\BaseExceptionInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Exceptions\Handler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) High coupling to filter exceptions
 */
class ExceptionHandler extends Handler
{
    use LaravelResponseTrait;

    /**
     * Encoder instance
     *
     * @var \EoneoPay\ApiFormats\Interfaces\EncoderInterface
     */
    protected $encoder;

    /**
     * PSR7 factory instance
     *
     * @var \EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface
     */
    private $encoderGuesser;

    /**
     * Logger instance
     *
     * @var \EoneoPay\Externals\Logger\Interfaces\LoggerInterface
     */
    private $logger;

    /**
     * Create a new exception handler and set classes which should be ignored.
     *
     * @param \EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface $encoderGuesser Encoder guesser instance
     * @param \EoneoPay\Externals\Logger\Interfaces\LoggerInterface $logger Logger instance
     * @param \EoneoPay\ApiFormats\External\Interfaces\Psr7\Psr7FactoryInterface $psr7Factory PSR7 factory
     */
    public function __construct(
        EncoderGuesserInterface $encoderGuesser,
        LoggerInterface $logger,
        Psr7FactoryInterface $psr7Factory
    ) {
        $this->encoderGuesser = $encoderGuesser;
        $this->logger = $logger;
        $this->psr7Factory = $psr7Factory;
    }

    /** @noinspection PhpMissingParentCallCommonInspection Parent intentionally not called */
    /**
     * {@inheritdoc}
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    public function render($request, Exception $exception): Response
    {
        $this->encoder = $this->getEncoder($request);

        if ($exception instanceof NotFoundHttpException) {
            return $this->httpNotFoundResponse($exception);
        }

        if ($exception instanceof NotFoundException) {
            return $this->entityNotFoundResponse($exception);
        }

        if ($exception instanceof ValidationException) {
            return $this->validationExceptionResponse($exception);
        }

        if ($exception instanceof CriticalException) {
            return $this->criticalExceptionResponse($exception);
        }

        return $this->unsupportedExceptionResponse($exception);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception If internal logger can't be instantiated from container
     */
    public function report(Exception $exception): void
    {
        // Log all exceptions with notice log level
        $this->logger->exception($exception);

        // If exception is runtime bump up to error
        if ($exception instanceof RuntimeException) {
            $this->logger->exception($exception, 'error');
        }

        // If exception is critical bump up to critical
        if ($exception instanceof CriticalException) {
            $this->logger->exception($exception, 'critical');
        }

        // Throw exception for the lumen handler
        parent::report($exception);
    }

    /**
     * Create response for critical exceptions.
     *
     * @param \EoneoPay\Utils\Exceptions\CriticalException $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function criticalExceptionResponse(CriticalException $exception): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => BaseExceptionInterface::DEFAULT_ERROR_CODE_RUNTIME,
            'sub_code' => BaseExceptionInterface::DEFAULT_ERROR_SUB_CODE,
            'time' => $this->getTimestamp(),
            'message' => $this->getExceptionMessage($exception, $exception->getErrorMessage())
        ], 503));
    }

    /**
     * Create response for entity not found exceptions.
     *
     * @param \EoneoPay\Utils\Exceptions\NotFoundException $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function entityNotFoundResponse(NotFoundException $exception): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => $exception->getErrorCode(),
            'sub_code' => $exception->getErrorSubCode(),
            'time' => $this->getTimestamp(),
            'message' => $exception->getMessage()
        ], $exception->getStatusCode()));
    }

    /**
     * Get encoder for given request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\EncoderInterface
     */
    protected function getEncoder(Request $request): EncoderInterface
    {
        if ($this->encoder !== null) {
            return $this->encoder;
        }

        $this->encoder = $request->get('_encoder', $this->encoderGuesser->defaultEncoder());

        return $this->encoder;
    }

    /**
     * Get exception message if not in production otherwise fallback to given default.
     *
     * @param \Exception $exception
     * @param string $default
     *
     * @return string
     */
    protected function getExceptionMessage(Exception $exception, string $default): string
    {
        if (\env('APP_ENV') !== 'production') {
            return empty($exception->getMessage()) === false ? $exception->getMessage() : $default;
        }

        return $default;
    }

    /**
     * Create response for HTTP not found exceptions.
     *
     * @param \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function httpNotFoundResponse(NotFoundHttpException $exception): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => BaseExceptionInterface::DEFAULT_ERROR_CODE_NOT_FOUND,
            'sub_code' => BaseExceptionInterface::DEFAULT_ERROR_SUB_CODE,
            'time' => $this->getTimestamp(),
            'message' => $this->getExceptionMessage($exception, 'Not found')
        ], 404));
    }

    /**
     * Create response for unsupported exceptions.
     *
     * @param \Exception $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function unsupportedExceptionResponse(Exception $exception): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => BaseExceptionInterface::DEFAULT_ERROR_CODE_RUNTIME,
            'sub_code' => BaseExceptionInterface::DEFAULT_ERROR_SUB_CODE,
            'time' => $this->getTimestamp(),
            'message' => $this->getExceptionMessage($exception, 'Something went wrong')
        ], BaseExceptionInterface::DEFAULT_STATUS_CODE_RUNTIME));
    }

    /**
     * Create response for validation exceptions.
     *
     * @param \EoneoPay\Utils\Exceptions\ValidationException $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function validationExceptionResponse(ValidationException $exception): Response
    {
        $error = [
            'code' => $exception->getErrorCode(),
            'sub_code' => $exception->getErrorSubCode(),
            'time' => $this->getTimestamp(),
            'message' => $exception->getMessage()
        ];

        // Only add violations if they exist
        if (\count($exception->getErrors()) > 0) {
            $error['violations'] = $exception->getErrors();
        }

        return $this->createLaravelResponseFromPsr($this->encoder->encode($error, $exception->getStatusCode()));
    }

    /**
     * Get exception timestamp
     *
     * @return string
     */
    private function getTimestamp(): string
    {
        return \gmdate('Y-m-d\TH:i:s\Z');
    }
}
