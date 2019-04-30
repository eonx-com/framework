<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Exceptions\Stubs;

use EoneoPay\Utils\Interfaces\Exceptions\ClientExceptionInterface;
use Exception;

class ClientExceptionStub extends Exception implements ClientExceptionInterface
{
    /**
     * Status code
     *
     * @var int
     */
    private $statusCode = 200;

    /**
     * {@inheritdoc}
     */
    public function getErrorCode(): int
    {
        return 1000;
    }

    /**
     * {@inheritdoc}
     */
    public function getErrorSubCode(): int
    {
        return 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getMessageParameters(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set status code
     *
     * @param int $statusCode
     *
     * @return void
     */
    public function setStatusCode(int $statusCode): void
    {
        $this->statusCode = $statusCode;
    }
}
