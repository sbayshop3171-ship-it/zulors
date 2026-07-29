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

Route::get('/', [App\Http\Controllers\Admin\Market\MarketController::class, 'index'])->name('admin.market.index');

Route::middleware('sided.layout')->get('/show/{productId}', [App\Http\Controllers\Admin\Market\MarketController::class, 'show'])->name('admin.market.show');

Route::post('/delete/{productId}', [App\Http\Controllers\Admin\Market\MarketController::class, 'destroy'])->name('admin.market.destroy');

Route::post('/approve/{productId}', [App\Http\Controllers\Admin\Market\MarketController::class, 'approve'])->name('admin.market.approve');

Route::post('/reject/{productId}', [App\Http\Controllers\Admin\Market\MarketController::class, 'reject'])->name('admin.market.reject');