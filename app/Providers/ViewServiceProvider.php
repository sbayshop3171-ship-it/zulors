<?php

namespace App\Providers;

use App\Info\Zulors;
use App\Support\Languages;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(['layouts.mpa.parts.header'], function ($view) {
            $view->with('appLanguages', new Languages());
        });

        View::composer("*", function ($view) {
            $logoUrl = (theme_name() === 'dark') ? 'dark.png' : 'light.png';

            $view->with('logotypeUrl', asset("assets/logos/{$logoUrl}"));
            $view->with('appVersion', Zulors::VERSION);
        });

        // MPA views
        View::addNamespace('admin', resource_path('views/apps/mpa/admin'));
        View::addNamespace('business', resource_path('views/apps/mpa/business'));
        View::addNamespace('document', resource_path('views/apps/mpa/document'));
        View::addNamespace('auth', resource_path('views/apps/mpa/auth'));
        View::addNamespace('onboarding', resource_path('views/apps/mpa/onboarding'));

        // SPA views
        View::addNamespace('desktop', resource_path('views/apps/spa/desktop'));
        View::addNamespace('mobile', resource_path('views/apps/spa/mobile'));

        // MPA layouts
        View::addNamespace('adminLayout', resource_path('views/layouts/mpa/apps/admin'));
        View::addNamespace('businessLayout', resource_path('views/layouts/mpa/apps/business'));
        View::addNamespace('authLayout', resource_path('views/layouts/mpa/apps/auth'));
        View::addNamespace('documentLayout', resource_path('views/layouts/mpa/apps/document'));
    }
}
