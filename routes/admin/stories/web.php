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

Route::get('/', [App\Http\Controllers\Admin\Story\StoryController::class, 'index'])->name('admin.stories.index');
Route::middleware('sided.layout')->get('/show/{frameId}', [App\Http\Controllers\Admin\Story\StoryController::class, 'show'])->name('admin.stories.show');
Route::post('/delete/{frameId}', [App\Http\Controllers\Admin\Story\StoryController::class, 'destroy'])->name('admin.stories.destroy');