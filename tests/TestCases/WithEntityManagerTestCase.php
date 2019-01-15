<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\TestCases;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\AnnotationRegistry;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager as DoctrineEntityManager;
use Doctrine\ORM\EntityManagerInterface as DoctrineEntityManagerInterface;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doctrine\ORM\Tools\SchemaTool;
use EoneoPay\Externals\ORM\EntityManager;
use EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) Due to doctrine configuration
 */
abstract class WithEntityManagerTestCase extends TestCase
{
    /**
     * @var string[]
     */
    public static $connection = [
        'driver' => 'pdo_sqlite',
        'path' => ':memory:'
    ];

    /**
     * @var string[]
     */
    public static $paths = [
        __DIR__ . '/../Database/Stubs'
    ];

    /**
     * @var \Doctrine\ORM\EntityManagerInterface
     */
    private $doctrine;

    /**
     * @var \EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface
     */
    private $entityManager;

    /**
     * Get doctrine entity manager.
     *
     * @return \Doctrine\ORM\EntityManagerInterface
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \Doctrine\ORM\ORMException
     *
     * @SuppressWarnings(PHPMD.StaticAccess) Inherited from Doctrine
     */
    protected function getDoctrineEntityManager(): DoctrineEntityManagerInterface
    {
        if ($this->doctrine !== null) {
            return $this->doctrine;
        }

        /** @noinspection PhpDeprecationInspection Only used for test case */
        AnnotationRegistry::registerFile(\sprintf(
            '%s/../../vendor/doctrine/orm/lib/Doctrine/ORM/Mapping/Driver/DoctrineAnnotations.php',
            __DIR__
        ));

        $cache = new ArrayCache();
        // Standard annotation reader
        $annotationReader = new AnnotationReader();

        // Create a driver chain for metadata reading
        $driverChain = new MappingDriverChain();

        // Now we want to register our application entities,
        // for that we need another metadata driver used for Entity namespace
        $annotationDriver = new AnnotationDriver(
            $annotationReader, // our cached annotation reader
            self::$paths // paths to look in
        );
        // NOTE: driver for application Entity can be different, Yaml, Xml or whatever
        // register annotation driver for our application Entity fully qualified namespace
        $driverChain->addDriver($annotationDriver, 'Tests\\EoneoPay\\Framework\\Database\\Stubs');

        // General ORM configuration
        $config = new Configuration();
        $config->setProxyDir(\sys_get_temp_dir());
        $config->setProxyNamespace('Proxy');
        $config->setAutoGenerateProxyClasses(true); // this can be based on production config.
        // Register metadata driver
        $config->setMetadataDriverImpl($driverChain);
        // Use our already initialized cache driver
        $config->setMetadataCacheImpl($cache);
        $config->setQueryCacheImpl($cache);

        // Finally, create entity manager
        $this->doctrine = DoctrineEntityManager::create(self::$connection, $config);

        return $this->doctrine;
    }

    /**
     * Get entity manager.
     *
     * @return \EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    protected function getEntityManager(): EntityManagerInterface
    {
        if ($this->entityManager !== null) {
            return $this->entityManager;
        }

        $this->entityManager = new EntityManager($this->getDoctrineEntityManager());

        return $this->entityManager;
    }

    /**
     * Create database.
     *
     * @return void
     *
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\Tools\ToolsException
     * @throws \Doctrine\Common\Annotations\AnnotationException
     */
    protected function setUp(): void
    {
        parent::setUp();

        (new SchemaTool($this->getDoctrineEntityManager()))
            ->createSchema($this->getDoctrineEntityManager()->getMetadataFactory()->getAllMetadata());
    }

    /**
     * Drop database.
     *
     * @return void
     *
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function tearDown(): void
    {
        (new SchemaTool($this->getDoctrineEntityManager()))->dropDatabase();

        parent::tearDown();
    }
}
