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

Route::get('/', [App\Http\Controllers\Admin\User\UserController::class, 'index'])->name('admin.users.index');
Route::middleware('sided.layout')->get('/show/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'show'])->name('admin.users.show');
Route::middleware('sided.layout')->get('/wallet/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'wallet'])->name('admin.users.wallet');
Route::middleware('sided.layout')->post('/delete/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'destroy'])->name('admin.users.destroy');
Route::post('/authorize/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'authorize'])->name('admin.users.authorize');
Route::post('/unauthorize/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'unauthorize'])->name('admin.users.unauthorize');
Route::post('/authorization/{userId}/reject', [App\Http\Controllers\Admin\User\UserController::class, 'rejectAuthorization'])->name('admin.users.auth-reject');

Route::post('/verify/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'verify'])->name('admin.users.verify');
Route::post('/unverify/{userId}', [App\Http\Controllers\Admin\User\UserController::class, 'unverify'])->name('admin.users.unverify');
Route::post('/followers/{userId}/test-sync', [App\Http\Controllers\Admin\User\UserController::class, 'syncTestFollowers'])->name('admin.users.test-followers.sync');
