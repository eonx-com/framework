<?php
declare(strict_types=1);

namespace EoneoPay\Framework\Providers;

use EoneoPay\ApiFormats\Bridge\Laravel\Providers\ApiFormatsServiceProvider;
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
    }
}
