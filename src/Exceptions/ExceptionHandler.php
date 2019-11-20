<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\Bridge\Laravel\Traits\LaravelResponseTrait;
use EoneoPay\ApiFormats\External\Interfaces\Psr7\Psr7FactoryInterface;
use EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface;
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
use EoneoPay\Utils\Str;
use EoneoPay\Utils\UtcDateTime;
use EoneoPay\Utils\XmlConverter;
use Exception;
use Illuminate\Console\OutputStyle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Exceptions\Handler;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * @noinspection EfferentObjectCouplingInspection High coupling required to handle all exceptions
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) High coupling to filter exceptions
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity) High complexity required to cover all exception types
 */
abstract class ExceptionHandler extends Handler
{
    use LaravelResponseTrait;

    /**
     * Encoder instance.
     * This property is protected to allow children to use it, remember to use setEncoder(Request $request) first.
     *
     * @var \EoneoPay\ApiFormats\Interfaces\EncoderInterface
     */
    protected $encoder;

    /**
     * PSR7 factory instance.
     *
     * @var \EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface
     */
    private $encoderGuesser;

    /**
     * Logger instance.
     *
     * @var \EoneoPay\Externals\Logger\Interfaces\LoggerInterface
     */
    private $logger;

    /**
     * Translator instance.
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

        $this->dontReport = $this->getNonReportableExceptions();
    }

    /**
     * @noinspection MultipleReturnStatementsInspection PhpMissingParentCallCommonInspection
     * - Complexity required to handle multiple exception types
     * - Parent intentionally not called
     *
     * {@inheritdoc}
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    public function render($request, Exception $exception): Response
    {
        $this->setEncoder($request); // Set encoder to allow rendering methods to use it

        // Handle validation exceptions
        if ($exception instanceof ValidationExceptionInterface) {
            return $this->renderException(
                $exception,
                ['violations' => $exception->getErrors()]
            );
        }

        // Handle critical, runtime and other application exceptions
        if ($exception instanceof ExceptionInterface) {
            return $this->renderException($exception);
        }

        // Handle all symfony exceptions
        if ($exception instanceof HttpException) {
            return $this->renderUnsupportedException($exception, $exception->getStatusCode());
        }

        // Handle all other exceptions
        return $this->renderUnsupportedException($exception, 500);
    }

    /**
     * Extends the default renderForConsole to provide additional feedback
     * when the thrown exception is an instance of a ValidationException and
     * reports the validation failure information after the stack trace.
     *
     * {@inheritdoc}
     */
    public function renderForConsole($output, Exception $exception): void
    {
        // Translate the exception message
        $style = new OutputStyle(new ArrayInput([]), $output);
        $style->caution(\sprintf(
            'Translated exception message: %s',
            $this->getExceptionMessage($exception)
        ));

        parent::renderForConsole($output, $exception);

        if (($exception instanceof ValidationExceptionInterface) === false) {
            return; // @codeCoverageIgnore
        }

        /**
         * @var \EoneoPay\Utils\Interfaces\Exceptions\ValidationExceptionInterface $exception
         */
        $output->writeln('<error>Validation Failures:</error>');

        if (\count($exception->getErrors()) === 0) {
            $output->writeln('No validation errors in exception');

            return;
        }

        foreach ($exception->getErrors() as $key => $errors) {
            /** @var mixed[] $error */
            foreach ($errors as $error) {
                $output->writeln(\sprintf(
                    '<error>%s</error> - %s',
                    $key,
                    \json_encode($error)
                ));
            }
        }
    }

    /**
     * {@inheritdoc}
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

        $this->logger->exception($exception, $logLevel, ['class' => \get_class($exception)]);

        // Throw exception for the lumen handler
        parent::report($exception);
    }

    /**
     * Initiate the list of exceptions to suppress from the report method.
     *
     * @return string[]
     */
    abstract protected function getNonReportableExceptions(): array;

    /**
     * Set ApiFormats encoder for given request. This method is protected to allow children to use it.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return void
     */
    protected function setEncoder(Request $request): void
    {
        $this->encoder = $request->get('_encoder', $this->encoderGuesser->defaultEncoder());
    }

