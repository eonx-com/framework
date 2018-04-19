<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Providers;

use EoneoPay\ApiFormats\Interfaces\EncoderGuesserInterface;
use EoneoPay\Externals\ORM\Interfaces\EntityManagerInterface;
use EoneoPay\Framework\Providers\FrameworkServiceProvider;
use Laravel\Lumen\Application;
use Tests\EoneoPay\Framework\TestCases\TestCase;

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
            EntityManagerInterface::class
        ];

        $application = new Application();
        /** @noinspection PhpParamsInspection Due to Laravel/Lumen working way */
        (new FrameworkServiceProvider($application))->register();

        foreach ($tests as $serviceId) {
            self::assertTrue($application->has($serviceId));
        }
    }
}
