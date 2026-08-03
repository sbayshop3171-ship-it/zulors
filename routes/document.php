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

Route::view('/terms-of-use', 'apps.mpa.document.terms.index')->name('document.terms.public');
Route::view('/privacy-policy', 'apps.mpa.document.privacy.index')->name('document.privacy.public');
Route::view('/cookies-policy', 'apps.mpa.document.cookies.index')->name('document.cookies.public');
Route::view('/account-deletion', 'apps.mpa.document.account_deletion.index')->name('document.account-deletion.public');
Route::view('/child-safety-standards', 'apps.mpa.document.child_safety.index')->name('document.child-safety.public');

Route::name('document.')->prefix('document')->group(function() {
    Route::view('/about', 'apps.mpa.document.about.index')->name('about.index');
    Route::view('/help-center', 'apps.mpa.document.help.index')->name('help.index');
    Route::view('/terms-of-use', 'apps.mpa.document.terms.index')->name('terms.index');
    Route::view('/privacy-policy', 'apps.mpa.document.privacy.index')->name('privacy.index');
    Route::view('/cookies-policy', 'apps.mpa.document.cookies.index')->name('cookies.index');
    Route::view('/account-deletion', 'apps.mpa.document.account_deletion.index')->name('account-deletion.index');
    Route::view('/child-safety-standards', 'apps.mpa.document.child_safety.index')->name('child-safety.index');
    Route::view('/developers-api', 'apps.mpa.document.developers.index')->name('developers.index');
    Route::view('/verification-rules', 'apps.mpa.document.verification.index')->name('verification.index');
    Route::view('/become-author', 'apps.mpa.document.author.index')->name('author.index');
});
