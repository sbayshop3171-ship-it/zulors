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

Route::delete('/profile/delete', [App\Http\Controllers\Api\Admin\AdminController::class, 'deleteProfile']);
Route::middleware(['api_key'])->post('/verification/user/verify', [App\Http\Controllers\Api\Admin\VerificationController::class, 'verifyUser']);
