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

Route::get('/draft', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'getDraftPost']);

Route::post('/create', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'createPost']);

Route::post('/media/image/upload', [App\Http\Controllers\Api\User\Timeline\PostImageController::class, 'uploadImage']);

Route::post('/media/video/upload', [App\Http\Controllers\Api\User\Timeline\PostVideoController::class, 'uploadVideo']);

Route::post('/media/video/direct/create', [App\Http\Controllers\Api\User\Timeline\DirectMediaUploadController::class, 'createVideoUpload']);

Route::post('/media/video/direct/raw', [App\Http\Controllers\Api\User\Timeline\DirectMediaUploadController::class, 'uploadRawVideo']);

Route::post('/media/video/direct/part', [App\Http\Controllers\Api\User\Timeline\DirectMediaUploadController::class, 'uploadVideoPart']);

Route::post('/media/video/direct/progress', [App\Http\Controllers\Api\User\Timeline\DirectMediaUploadController::class, 'updateVideoUploadProgress']);

Route::post('/media/video/direct/complete', [App\Http\Controllers\Api\User\Timeline\DirectMediaUploadController::class, 'completeVideoUpload']);

Route::get('/media/video/preview/{mediaId}', [App\Http\Controllers\Api\User\Timeline\PostMediaController::class, 'previewVideo']);

Route::post('/media/audio/upload', [App\Http\Controllers\Api\User\Timeline\PostAudioController::class, 'uploadAudio']);

Route::post('/media/document/upload', [App\Http\Controllers\Api\User\Timeline\PostDocumentController::class, 'uploadDocument']);

Route::delete('/media/delete', [App\Http\Controllers\Api\User\Timeline\PostMediaController::class, 'deleteMedia']);

Route::post('/poll/create', [App\Http\Controllers\Api\User\Timeline\PostPollController::class, 'createPoll']);

Route::delete('/poll/delete', [App\Http\Controllers\Api\User\Timeline\PostPollController::class, 'deletePoll']);

Route::post('/gif/create', [App\Http\Controllers\Api\User\Timeline\PostGifController::class, 'createGif']);

Route::post('/link/preview', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'previewLink']);

Route::delete('/link/delete', [App\Http\Controllers\Api\User\Timeline\PostController::class, 'deleteLinkSnapshot']);
