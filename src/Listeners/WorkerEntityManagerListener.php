<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Listeners;

use Doctrine\ORM\EntityManagerInterface;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;

/**
 * This listener exists to ensure that the EntityManager is cleared
 * before every run of an async job.
 *
 * If this isnt done, the entity manager will reuse previously hydrated
 * entities that are not updated by default when being re-queried, which
 * results in stale entities being returned from the EntityManager when
 * the job is async.
 *
 * The Doctrine behaviour is documented and expected.
 *
 * @see https://stackoverflow.com/questions/39837695/force-doctrine-to-always-refresh
 */
class WorkerEntityManagerListener
{
    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $entityManager;

    /**
     * Constructor.
     *
     * @param \Doctrine\ORM\EntityManagerInterface $entityManager
     */
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Clears the EntityManager before a job is processed.
     *
     * @param \Illuminate\Queue\Events\JobProcessing $event
     *
     * @return void
     */
    public function handle(JobProcessing $event): void
    {
        if (($event->job instanceof SyncJob) === true) {
            // We will only clear the entity manager when a job is async,
            // if it is sync we dont want to clear out the entity manager
            // which might still be in use.

            return;
        }

        $this->entityManager->clear();
    }
}
