<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Http\Controllers;

use EoneoPay\ApiFormats\Bridge\Laravel\Responses\FormattedApiResponse;
use EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface;
use EoneoPay\Externals\ORM\Interfaces\EntityInterface;
use EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface;
use EoneoPay\Externals\Request\Interfaces\RequestInterface;
use EoneoPay\Framework\Exceptions\EntityNotFoundException;
use EoneoPay\Framework\Interfaces\ControllerInterface;
use Laravel\Lumen\Routing\Controller as BaseController;

abstract class Controller extends BaseController implements ControllerInterface
{
    /**
     * @var \EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface
     */
    private $entityManager;

    /**
     * Controller constructor.
     *
     * @param \EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Create entity and return formatted api response.
     *
     * @param string $entityClass
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \EoneoPay\Externals\ORM\Exceptions\EntityValidationFailedException
     * @throws \InvalidArgumentException
     * @throws \EoneoPay\Externals\ORM\Exceptions\ORMException
     */
    public function createEntityAndRespond(
        string $entityClass,
        RequestInterface $request
    ): FormattedApiResponseInterface {
        /** @var \EoneoPay\Externals\ORM\Interfaces\EntityInterface $entity */
        $entity = new $entityClass($request->toArray());

        $this->saveEntity($entity);

        return $this->formattedApiResponse($entity, 201);
    }

    /**
     * Delete entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param null|string $notFoundException
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \EoneoPay\Externals\ORM\Exceptions\ORMException
     * @throws \EoneoPay\Externals\ORM\Exceptions\EntityValidationFailedException
     * @throws \InvalidArgumentException
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function deleteEntityAndRespond(
        string $entityClass,
        string $entityId,
        ?string $notFoundException = null
    ): FormattedApiResponseInterface {
        $entity = $this->retrieveEntity($entityClass, $entityId, $notFoundException);

        $this->removeEntity($entity);

        return $this->formattedApiResponse([], 203);
    }

    /**
     * Create formatted api response for given content.
     *
     * @param \EoneoPay\Utils\Interfaces\SerializableInterface|mixed[] $content
     * @param int|null $statusCode
     * @param string[]|null $headers
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \InvalidArgumentException
     */
    public function formattedApiResponse(
        $content,
        ?int $statusCode = null,
        ?array $headers = null
    ): FormattedApiResponseInterface {
        return new FormattedApiResponse($content, $statusCode ?? 200, $headers ?? []);
    }

    /**
     * Remove entity from database.
     *
     * @param \EoneoPay\Externals\ORM\Interfaces\EntityInterface $entity
     *
     * @return void
     *
     * @throws \EoneoPay\Externals\ORM\Exceptions\EntityValidationFailedException
     * @throws \EoneoPay\Externals\ORM\Exceptions\ORMException
     */
    public function removeEntity(EntityInterface $entity): void
    {
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }

    /** @noinspection PhpDocRedundantThrowsInspection Thrown dynamically */
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
    ): EntityInterface {
        $entity = $this->getEntityManager()->getRepository($entityClass)->find($entityId);

        if ($entity === null) {
            $exceptionClass = $notFoundException ?? EntityNotFoundException::class;

            throw new $exceptionClass(\sprintf('%s %s not found', $entityClass, $entityId));
        }

        return $entity;
    }

    /** @noinspection PhpDocRedundantThrowsInspection Exception thrown dynamically */

    /**
     * Save entity into database.
     *
     * @param \EoneoPay\Externals\ORM\Interfaces\EntityInterface $entity
     *
     * @return void
     *
     * @throws \EoneoPay\Externals\ORM\Exceptions\ORMException
     * @throws \EoneoPay\Externals\ORM\Exceptions\EntityValidationFailedException
     */
    public function saveEntity(EntityInterface $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    /**
     * Update entity and return formatted api response.
     *
     * @param string $entityClass
     * @param string $entityId
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     * @param null|string $notFoundException
     *
     * @return \EoneoPay\ApiFormats\Interfaces\FormattedApiResponseInterface
     *
     * @throws \EoneoPay\Externals\ORM\Exceptions\ORMException
     * @throws \EoneoPay\Externals\ORM\Exceptions\EntityValidationFailedException
     * @throws \InvalidArgumentException
     * @throws \EoneoPay\Utils\Exceptions\NotFoundException
     */
    public function updateEntityAndRespond(
        string $entityClass,
        string $entityId,
        RequestInterface $request,
        ?string $notFoundException = null
    ): FormattedApiResponseInterface {
        /** @var \EoneoPay\Externals\ORM\Interfaces\EntityInterface $entity */
        $entity = $this->retrieveEntity($entityClass, $entityId, $notFoundException);
        $entity->fill($request->toArray());

        $this->saveEntity($entity);

        return $this->formattedApiResponse($entity);
    }

    /**
     * Get entity manager instance
     *
     * @return \EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }
}
