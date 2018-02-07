<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Exceptions;

use EoneoPay\ApiFormats\Bridge\Laravel\Traits\LaravelResponseTrait;
use EoneoPay\ApiFormats\External\Interfaces\Psr7FactoryInterface;
use EoneoPay\ApiFormats\Interfaces\RequestEncoderGuesserInterface;
use EoneoPay\ApiFormats\Interfaces\RequestEncoderInterface;
use EoneoPay\External\ORM\Exceptions\EntityValidationException;
use EoneoPay\Utils\Exceptions\CriticalException;
use EoneoPay\Utils\Exceptions\NotFoundException;
use EoneoPay\Utils\Exceptions\RuntimeException;
use EoneoPay\Utils\Interfaces\BaseExceptionInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Lumen\Exceptions\Handler;

class ExceptionHandler extends Handler
{
    use LaravelResponseTrait;

    /**
     * @var RequestEncoderInterface
     */
    private $encoder;

    /**
     * @var \EoneoPay\ApiFormats\Interfaces\RequestEncoderGuesserInterface
     */
    private $encoderGuesser;

    /**
     * Create a new exception handler and set classes which should be ignored.
     *
     * @param \EoneoPay\ApiFormats\External\Interfaces\Psr7FactoryInterface $psr7Factory
     * @param \EoneoPay\ApiFormats\Interfaces\RequestEncoderGuesserInterface $encoderGuesser
     */
    public function __construct(Psr7FactoryInterface $psr7Factory, RequestEncoderGuesserInterface $encoderGuesser)
    {
        $this->psr7Factory = $psr7Factory;
        $this->encoderGuesser = $encoderGuesser;
    }

    /* @noinspection PhpMissingParentCallCommonInspection Avoid non-formatted response from API when unsupported exceptions */
    /**
     * @param \Illuminate\Http\Request $request
     * @param \Exception $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    public function render($request, Exception $exception): Response
    {
        $this->encoder = $this->getEncoder($request);

        if ($exception instanceof NotFoundException) {
            /** @var RuntimeException $exception */
            return $this->entityNotFoundResponse($exception);
        }

        if ($exception instanceof EntityValidationException) {
            return $this->entityValidationExceptionResponse($exception);
        }

        if ($exception instanceof CriticalException) {
            return $this->criticalExceptionResponse($exception);
        }

        return $this->unsupportedExceptionResponse($exception);
    }

    /**
     * Create response for critical exceptions.
     *
     * @param \Exception $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function criticalExceptionResponse(Exception $exception): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => BaseExceptionInterface::DEFAULT_ERROR_CODE_RUNTIME,
            'sub_code' => BaseExceptionInterface::DEFAULT_ERROR_SUB_CODE,
            'time' => \time(),
            'message' => $this->getExceptionMessage($exception, 'Service is currently unavailable')
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
            'time' => \time(),
            'message' => $exception->getMessage()
        ], $exception->getStatusCode()));
    }

    /**
     * Create response for entity validation exceptions.
     *
     * @param \EoneoPay\External\ORM\Exceptions\EntityValidationException $exception
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \EoneoPay\ApiFormats\Bridge\Laravel\Exceptions\InvalidPsr7FactoryException
     */
    protected function entityValidationExceptionResponse(EntityValidationException $exception): Response
    {
        return $this->createLaravelResponseFromPsr($this->encoder->encode([
            'code' => $exception->getErrorCode(),
            'sub_code' => $exception->getErrorSubCode(),
            'time' => \time(),
            'message' => $exception->getMessage(),
            'violations' => $exception->getErrors()
        ], $exception->getStatusCode()));
    }

    /**
     * Get encoder for given request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\RequestEncoderInterface
     */
    protected function getEncoder(Request $request): RequestEncoderInterface
    {
        if (null !== $this->encoder) {
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
        return \env('APP_ENV') !== 'production' ? $exception->getMessage() : $default;
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
            'time' => \time(),
            'message' => $this->getExceptionMessage($exception, 'Something went wrong')
        ], BaseExceptionInterface::DEFAULT_STATUS_CODE_RUNTIME));
    }
}