    /**
     * Get default message for an exception based on type.
     *
     * @param \Throwable $exception The exception to get the message for
     *
     * @return string
     */
    private function getDefaultExceptionMessage(Throwable $exception): string
    {
        if ($exception instanceof ClientExceptionInterface || $exception instanceof HttpException) {
            $message = $this->getErrorMessageFromStatusCode($exception->getStatusCode());
        }

        if ($exception instanceof CriticalExceptionInterface) {
            $message = $exception->getErrorMessage();
        }

        if ($exception instanceof InvalidApiResponseExceptionInterface) {
            $message = 'Invalid response received from external service.';
        }

        if ($exception instanceof ValidationExceptionInterface) {
            $message = 'Validation failed.';
        }

        return $message ?? 'An error occurred, try again shortly.';
    }

    /**
     * Get an exception message from a status code.
     *
     * @param int $statusCode
     *
     * @return string
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) High complexity required to cover all error codes
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength) Large method length required to cover all error codes
     */
    private function getErrorMessageFromStatusCode(int $statusCode): string
    {
        // Get message
        switch ($statusCode) {
            case 400: // @codeCoverageIgnore
                $message = 'Bad request.';
                break;

            case 401: // @codeCoverageIgnore
                $message = 'Unauthorised.';
                break;

            case 402: // @codeCoverageIgnore
                $message = 'Payment required.';
                break;

            case 403: // @codeCoverageIgnore
                $message = 'Forbidden.';
                break;

            case 404: // @codeCoverageIgnore
                $message = 'Not found.';
                break;

            case 405: // @codeCoverageIgnore
                $message = 'Method not allowed.';
                break;

            case 406: // @codeCoverageIgnore
                $message = 'Not acceptable.';
                break;

            case 407: // @codeCoverageIgnore
                $message = 'Proxy authentication required.';
                break;

            case 408: // @codeCoverageIgnore
                $message = 'Request time-out.';
                break;

            case 409: // @codeCoverageIgnore
                $message = 'Conflict.';
                break;

            case 410: // @codeCoverageIgnore
                $message = 'Gone.';
                break;

            case 411: // @codeCoverageIgnore
                $message = 'Length required.';
                break;

            case 412: // @codeCoverageIgnore
                $message = 'Precondition failed.';
                break;

            case 413: // @codeCoverageIgnore
                $message = 'Payload too large.';
                break;

            case 414: // @codeCoverageIgnore
                $message = 'URI too long.';
                break;

            case 415: // @codeCoverageIgnore
                $message = 'Unsupported media type.';
                break;

            case 416: // @codeCoverageIgnore
                $message = 'Range not satisfiable.';
                break;

            case 417: // @codeCoverageIgnore
                $message = 'Expectation failed.';
                break;

            case 418: // @codeCoverageIgnore
                $message = "I'm a teapot.";
                break;

            case 421: // @codeCoverageIgnore
                $message = 'Misdirected request.';
                break;

            case 422: // @codeCoverageIgnore
                $message = 'Unprocessable entity.';
                break;

            case 423: // @codeCoverageIgnore
                $message = 'Locked.';
                break;

            case 424: // @codeCoverageIgnore
                $message = 'Failed dependency.';
                break;

            case 425: // @codeCoverageIgnore
                $message = 'Too early.';
                break;

            case 426: // @codeCoverageIgnore
                $message = 'Upgrade required.';
                break;

            case 428: // @codeCoverageIgnore
                $message = 'Precondition required.';
                break;

            case 429: // @codeCoverageIgnore
                $message = 'Too many requests.';
                break;

            case 431: // @codeCoverageIgnore
                $message = 'Request header fields too large.';
                break;

            case 451: // @codeCoverageIgnore
                $message = 'Unavailable for legal reasons.';
                break;

            case 501: // @codeCoverageIgnore
                $message = 'Not implemented.';
                break;

            case 502: // @codeCoverageIgnore
                $message = 'Bad gateway.';
                break;

            case 503: // @codeCoverageIgnore
                $message = 'Service unavailable.';
                break;

            case 504: // @codeCoverageIgnore
                $message = 'Gateway time-out.';
                break;

            case 505: // @codeCoverageIgnore
                $message = 'HTTP version not supported.';
                break;

            case 506: // @codeCoverageIgnore
                $message = 'Variant also negotiates.';
                break;

            case 507: // @codeCoverageIgnore
                $message = 'Insufficient storage.';
                break;

            case 508: // @codeCoverageIgnore
                $message = 'Loop detected.';
                break;

            case 510: // @codeCoverageIgnore
                $message = 'Not extended.';
                break;

            case 511: // @codeCoverageIgnore
                $message = 'Network authentication required.';
                break;

            default:
                $message = 'Internal server error.';
                break;
        }

        return $message;
    }

