<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Providers;

use EoneoPay\ApiFormats\External\Interfaces\Psr7\Psr7FactoryInterface;
use EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface;
use EoneoPay\Externals\Environment\Interfaces\EnvInterface;
use EoneoPay\Externals\Logger\Interfaces\LoggerInterface;
use EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface;
use EoneoPay\Externals\Request\Interfaces\RequestInterface;
use EoneoPay\Externals\Translator\Interfaces\TranslatorInterface;
use EoneoPay\Externals\Validator\Interfaces\ValidatorInterface;
use EoneoPay\Framework\Providers\FrameworkServiceProvider;
use Laravel\Lumen\Application;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @covers \EoneoPay\Framework\Providers\FrameworkServiceProvider
 */
class FrameworkServiceProviderTest extends TestCase
{
    /**
     * Test provider register other packages service providers correctly.
     *
     * @return void
     */
    public function testRegister(): void
    {
        $tests = [
            EncoderGuesserInterface::class,
            EntityManagerInterface::class,
            EnvInterface::class,
            LoggerInterface::class,
            Psr7FactoryInterface::class,
            RequestInterface::class,
            TranslatorInterface::class,
            ValidatorInterface::class,
        ];

        $application = new Application();
        /** @noinspection PhpParamsInspection Due to Laravel/Lumen working way */
        (new FrameworkServiceProvider($application))->register();

        foreach ($tests as $serviceId) {
            self::assertTrue($application->has($serviceId));
        }
    }
}
