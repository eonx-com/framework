<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Stubs\Vendor\Laravel;

use Illuminate\Queue\RedisQueue as BaseRedisQueue;

class RedisQueueStub extends BaseRedisQueue
{
    /**
     * Constructor override.
     *
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct()
    {
    }
}
