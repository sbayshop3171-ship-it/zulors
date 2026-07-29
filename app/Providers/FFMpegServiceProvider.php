<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Filesystem\FFMpeg\FFMpegService;

class FFMpegServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FFMpegService::class, function () {
            return new FFMpegService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
