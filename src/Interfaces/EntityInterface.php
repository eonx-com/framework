<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Interfaces;


interface EntityInterface
{
    /**
     * Get entity not found exception for entity.
     *
     * @return string
     */
    public function getEntityNotFoundException(): string;

    /**
     * Get validation failed exception for entity.
     *
     * @return string
     */
    public function getValidationFailedException(): string;
}
