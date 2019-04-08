<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\Bridge\Laravel\Traits\LaravelResponseTrait;
use EoneoPay\ApiFormats\External\Interfaces\Psr7\Psr7FactoryInterface;
use EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface;
use EoneoPay\ApiFormats\Interfaces\EncoderInterface;
use EoneoPay\Externals\Environment\Env;
use EoneoPay\Externals\HttpClient\Interfaces\InvalidApiResponseExceptionInterface;
use EoneoPay\Externals\Logger\Interfaces\LoggerInterface;
use EoneoPay\Externals\Translator\Interfaces\TranslatorInterface;
use EoneoPay\Utils\Arr;
use EoneoPay\Utils\Exceptions\InvalidXmlException;
use EoneoPay\Utils\Interfaces\Exceptions\ClientExceptionInterface;
use EoneoPay\Utils\Interfaces\Exceptions\CriticalExceptionInterface;
use EoneoPay\Utils\Interfaces\Exceptions\ExceptionInterface;
use EoneoPay\Utils\Interfaces\Exceptions\RuntimeExceptionInterface;
use EoneoPay\Utils\Interfaces\Exceptions\ValidationExceptionInterface;
use EoneoPay\Utils\UtcDateTime;
use EoneoPay\Utils\XmlConverter;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Exceptions\Handler;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * @noinspection EfferentObjectCouplingInspection High coupling required to handle all exceptions
 *
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
     * Translator instance
     *
     * @var \EoneoPay\Externals\Translator\Interfaces\TranslatorInterface
     */
    private $translator;

    /**
     * Create a new exception handler and set classes which should be ignored.
     *
     * @param \EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface $encoderGuesser Encoder guesser instance
     * @param \EoneoPay\Externals\Logger\Interfaces\LoggerInterface $logger Logger instance
     * @param \EoneoPay\ApiFormats\External\Interfaces\Psr7\Psr7FactoryInterface $psr7Factory PSR7 factory
     * @param \EoneoPay\Externals\Translator\Interfaces\TranslatorInterface $translator Translator instance
     */
    public function __construct(
        EncoderGuesserInterface $encoderGuesser,
        LoggerInterface $logger,
        Psr7FactoryInterface $psr7Factory,
        TranslatorInterface $translator
    ) {
        $this->encoderGuesser = $encoderGuesser;
        $this->logger = $logger;
        $this->psr7Factory = $psr7Factory;
        $this->translator = $translator;
    }

    /**
     * @noinspection PhpMissingParentCallCommonInspection Parent intentionally not called
     *
     * @inheritdoc
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    public function render($request, Exception $exception): Response
    {
        $this->encoder = $this->getEncoder($request);

        if ($exception instanceof ClientExceptionInterface) {
            return $this->handleClientException($exception);
        }

        if ($exception instanceof CriticalExceptionInterface) {
            return $this->renderException($exception, $exception->getErrorMessage());
        }

        if ($exception instanceof NotFoundHttpException) {
            return $this->renderUnsupportedException(
                $exception,
                $this->translator->trans('exceptions.messages.not_found'),
                404
            );
        }

        if ($exception instanceof ValidationExceptionInterface) {
            return $this->renderValidationException($exception);
        }

        if ($exception instanceof InvalidApiResponseExceptionInterface) {
            return $this->renderExternalApiException(
                $exception,
                (string)$this->translator->trans('exceptions.messages.invalid_response')
            );
        }

        // Catch any other exceptions using the interface
        if ($exception instanceof ExceptionInterface) {
            return $this->renderException($exception, (string)$this->translator->trans('exceptions.messages.unknown'));
        }

        // Handle all other exceptions
        return $this->renderUnsupportedException($exception);
    }

    /**
     * @inheritdoc
     *
     * @throws \Exception If internal logger can't be instantiated from container
     */
    public function report(Exception $exception): void
    {
        // Log all exceptions with notice log level
        $logLevel = null;

        // If exception is runtime bump up to error
        if ($exception instanceof RuntimeExceptionInterface) {
            $logLevel = 'error';
        }

        // If exception is critical bump up to critical
        if ($exception instanceof CriticalExceptionInterface) {
            $logLevel = 'critical';
        }

        $this->logger->exception($exception, $logLevel);

        // Throw exception for the lumen handler
        parent::report($exception);
    }

    /**
     * Get encoder for given request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\EncoderInterface
     */
    private function getEncoder(Request $request): EncoderInterface
    {
        return $this->encoder ?? $this->encoder = $request->get('_encoder', $this->encoderGuesser->defaultEncoder());
    }

    /**
     * Get exception message if not in production otherwise fallback to given default.
     *
     * @param \Throwable $exception The exception to get the message for
     * @param string $default The default message to use if in production or exception message is missing
     *
     * @return string
     */
    private function getExceptionMessage(Throwable $exception, string $default): string
    {
        return $this->inProduction() === false && empty($exception->getMessage()) === false ?
            $exception->getMessage() :
            $default;
    }

    /**
     * Get exception timestamp
     *
     * @return string
     *
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function getTimestamp(): string
    {
        return (new UtcDateTime('now'))->getZulu();
    }

    /**
     * Handle a client exception (40x error that is not validation related)
     *
     * @param \EoneoPay\Utils\Interfaces\Exceptions\ClientExceptionInterface $exception The exception to handle
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function handleClientException(ClientExceptionInterface $exception): Response
    {
        // Get message
        switch ($exception->getStatusCode()) {
            case 401:
                $message = $this->translator->trans('exceptions.messages.unauthorised');
                break;

            case 403:
                $message = $this->translator->trans('exceptions.messages.forbidden');
                break;

            case 404:
                $message = $this->translator->trans('exceptions.messages.not_found');
                break;

            case 409:
                $message = $this->translator->trans('exceptions.messages.conflict');
                break;

            default:
                $message = $this->translator->trans('exceptions.messages.client_error');
                break;
        }

        return $this->renderException($exception, (string)$message);
    }

    /**
     * Determine if we're in production or not
     *
     * @return bool
     */
    private function inProduction(): bool
    {
        return (new Env())->get('APP_ENV') === 'production';
    }

    /**
     * Determine if a string is json
     *
     * @param string $string The string to check
     *
     * @return bool
     */
    private function isJson(string $string): bool
    {
        \json_decode($string);

        return \json_last_error() === \JSON_ERROR_NONE;
    }

    /**
     * Convert exception into response
     *
     * @param \EoneoPay\Utils\Interfaces\Exceptions\ExceptionInterface $exception The exception to render
     * @param string $message The default message to use when displaying this exception in production
     * @param mixed[]|null $extra Additional content to add to the rendered result
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function renderException(ExceptionInterface $exception, string $message, ?array $extra = null): Response
    {
        $arr = new Arr();

        return $this->createLaravelResponseFromPsr($this->encoder->encode($arr->sort($arr->merge([
            'code' => $exception->getErrorCode(),
            'message' => $this->getExceptionMessage($exception, $message),
            'sub_code' => $exception->getErrorSubCode(),
            'time' => $this->getTimestamp()
        ], $extra ?? [])), $exception->getStatusCode()));
    }

    /**
     * Create response for invalid api exceptions
     *
     * @param \EoneoPay\Externals\HttpClient\Interfaces\InvalidApiResponseExceptionInterface $exception The exception
     * @param string|null $message The message to display in production
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function renderExternalApiException(
        InvalidApiResponseExceptionInterface $exception,
        ?string $message = null
    ): Response {
        // Get content if we're not in production
        if ($this->inProduction() === false) {
            $content = $decoded = $exception->getResponse()->getContent();

            // Attempt to decode json
            if ($this->isJson($content) === true) {
                $decoded = \json_decode($content) ?: $message;
            }

            // Attempt to decode xml
            try {
                $decoded = (new XmlConverter())->xmlToArray($content);
            } /** @noinspection BadExceptionsProcessingInspection */ catch (InvalidXmlException $xmlException) {
                // This can safely be ignored and message will be displayed instead
            }
        }

        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => $exception->getErrorCode(),
            'message' => $decoded ?? $message ?? (string)$this->translator->trans('exceptions.messages.unknown'),
            'sub_code' => $exception->getErrorSubCode(),
            'time' => $this->getTimestamp()
        ], $exception->getResponse()->getStatusCode()));
    }

    /**
     * Create response for unsupported exceptions.
     *
     * @param \Throwable $exception The exception to handle
     * @param string|null $message The message to display in production
     * @param int|null $statusCode The status code to return
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function renderUnsupportedException(
        Throwable $exception,
        ?string $message = null,
        ?int $statusCode = null
    ): Response {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => $exception->getCode(),
            'message' => $this->getExceptionMessage(
                $exception,
                $message ?? (string)$this->translator->trans('exceptions.messages.unknown')
            ),
            'sub_code' => 0,
            'time' => $this->getTimestamp()
        ], $statusCode ?? 500));
    }

    /**
     * Handle a validation exception
     *
     * @param \EoneoPay\Utils\Interfaces\Exceptions\ValidationExceptionInterface $exception The exception to handle
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function renderValidationException(ValidationExceptionInterface $exception): Response
    {
        return $this->renderException(
            $exception,
            (string)$this->translator->trans('exceptions.messages.validation_error'),
            ['violations' => $exception->getErrors()]
        );
    }
}
