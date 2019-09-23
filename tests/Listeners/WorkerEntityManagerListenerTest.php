<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Listeners;

use EoneoPay\Framework\Listeners\WorkerEntityManagerListener;
use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\RedisJob;
use Illuminate\Queue\Jobs\SyncJob;
use Tests\EoneoPay\Framework\Stubs\Vendor\Doctrine\EntityManagerStub;
use Tests\EoneoPay\Framework\Stubs\Vendor\Laravel\RedisQueueStub;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @covers \EoneoPay\Framework\Listeners\WorkerEntityManagerListener
 */
class WorkerEntityManagerListenerTest extends TestCase
{
    /**
     * Tests handle will not clear the entity manager when the job does
     * not implement ShouldQueue.
     *
     * @return void
     */
    public function testHandle(): void
    {
        $entityManager = new EntityManagerStub();
        $listener = new WorkerEntityManagerListener($entityManager);

        $job = new RedisJob(
            new Container(),
            new RedisQueueStub(),
            'job',
            'reserved',
            'connection',
            'queue'
        );

        $listener->handle(new JobProcessing('connection', $job));

        self::assertTrue($entityManager->isCleared());
    }

    /**
     * Tests handle will not clear the entity manager when the job does
     * not implement ShouldQueue.
     *
     * @return void
     */
    public function testHandleSyncJob(): void
    {
        $entityManager = new EntityManagerStub();
        $listener = new WorkerEntityManagerListener($entityManager);

        $job = new SyncJob(
            new Container(),
            'payload',
            'connection',
            'queue'
        );

        $listener->handle(new JobProcessing('connection', $job));

        self::assertFalse($entityManager->isCleared());
    }
}
