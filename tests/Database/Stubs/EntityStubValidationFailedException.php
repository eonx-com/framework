<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Database\Stubs;

use EoneoPay\Externals\ORM\Exceptions\EntityValidationFailedException;

class EntityStubValidationFailedException extends EntityValidationFailedException
{
    /**
     * Get Error code.
     *
     * @return int
     */
    public function getErrorCode(): int
    {
        return 0;
    }

    /**
     * Get Error sub-code.
     *
     * @return int
     */
    public function getErrorSubCode(): int
    {
        return 0;
    }
}
