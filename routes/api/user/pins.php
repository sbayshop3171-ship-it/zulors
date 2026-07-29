<?php

use Illuminate\Support\Facades\Route;

Route::get('/posts/global', [App\Http\Controllers\Api\User\Pin\PinController::class, 'getGlobalPinnedPosts']);