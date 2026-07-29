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

Route::get('/profile', [App\Http\Controllers\Api\User\Profile\ProfileController::class, 'getProfileData']);
Route::get('/profile/posts', [App\Http\Controllers\Api\User\Profile\ProfileController::class, 'getProfilePosts']);
Route::get('/profile/details', [App\Http\Controllers\Api\User\Profile\ProfileController::class, 'getProfileDetails']);
Route::get('/profile/followers', [App\Http\Controllers\Api\User\Profile\ProfileController::class, 'getProfileFollowers']);
Route::get('/profile/followings', [App\Http\Controllers\Api\User\Profile\ProfileController::class, 'getProfileFollowings']);