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

Route::get('/all', [App\Http\Controllers\Api\User\Notification\NotificationController::class, 'getAll']);
Route::get('/mentions', [App\Http\Controllers\Api\User\Notification\NotificationController::class, 'getMentions']);
Route::get('/important', [App\Http\Controllers\Api\User\Notification\NotificationController::class, 'getImportant']);
Route::get('/unread/count', [App\Http\Controllers\Api\User\Notification\NotificationController::class, 'getUnreadCount']);
Route::delete('/delete', [App\Http\Controllers\Api\User\Notification\NotificationController::class, 'deleteNotification']);