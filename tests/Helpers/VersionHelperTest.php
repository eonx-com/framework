<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Helpers;

use EoneoPay\Externals\Bridge\Laravel\Request;
use EoneoPay\Externals\Request\Interfaces\RequestInterface;
use EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException;
use EoneoPay\Framework\Helpers\VersionHelper;
use Illuminate\Http\Request as HttpRequest;
use Tests\EoneoPay\Framework\TestCases\TestCase;

/**
 * @covers \EoneoPay\Framework\Helpers\VersionHelper
 */
class VersionHelperTest extends TestCase
{
    /**
     * Test version helper returns right controllers namespace based on request accept header.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testMonoAppGetControllersControllersNamespace(): void
    {
        $tests = [
            'invalid' => 'V1',
            'vnd.eoneopay.v1+' => 'V1',
            'vnd.eoneopay.v2+' => 'V2',
            'vnd.eoneopay.v3+' => 'V3',
        ];

        foreach ($tests as $header => $version) {
            $expected = \sprintf('App\Http\Controllers\%s', $version);
            $request = new Request(new HttpRequest());
            $request->setHeader('accept', $header);

            $versionHelper = new VersionHelper(__DIR__, $request);

            self::assertSame($expected, $versionHelper->getControllersNamespace());
            self::assertSame($expected, $versionHelper->getControllersNamespace()); // For caching coverage
        }
    }

    /**
     * Test version helper throw exception when no routes file found.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testMonoAppGetRoutesFileBasePathException(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        (new VersionHelper(__DIR__, new Request(new HttpRequest()), 4))->getRoutesFileBasePath();
    }

    /**
     * Test version helper returns right routes file base path.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testMonoAppGetRoutesFileBasePathSuccessfully(): void
    {
        $expected = \sprintf('%s/app/Http/Routes/V2.php', __DIR__);
        $request = new Request(new HttpRequest());
        $request->setHeader('accept', 'vnd.eoneopay.v2+');

        $versionHelper = new VersionHelper(__DIR__, $request);

        self::assertSame($expected, $versionHelper->getRoutesFileBasePath());
        self::assertSame($expected, $versionHelper->getRoutesFileBasePath()); // For caching coverage
    }

    /**
     * Test version helper returns right routes file base path and controller namespace for multi-applications.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testMultiAppsGetRoutesFileBasePathSuccessfully(): void
    {
        $apps = [
            ['EoneoPay', 'eoneopay.local', '1'],
            ['eoneo_pay', 'eoneopay.box', null],
            ['ewallet', 'ewallet.box', null],
            ['Nate_App', 'nate-app.nate', 6],
            ['nate-da-bomb', 'nate_da_bomb.com', 1],
        ];
        $tests = [
            ['eoneopay.local', 'vnd.eoneopay.v1+', 1, 'EoneoPay'],
            ['eoneopay.box', 'vnd.eoneopay.v2+', 1, 'EoneoPay'],
            ['ewallet.box', 'vnd.ewallet.v4+', 1, 'Ewallet'],
            ['nate-app.nate', 'vnd.eoneopay.v1+', 6, 'NateApp'],
            ['nate_da_bomb.com', 'vnd.eoneopay.v3+', 3, 'NateDaBomb'],
        ];

        $patternControllers = 'App\Http\Controllers\V%s\%s';
        $patternRoutes = '%s/app/Http/Routes/V%s/%s.php';

        foreach ($tests as $test) {
            $request = new Request(new HttpRequest([], [], [], [], [], [
                'HTTP_ACCEPT' => $test[1],
                'HTTP_HOST' => $test[0],
            ]));

            self::assertSame(
                \sprintf($patternControllers, $test[2], $test[3]),
                $this->getVersionHelper(__DIR__, $request, $apps)->getControllersNamespace()
            );
            self::assertSame(
                \sprintf($patternRoutes, __DIR__, $test[2], $test[3]),
                $this->getVersionHelper(__DIR__, $request, $apps)->getRoutesFileBasePath()
            );
        }
    }

    /**
     * Get configured version helper for multi-applications.
     *
     * @param string $basePath
     * @param \EoneoPay\Externals\Request\Interfaces\RequestInterface $request
     * @param mixed[] $apps
     *
     * @return \EoneoPay\Framework\Helpers\VersionHelper
     */
    private function getVersionHelper(string $basePath, RequestInterface $request, array $apps): VersionHelper
    {
        $versionHelper = new VersionHelper($basePath, $request);

        foreach ($apps as $app) {
            $versionHelper->addApplication(...$app);
        }

        return $versionHelper;
    }
}
