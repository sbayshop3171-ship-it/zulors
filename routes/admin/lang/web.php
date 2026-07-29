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

Route::get('/', [App\Http\Controllers\Admin\Lang\LangController::class, 'index'])->name('admin.lang.index');

Route::middleware('sided.layout')->get('/show/{localeId}', [App\Http\Controllers\Admin\Lang\LangController::class, 'show'])->name('admin.lang.show');

Route::post('/disable/{localeId}', [App\Http\Controllers\Admin\Lang\LangController::class, 'disable'])->name('admin.lang.disable');

Route::post('/enable/{localeId}', [App\Http\Controllers\Admin\Lang\LangController::class, 'enable'])->name('admin.lang.enable');

Route::post('/make-default/{localeId}', [App\Http\Controllers\Admin\Lang\LangController::class, 'makeDefault'])->name('admin.lang.make_default');

Route::get('/create', [App\Http\Controllers\Admin\Lang\LangController::class, 'create'])->name('admin.lang.create');

Route::post('/store', [App\Http\Controllers\Admin\Lang\LangController::class, 'store'])->name('admin.lang.store');

Route::post('/delete/{localeId}', [App\Http\Controllers\Admin\Lang\LangController::class, 'destroy'])->name('admin.lang.delete');