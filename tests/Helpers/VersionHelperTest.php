<?php
declare(strict_types=1);

namespace Tests\EoneoPay\Framework\Helpers;

use EoneoPay\Externals\Bridge\Laravel\Request;
use EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException;
use EoneoPay\Framework\Helpers\VersionHelper;
use Illuminate\Http\Request as HttpRequest;
use Tests\EoneoPay\Framework\TestCases\TestCase;

class VersionHelperTest extends TestCase
{
    /**
     * Test version helper returns right controllers namespace based on request accept header.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testGetControllersControllersNamespace(): void
    {
        $tests = [
            'invalid' => 'V1',
            'vnd.eoneopay.v1+' => 'V1',
            'vnd.eoneopay.v2+' => 'V2',
            'vnd.eoneopay.v3+' => 'V3'
        ];

        foreach ($tests as $header => $version) {
            $expected = \sprintf('App\Http\Controllers\%s', $version);
            $request = new Request(new HttpRequest());
            $request->setHeader('accept', $header);

            $actual = (new VersionHelper('', $request))->getControllersNamespace();

            \var_dump($actual);

            self::assertEquals($expected, $actual);
        }
    }

    /**
     * Test version helper throw exception when no routes file found.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testGetRoutesFileBasePathException(): void
    {
        $this->expectException(UnsupportedVersionException::class);

        (new VersionHelper(__DIR__, new Request(new HttpRequest()), null, 4))->getRoutesFileBasePath();
    }

    /**
     * Test version helper returns right routes file base path.
     *
     * @return void
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function testGetRoutesFileBasePathSuccessfully(): void
    {
        $expected = \sprintf('%s/app/Http/Routes/V2.php', __DIR__);
        $request = new Request(new HttpRequest());
        $request->setHeader('accept', 'vnd.eoneopay.v2+');

        self::assertEquals($expected, (new VersionHelper(__DIR__, $request))->getRoutesFileBasePath());
    }
}
