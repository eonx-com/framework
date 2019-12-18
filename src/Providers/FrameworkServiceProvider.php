<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Providers;

use EoneoPay\ApiFormats\Bridge\Laravel\Providers\ApiFormatsServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\EnvServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\FilesystemServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\OrmServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\RequestServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\TranslatorServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\ValidationServiceProvider;
use EoneoPay\Externals\Logger\Interfaces\LoggerInterface;
use EoneoPay\Externals\Logger\Logger;
use EoneoPay\Utils\Bridge\Lumen\Providers\ConfigurationServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\PresenceVerifierInterface;
use LaravelDoctrine\ORM\DoctrineServiceProvider;
use LaravelDoctrine\ORM\Validation\DoctrinePresenceVerifier;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects) High coupling required to bind all framework services
 */
class FrameworkServiceProvider extends ServiceProvider
{
    /**
     * @noinspection PhpMissingParentCallCommonInspection Parent implementation empty
     *
     * @inheritdoc
     */
    public function register(): void
    {
        // Ensures formats can be read/write based on header - required by base controller
        $this->app->register(ApiFormatsServiceProvider::class);

        // Add configurations automatically - required by bootstrap
        $this->app->register(ConfigurationServiceProvider::class);

        // Enable database interaction - required by base controller
        $this->app->register(DoctrineServiceProvider::class);

        // Enable filesystem - required by health check, must be loaded after configuration
        $this->app->register(FilesystemServiceProvider::class);

        // Logger interface - required by exception handler
        $this->app->singleton(LoggerInterface::class, Logger::class);

        // Add bridge to doctrine - required by base controller
        $this->app->register(OrmServiceProvider::class);

        // Add bridge for requests - required for most controller actions
        $this->app->register(RequestServiceProvider::class);

        // Add env helper - required by kernel
        $this->app->register(EnvServiceProvider::class);

        // Validator - required by command
        $this->app->register(TranslatorServiceProvider::class);
        $this->app->bind(PresenceVerifierInterface::class, DoctrinePresenceVerifier::class);
        $this->app->register(ValidationServiceProvider::class);
    }
}