    /**
     * Get exception message if not in production otherwise fallback to given default.
     *
     * @param \Throwable $exception The exception to get the message for
     *
     * @return mixed
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity) High complexity required to cover all exception types
     * @SuppressWarnings(PHPMD.NPathComplexity) High complexity required to cover all exception types
     */
    private function getExceptionMessage(Throwable $exception)
    {
        $message = \trim($exception->getMessage());

        // If the message is empty and can't be rendered, or if we're in production, return the default message
        if (($message === '' && ($exception instanceof InvalidApiResponseExceptionInterface) === false)
            || $this->inProduction() === true) {
            return $this->getDefaultExceptionMessage($exception);
        }

        $params = [];

        if (($exception instanceof ExceptionInterface) === true) {
            /**
             * @var \EoneoPay\Utils\Interfaces\Exceptions\ExceptionInterface $exception
             *
             * @see https://youtrack.jetbrains.com/issue/WI-37859 - typehint required until PhpStorm recognises ===
             */
            $params = $exception->getMessageParameters();
        }

        // Try to translate the message if possible
        $translated = $this->translator->trans($message, $params);

        // If there was a translation, return it
        if ($translated !== $message) {
            return $translated;
        }

        // Extract error from content
        if ($exception instanceof InvalidApiResponseExceptionInterface) {
            $content = $exception->getResponse()->getContent();
            $decoded = null;

            $str = new Str();

            // Attempt to decode xml
            if ($str->isXml($content)) {
                try {
                    $decoded = (new XmlConverter())->xmlToArray($content);
                    // @codeCoverageIgnoreStart
                } /** @noinspection BadExceptionsProcessingInspection */ catch (InvalidXmlException $xmlException) {
                    // This can safely be ignored and message will be displayed instead
                    // @codeCoverageIgnoreEnd
                }
            }

            // Attempt to decode json
            if ($str->isJson($content) === true) {
                $decoded = \json_decode($content, true);
            }

            if ($decoded !== null) {
                $message = $decoded;
            }
        }

        // Attempt to use exception message or default if no message is provided
        return $message ?: \sprintf('Unhandled %s was thrown, unable to continue.', \get_class($exception));
    }

    /**
     * Get exception timestamp.
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
     * Determine if we're in production or not.
     *
     * @return bool
     */
    private function inProduction(): bool
    {
        return (new Env())->get('APP_ENV') === 'production';
    }

    /**
     * Convert exception into response.
     *
     * @param \EoneoPay\Utils\Interfaces\Exceptions\ExceptionInterface $exception The exception to render
     * @param mixed[]|null $extra Additional content to add to the rendered result
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function renderException(ExceptionInterface $exception, ?array $extra = null): Response
    {
        $arr = new Arr();

        return $this->createLaravelResponseFromPsr($this->encoder->encode($arr->sort($arr->merge([
            'code' => $exception->getErrorCode(),
            'message' => $this->getExceptionMessage($exception),
            'sub_code' => $exception->getErrorSubCode(),
            'time' => $this->getTimestamp(),
        ], $extra ?? [])), $exception->getStatusCode()));
    }

    /**
     * Create response for unsupported exceptions.
     *
     * @param \Throwable $exception The exception to handle
     * @param int $statusCode The status code to return
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException If psr7 response is invalid
     * @throws \EoneoPay\Utils\Exceptions\InvalidDateTimeStringException If datetime constructor string is invalid
     */
    private function renderUnsupportedException(Throwable $exception, int $statusCode): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => $exception->getCode(),
            'message' => $this->getExceptionMessage($exception),
            'sub_code' => 0,
            'time' => $this->getTimestamp(),
        ], $statusCode ?: 500));
    }
}
