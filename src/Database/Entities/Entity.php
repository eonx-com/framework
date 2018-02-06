<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Database\Entities;

use EoneoPay\External\ORM\Entity as ORMEntity;
use EoneoPay\Framework\Interfaces\EntityInterface;

abstract class Entity extends ORMEntity implements EntityInterface
{
    /**
     * {@inheritdoc}
     */
    abstract public function getEntityNotFoundException(): string;

    /**
     * {@inheritdoc}
     */
    abstract public function getValidationFailedException(): string;
}
