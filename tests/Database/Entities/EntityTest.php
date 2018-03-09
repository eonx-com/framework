<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Database\Entities;

use Tests\EoneoPay\Framework\Database\Stubs\EntityStub;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubNotFoundException;
use Tests\EoneoPay\Framework\Database\Stubs\EntityStubValidationFailedException;
use Tests\EoneoPay\Framework\TestCases\TestCase;

class EntityTest extends TestCase
{
    /**
     * Test entity abstract methods return right values.
     *
     * @return void
     */
    public function testEntityAbstractFunctions(): void
    {
        $entity = new EntityStub();

        self::assertEquals(EntityStubValidationFailedException::class, $entity->getValidationFailedException());
        self::assertEquals(EntityStubNotFoundException::class, $entity->getEntityNotFoundException());
    }
}
