<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Interfaces;

use EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface;
use EoneoPay\External\ORM\Interfaces\EntityInterface;
use Illuminate\Http\Request;

interface ControllerInterface
{
    /**
     * Create entity and return formatted api response.
     *
     * @param string $entityClass
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     */
    public function createEntityAndRespond(string $entityClass, Request $request): FormattedApiResponseInterface;

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
     * @param \EoneoPay\External\ORM\Interfaces\EntityInterface|array $entity
     * @param int|null $statusCode
     * @param array|null $headers
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
     * @param \EoneoPay\External\ORM\Interfaces\EntityInterface $entity
     */
    public function removeEntity(EntityInterface $entity): void;

    /**
     * Retrieve entity by id.
     *
     * @param string $entityClass
     * @param string $entityId
     *
     * @return \EoneoPay\External\ORM\Interfaces\EntityInterface
     *
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function retrieveEntity(string $entityClass, string $entityId): EntityInterface;

    /**
     * Save entity into database.
     *
     * @param \EoneoPay\External\ORM\Interfaces\EntityInterface $entity
     */
    public function saveEntity(EntityInterface $entity): void;

    /**
     * Update entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function updateEntityAndRespond(
        string $entityClass,
        string $entityId,
        Request $request
    ): FormattedApiResponseInterface;
}
