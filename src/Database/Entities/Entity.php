<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Database\Entities;

use EoneoPay\Externals\ORM\Entity as ORMEntity;
use EoneoPay\Framework\Interfaces\Database\EntityInterface;

abstract class Entity extends ORMEntity implements EntityInterface
{

}
