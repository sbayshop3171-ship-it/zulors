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

Route::get('/', [App\Http\Controllers\Admin\Post\PostController::class, 'index'])->name('admin.posts.index');

Route::middleware('sided.layout')->get('/show/{postId}', [App\Http\Controllers\Admin\Post\PostController::class, 'show'])->name('admin.posts.show');
Route::middleware('sided.layout')->post('/pin/{postId}', [App\Http\Controllers\Admin\Post\PostController::class, 'pin'])->name('admin.posts.pin');
Route::middleware('sided.layout')->post('/unpin/{postId}', [App\Http\Controllers\Admin\Post\PostController::class, 'unpin'])->name('admin.posts.unpin');

Route::middleware('sided.layout')->post('/delete/{postId}', [App\Http\Controllers\Admin\Post\PostController::class, 'destroy'])->name('admin.posts.destroy');