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

Route::get('/', [App\Http\Controllers\Admin\Page\PageController::class, 'index'])->name('admin.pages.index');
Route::get('/{pageName}/edit', [App\Http\Controllers\Admin\Page\PageController::class, 'edit'])->name('admin.pages.edit');
Route::post('/store', [App\Http\Controllers\Admin\Page\PageController::class, 'store'])->name('admin.pages.store');