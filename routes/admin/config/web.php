<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Config\ConfigController::class, 'index'])->name('admin.config.index');

Route::get('/branding', [App\Http\Controllers\Admin\Config\ConfigController::class, 'branding'])->name('admin.config.branding');

Route::get('/general', [App\Http\Controllers\Admin\Config\ConfigController::class, 'general'])->name('admin.config.general');

Route::get('/ffmpeg', [App\Http\Controllers\Admin\Config\ConfigController::class, 'ffmpeg'])->name('admin.config.ffmpeg');

Route::get('/email', [App\Http\Controllers\Admin\Config\ConfigController::class, 'email'])->name('admin.config.email');

Route::get('/mobile-apps/{tab?}', [App\Http\Controllers\Admin\Config\ConfigController::class, 'mobileApps'])->name('admin.config.mobile-apps');

Route::get('/social-login', [App\Http\Controllers\Admin\Config\ConfigController::class, 'socialLogin'])->name('admin.config.social-login');

Route::post('/email/testing', [App\Http\Controllers\Admin\Config\ConfigController::class, 'emailTesting'])->name('admin.config.email-testing');

Route::get('/notifications', [App\Http\Controllers\Admin\Config\ConfigController::class, 'notifications'])->name('admin.config.notifications');

Route::get('/api', [App\Http\Controllers\Admin\Config\ConfigController::class, 'api'])->name('admin.config.api');

Route::get('/verification', [App\Http\Controllers\Admin\Config\ConfigController::class, 'verification'])->name('admin.config.verification');

Route::get('/wallet', [App\Http\Controllers\Admin\Config\ConfigController::class, 'wallet'])->name('admin.config.wallet');

Route::get('/auth', [App\Http\Controllers\Admin\Config\ConfigController::class, 'auth'])->name('admin.config.auth');

Route::get('/code-injection', [App\Http\Controllers\Admin\Config\ConfigController::class, 'codeInjection'])->name('admin.config.code-injection');

Route::get('/backup', [App\Http\Controllers\Admin\Config\ConfigController::class, 'backup'])->name('admin.config.backup');
