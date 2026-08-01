<?php

namespace App\Providers;

use App\Data\DataCapsule;
use App\Mail\Transport\BrevoApiTransport;
use App\Services\Filesystem\RoundRobin\RoundRobinService;
use App\Support\Languages;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Opcodes\LogViewer\Facades\LogViewer;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(RoundRobinService::class, function () {
            return new RoundRobinService();
        });

        $this->app->singleton(DataCapsule::class, function () {
            return new DataCapsule();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        bcscale(2);

        Mail::extend('brevo_api', function (array $config) {
            return new BrevoApiTransport(
                (string) ($config['key'] ?? ''),
                (int) ($config['timeout'] ?? 60)
            );
        });

        View::composer('*', function($view) {
            $view->with('localeName', (new Languages())->getLocaleName());
            $view->with('buildNumber', cache()->rememberForever('frontend_build_number', function() {
                $buildNumberPath = storage_path('frontend/build.num');

                if(is_file($buildNumberPath)) {
                    $buildNumber = trim((string) file_get_contents($buildNumberPath));

                    if($buildNumber !== '') {
                        return $buildNumber;
                    }
                }

                return (string) random_int(1, 1000000);
            }));
        });

        LogViewer::auth(function() {
            return auth_check() && me()->isRoot();
        });

        $livewireComponents = [
            'admin.config.pwa' => \App\Livewire\Admin\Config\PWA::class,
            'admin.config.ffmpeg' => \App\Livewire\Admin\Config\FFMPeg::class,
            'admin.config.ffmpeg-test' => \App\Livewire\Admin\Config\FFMPegTest::class,
        ];

        foreach ($livewireComponents as $alias => $class) {
            Livewire::component($alias, $class);
        }
    }
}
