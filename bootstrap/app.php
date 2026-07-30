<?php
/*
|--------------------------------------------------------------------------
| Zulors - The Ultimate Zulors Web Application.
|--------------------------------------------------------------------------
| Author: Mansur Terla. Full-Stack Web Developer, UI/UX Designer.
| Website: www.terla.me
| E-mail: mansurtl.contact@gmail.com
| Instagram: @mansur_terla
| Telegram: @mansurtl_contact
|--------------------------------------------------------------------------
| Copyright (c)  Zulors. All rights reserved.
|--------------------------------------------------------------------------
*/

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        using: function() {
            Route::prefix(config('app.admin_prefix'))->middleware('admin-area')->group(base_path('routes/admin/web.php'));
            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/downloads.php'));
            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/social.php'));
            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/document.php'));
            Route::middleware(['web', 'restrict.ip', 'auth', 'user.status', 'device.identifier', 'terminator'])->prefix('business')->group(base_path('routes/business.php'));
            Route::middleware(['api', 'log.request', 'restrict.ip', 'device.identifier', 'terminator'])->prefix('api')->group(base_path('routes/api.php'));
            Route::withoutMiddleware()->group(base_path('routes/webhooks/payment_webhooks.php'));
            Route::withoutMiddleware()->group(base_path('routes/callbacks.php'));

            Route::middleware(['web', 'restrict.ip', 'device.identifier', 'terminator'])->group(base_path('routes/web.php'));

        })->withMiddleware(function (Middleware $middleware) {

            $middleware->redirectGuestsTo('auth/login');


            $middleware->alias([
                'user.status' => App\Http\Middleware\UserStatusMiddleware::class,
                'device.identifier' => App\Http\Middleware\DeviceIdentifierMiddleware::class,
                'terminator' => App\Http\Middleware\TerminatingMiddleware::class,
                'restrict.ip' => App\Http\Middleware\RestrictIPAddressMiddleware::class,
                'features.status' => App\Http\Middleware\FeatureStatusMiddleware::class,
                'sided.layout' => App\Http\Middleware\SidedLayoutMiddleware::class,
                'api.key' => App\Http\Middleware\VerifyApiKey::class,
                'admin' => App\Http\Middleware\AdminRoleMiddleware::class,
                'log.request' => App\Http\Middleware\LogRequestMiddleware::class,
            ]);

            $middleware->web(append: [
                App\Http\Middleware\UserLanguageMiddleware::class,
                App\Http\Middleware\UserOnlineMiddleware::class
            ]);

            $middleware->api(append: [
                App\Http\Middleware\UserLanguageMiddleware::class,
                App\Http\Middleware\UserOnlineMiddleware::class
            ]);

            $middleware->statefulApi();

            $middleware->encryptCookies(except: [
                'theme',
                'theme_runtime',
            ]);

            $middleware->trustProxies('*');

            $middleware->group('admin-area', ['web', 'admin']);

        })->withExceptions(function (Exceptions $exceptions) {

        })->create();
