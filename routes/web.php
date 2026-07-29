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

use Illuminate\Http\Request;
use Jenssegers\Agent\Agent;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cookie;

Route::name('user.')->group(function() {
    Route::get('/switch-language/{lang}', [App\Http\Controllers\User\Language\LanguageController::class, 'switchLanguage'])->name('language.switch');
    Route::middleware(['features.status:dark_theme'])->get('/switch-theme/{theme}', [App\Http\Controllers\User\Theme\ThemeController::class, 'switchTheme'])->name('theme.switch');
});

Route::name('user.')->prefix('auth')->middleware(['guest'])->group(function() {
    Route::get('/login', [App\Http\Controllers\User\Auth\AuthController::class, 'index'])->name('auth.index');
    Route::get('/signup', [App\Http\Controllers\User\Auth\AuthController::class, 'signup'])->name('auth.signup');
    Route::get('/forgot-password', [App\Http\Controllers\User\Auth\AuthController::class, 'forgotPassword'])->name('auth.forgot');
    Route::get('/reset-password/{token}', [App\Http\Controllers\User\Auth\AuthController::class, 'resetPassword'])->name('auth.reset');
    Route::get('/confirm-signup/{token}', [App\Http\Controllers\User\Auth\AuthController::class, 'confirmSignup'])->name('auth.confirm-signup');
    Route::get('/forgot-success/{hashId}', [App\Http\Controllers\User\Auth\AuthController::class, 'forgotSuccess'])->name('auth.forgot-success');
    Route::get('/signup-success/{hashId}', [App\Http\Controllers\User\Auth\AuthController::class, 'signupSuccess'])->name('auth.signup-success');
});

Route::name('admin.')->prefix(config('app.admin_prefix'))->middleware(['guest'])->group(function() {
    Route::get('/login', [App\Http\Controllers\Admin\Auth\AuthController::class, 'login'])->name('auth.login');
});

Route::name('user.')->prefix('auth')->middleware(['auth'])->group(function() {
    Route::middleware(['features.status:link_accounts'])
        ->get('/link-account', [App\Http\Controllers\User\Auth\LinkerController::class, 'index'])
        ->name('linker.index');

    Route::get('/logout', [App\Http\Controllers\User\Auth\AuthController::class, 'logout'])->name('auth.logout');
});

Route::name('user.')->prefix('onboarding')->middleware(['auth'])->group(function() {
    Route::get('/step/{step}', [App\Http\Controllers\User\Onboarding\OnboardingController::class, 'index'])->name('onboarding.index');
});

Route::prefix('switcher')->get('/device/{type}', function (string $type) {

    // 1 year
    Cookie::queue('device_type', $type, (60 * 24 * 365));

    $previousUrl = url()->previous();

    if(str_contains($previousUrl, '/switcher/device/')) {
        return redirect()->route('user.desktop.index');
    }

    return redirect()->back();
})->name('device.switch')->whereIn('type', ['desktop', 'mobile']);

Route::middleware(['user.status', 'auth:sanctum'])->group(function() {
    $resolveDeviceType = function (Request $request) {
        $deviceType = Cookie::get('device_type');

        if(in_array($deviceType, ['desktop', 'mobile'])) {
            return $deviceType;
        }

        $agent = new Agent();
        $agent->setUserAgent($request->userAgent());

        return $agent->isMobile() ? 'mobile' : 'desktop';
    };

    Route::get('/', function (Request $request) use ($resolveDeviceType) {
        $deviceType = $resolveDeviceType($request);

        if($deviceType == 'mobile') {
            return view('mobile::index');
        }

        else{
            return view('desktop::index');
        }
    })->name('user.desktop.index');

    Route::get('{any}', function (Request $request) use ($resolveDeviceType) {
        $deviceType = $resolveDeviceType($request);

        if($deviceType == 'mobile') {
            return view('mobile::index');
        }

        else{
            return view('desktop::index');
        }
    })->where('any', '.*');
});
