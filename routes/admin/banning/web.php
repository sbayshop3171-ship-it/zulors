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

Route::get('/', [App\Http\Controllers\Admin\Banning\BanningController::class, 'index'])->name('admin.banning.index');

Route::middleware(['sided.layout'])->get('/show/{banId}', [App\Http\Controllers\Admin\Banning\BanningController::class, 'show'])->name('admin.banning.show');

Route::post('/delete/{banId}', [App\Http\Controllers\Admin\Banning\BanningController::class, 'destroy'])->name('admin.banning.delete');