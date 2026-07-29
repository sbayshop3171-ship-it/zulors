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

Route::post('/follow/user', [App\Http\Controllers\Api\User\Relations\FollowsController::class, 'followUser']);
Route::post('/accept/user', [App\Http\Controllers\Api\User\Relations\FollowsController::class, 'acceptFollowRequest']);
Route::post('/mute/user', [App\Http\Controllers\Api\User\Relations\MuteController::class, 'muteUser']);
Route::post('/block/user', [App\Http\Controllers\Api\User\Relations\BlockController::class, 'blockUser']);
Route::get('/block/list', [App\Http\Controllers\Api\User\Relations\BlockController::class, 'getBlockedUsers']);
