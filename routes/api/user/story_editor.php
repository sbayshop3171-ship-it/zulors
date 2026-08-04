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

Route::post('/create', [App\Http\Controllers\Api\User\Story\StoryController::class, 'create']);

Route::post('/media/upload', [App\Http\Controllers\Api\User\Story\StoryMediaController::class, 'uploadMedia']);
Route::get('/media/video/preview/{mediaId}', [App\Http\Controllers\Api\User\Story\StoryMediaController::class, 'previewVideo']);

Route::delete('/media/delete', [App\Http\Controllers\Api\User\Story\StoryMediaController::class, 'deleteMedia']);
