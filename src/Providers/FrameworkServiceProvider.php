<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Providers;

use EoneoPay\ApiFormats\Bridge\Laravel\Providers\ApiFormatsServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\EnvServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\OrmServiceProvider;
use EoneoPay\Externals\Bridge\Laravel\Providers\RequestServiceProvider;
use Illuminate\Support\ServiceProvider;
use LaravelDoctrine\ORM\DoctrineServiceProvider;

class FrameworkServiceProvider extends ServiceProvider
{
    /**
     * Register framework services
     *
     * @return void
     */
    public function register(): void
    {
        // Ensures formats can be read/write based on header, required by base controller
        $this->app->register(ApiFormatsServiceProvider::class);

        // Enable database interaction - required by base controller
        $this->app->register(DoctrineServiceProvider::class);

        // Add bridge to doctrine - required by base controller
        $this->app->register(OrmServiceProvider::class);

        // Add bridge for requests - required for most controller actions
        $this->app->register(RequestServiceProvider::class);

        // Add env helper - required by kernel
        $this->app->register(EnvServiceProvider::class);
    }
}
