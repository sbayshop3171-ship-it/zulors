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

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Business\Dashboard\DashboardController::class, 'index'])
    ->name('business.dashboard.index');

Route::get('/market', [App\Http\Controllers\Business\Market\MarketController::class, 'index'])
    ->name('business.market.index');

Route::middleware('sided.layout')->get('/market/create', [App\Http\Controllers\Business\Market\MarketController::class, 'create'])
    ->name('business.market.create');

Route::middleware('sided.layout')->get('/market/{productId}/show', [App\Http\Controllers\Business\Market\MarketController::class, 'show'])
    ->name('business.market.show');

Route::middleware('sided.layout')->get('/market/{productId}/edit', [App\Http\Controllers\Business\Market\MarketController::class, 'edit'])
    ->name('business.market.edit');

Route::post('/market/{productId}/delete', [App\Http\Controllers\Business\Market\MarketController::class, 'destroy'])
    ->name('business.market.destroy');

Route::post('/market/{productId}/unpublish', [App\Http\Controllers\Business\Market\MarketController::class, 'unpublish'])
    ->name('business.market.unpublish');

Route::post('/market/{productId}/publish', [App\Http\Controllers\Business\Market\MarketController::class, 'publish'])
    ->name('business.market.publish');

Route::middleware('sided.layout')->group(function() {
    Route::get('/ads', [App\Http\Controllers\Business\Ads\AdsController::class, 'index'])
    ->name('business.ads.index');

    Route::get('/ads/create', [App\Http\Controllers\Business\Ads\AdsController::class, 'create'])
        ->name('business.ads.create');

    Route::get('/ads/{adId}/show', [App\Http\Controllers\Business\Ads\AdsController::class, 'show'])
        ->name('business.ads.show')->where('adId', '\d+');

    Route::get('/ads/{adId}/edit', [App\Http\Controllers\Business\Ads\AdsController::class, 'edit'])
        ->name('business.ads.edit')->where('adId', '\d+');
});

Route::middleware('sided.layout')->group(function() {
    Route::get('/jobs', [App\Http\Controllers\Business\Job\JobController::class, 'index'])
        ->name('business.jobs.index');

    Route::get('/jobs/create', [App\Http\Controllers\Business\Job\JobController::class, 'create'])
        ->name('business.jobs.create');

    Route::get('/jobs/{jobId}/show', [App\Http\Controllers\Business\Job\JobController::class, 'show'])
        ->name('business.jobs.show')->where('jobId', '\d+');

    Route::get('/jobs/{jobId}/edit', [App\Http\Controllers\Business\Job\JobController::class, 'edit'])
        ->name('business.jobs.edit')->where('jobId', '\d+');
});

Route::post('/jobs/{jobId}/delete', [App\Http\Controllers\Business\Job\JobController::class, 'destroy'])
    ->name('business.jobs.destroy')->where('jobId', '\d+');

Route::post('/ads/{adId}/delete', [App\Http\Controllers\Business\Ads\AdsController::class, 'destroy'])
    ->name('business.ads.destroy')->where('adId', '\d+');

Route::post('/ads/{adId}/pause', [App\Http\Controllers\Business\Ads\AdsController::class, 'pause'])->name('business.ads.pause')
    ->where('adId', '\d+');

Route::post('/ads/{adId}/publish', [App\Http\Controllers\Business\Ads\AdsController::class, 'publish'])->name('business.ads.publish')
    ->where('adId', '\d+');

Route::post('/jobs/{jobId}/unpublish', [App\Http\Controllers\Business\Job\JobController::class, 'unpublish'])
    ->name('business.jobs.unpublish')->where('jobId', '\d+');

Route::post('/jobs/{jobId}/publish', [App\Http\Controllers\Business\Job\JobController::class, 'publish'])
    ->name('business.jobs.publish')->where('jobId', '\d+');

Route::middleware('features.status:wallet')->get('/wallet', [App\Http\Controllers\Business\Wallet\WalletController::class, 'index'])
    ->name('business.wallet.index');

Route::middleware('features.status:wallet')->get('/wallet/cashouts', [App\Http\Controllers\Business\Wallet\WalletController::class, 'index'])
    ->name('business.wallet.cashouts');

Route::middleware('features.status:wallet')->get('/wallet/cashouts/new', [App\Http\Controllers\Business\Wallet\WalletController::class, 'createCashout'])
    ->name('business.wallet.create-cashout');

Route::get('/notifications', [App\Http\Controllers\Business\Notifications\NotificationController::class, 'index'])
    ->name('business.notifications.index');

Route::get('/settings', [App\Http\Controllers\Business\Settings\SettingsController::class, 'index'])
    ->name('business.settings.index');

Route::get('/settings/edit', [App\Http\Controllers\Business\Settings\SettingsController::class, 'edit'])
    ->name('business.settings.edit');
