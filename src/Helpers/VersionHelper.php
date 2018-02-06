<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Helpers;

use EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException;
use EoneoPay\Framework\Helpers\Interfaces\VersionHelperInterface;
use Illuminate\Http\Request;

class VersionHelper implements VersionHelperInterface
{
    /**
     * Application base path.
     *
     * @var string
     */
    private $basePath;

    /**
     * Requested version.
     *
     * @var string
     */
    private $version;

    /**
     * VersionHelper constructor.
     *
     * @param \Illuminate\Http\Request $request
     * @param string $basePath
     */
    public function __construct(Request $request, string $basePath)
    {
        $this->basePath = $basePath;
        $this->version = $this->guessVersionFromRequest($request);
    }

    /**
     * Returns controllers namespace based on version from request.
     *
     * @return string
     */
    public function getControllersNamespace(): string
    {
        return \sprintf('App\Http\Controllers\%s', $this->version);
    }

    /**
     * Returns routes file base path based on version from request.
     *
     * @return string
     *
     * @throws \EoneoPay\Framework\Helpers\Exceptions\UnsupportedVersionException
     */
    public function getRoutesFileBasePath(): string
    {
        $path = \sprintf('%s/app/Http/Routes/%s.php', $this->basePath, $this->version);

        if (!\file_exists($path)) {
            $path = \sprintf('%s/app/Http/Routes/%s.php', $this->basePath, $this->getLatestVersion());
        }

        if (!\file_exists($path)) {
            throw new UnsupportedVersionException(\sprintf(
                'Version %s requested, fallback to %s but files does not exist',
                $this->version,
                $this->getLatestVersion()
            ));
        }

        return $path;
    }

    /**
     * Get latest version.
     *
     * @return string
     */
    private function getLatestVersion(): string
    {
        return \env('APP_LATEST_VERSION', 'V1');
    }

    /**
     * Guess version from request.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    private function guessVersionFromRequest(Request $request): string
    {
        \preg_match('#vnd.eoneopay.(v\d+)\+#i', $request->headers->get('accept', ''), $matches);

        $version = $matches[1] ?? $this->getLatestVersion();

        return \mb_strtoupper($version);
    }
}
