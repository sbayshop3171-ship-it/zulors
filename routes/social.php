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

Route::name('social-login.')->prefix('social-login')->group(function() {
    // Google login routes
    Route::get('/auth/google', [App\Http\Controllers\User\Auth\Social\GoogleAuthController::class, 'index'])->name('google.redirect');

    Route::get('/callback/google',[App\Http\Controllers\User\Auth\Social\GoogleAuthController::class, 'callbackHandler'])->name('google.callback');
});
