<?php

use Illuminate\Support\Facades\Route;

Route::post('/bio/update', [App\Http\Controllers\Api\User\Tip\TipController::class, 'updateBio']);

Route::post('/recommended/follow', [App\Http\Controllers\Api\User\Tip\TipController::class, 'followRecommendedUsers']);

Route::post('/avatar/update', [App\Http\Controllers\Api\User\Tip\TipController::class, 'updateAvatar']);