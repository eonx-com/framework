<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Http\Controllers;

use EoneoPay\ApiFormats\Bridge\Laravel\Responses\FormattedApiResponse;
use EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface;
use EoneoPay\External\ORM\Interfaces\EntityInterface;
use EoneoPay\External\ORM\Interfaces\EntityManagerInterface;
use EoneoPay\Framework\Interfaces\ControllerInterface;
use Illuminate\Http\Request;
use Laravel\Lumen\Routing\Controller as BaseController;

abstract class Controller extends BaseController implements ControllerInterface
{
    /**
     * @var \EoneoPay\External\ORM\Interfaces\EntityManagerInterface
     */
    protected $entityManager;

    /**
     * Controller constructor.
     *
     * @param \EoneoPay\External\ORM\Interfaces\EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create entity and return formatted api response.
     *
     * @param string $entityClass
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \InvalidArgumentException
     */
    public function createEntityAndRespond(string $entityClass, Request $request): FormattedApiResponseInterface
    {
        /** @var EntityInterface $entity */
        $entity = new $entityClass($request->all());

        $this->saveEntity($entity);

        return $this->formattedApiResponse($entity, 201);
    }

    /**
     * Delete entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \InvalidArgumentException
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function deleteEntityAndRespond(string $entityClass, string $entityId): FormattedApiResponseInterface
    {
        $entity = $this->retrieveEntity($entityClass, $entityId);

        $this->removeEntity($entity);

        return $this->formattedApiResponse([], 203);
    }

    /**
     * Create formatted api response for given entity.
     *
     * @param \EoneoPay\External\ORM\Interfaces\EntityInterface|array $entity
     * @param int|null $statusCode
     * @param array|null $headers
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \InvalidArgumentException
     */
    public function formattedApiResponse(
        $entity,
        ?int $statusCode = null,
        ?array $headers = null
    ): FormattedApiResponseInterface {
        return new FormattedApiResponse($entity, $statusCode ?? 200, $headers ?? []);
    }

    /**
     * Remove entity from database.
     *
     * @param \EoneoPay\External\ORM\Interfaces\EntityInterface $entity
     */
    public function removeEntity(EntityInterface $entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /** @noinspection PhpDocRedundantThrowsInspection Exception thrown dynamically */
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
    public function retrieveEntity(string $entityClass, string $entityId): EntityInterface
    {
        $entity = $this->entityManager->getRepository($entityClass)->find($entityId);

        if (null === $entity) {
            /** @var \EoneoPay\Framework\Database\Entities\Entity $entity */
            $entity = new $entityClass();
            $exceptionClass = $entity->getEntityNotFoundException();

            throw new $exceptionClass(\sprintf('%s %s not found', $entityClass, $entityId));
        }

        return $entity;
    }

    /**
     * Save entity into database.
     *
     * @param \EoneoPay\External\ORM\Interfaces\EntityInterface $entity
     */
    public function saveEntity(EntityInterface $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Update entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param \Illuminate\Http\Request $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \InvalidArgumentException
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function updateEntityAndRespond(
        string $entityClass,
        string $entityId,
        Request $request
    ): FormattedApiResponseInterface {
        /** @var EntityInterface $entity */
        $entity = $this->retrieveEntity($entityClass, $entityId);
        $entity->fill($request->all());

        $this->saveEntity($entity);

        return $this->formattedApiResponse($entity);
    }
}
