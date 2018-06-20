<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Interfaces;

use EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface;
use EoneoPay\Externals\ORM\Interfaces\EntityInterface;
use EoneoPay\Externals\Request\Interfaces\RequestInterface;

interface ControllerInterface
{
    /**
     * Create entity and return formatted api response.
     *
     * @param string $entityClass
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     */
    public function createEntityAndRespond(
        string $entityClass,
        RequestInterface $request
    ): FormattedApiResponseInterface;

    /**
     * Delete entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function deleteEntityAndRespond(string $entityClass, string $entityId): FormattedApiResponseInterface;

    /**
     * Create formatted api response for given entity.
     *
     * @param \EoneoPay\Externals\ORM\Interfaces\EntityInterface|string[]|array[] $entity
     * @param int|null $statusCode
     * @param string[]|null $headers
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     */
    public function formattedApiResponse(
        $entity,
        ?int $statusCode = null,
        ?array $headers = null
    ): FormattedApiResponseInterface;

    /**
     * Remove entity from database.
     *
     * @param \EoneoPay\Externals\ORM\Interfaces\EntityInterface $entity
     */
    public function removeEntity(EntityInterface $entity): void;

    /**
     * Retrieve entity by id.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param null|string $notFoundException
     *
     * @return \EoneoPay\Externals\ORM\Interfaces\EntityInterface
     *
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function retrieveEntity(
        string $entityClass,
        string $entityId,
        ?string $notFoundException = null
    ): EntityInterface;

    /**
     * Save entity into database.
     *
     * @param \EoneoPay\Externals\ORM\Interfaces\EntityInterface $entity
     */
    public function saveEntity(EntityInterface $entity): void;

    /**
     * Update entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function updateEntityAndRespond(
        string $entityClass,
        string $entityId,
        RequestInterface $request
    ): FormattedApiResponseInterface;
}
