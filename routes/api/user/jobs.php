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

Route::get('/categories', [App\Http\Controllers\Api\User\Job\JobController::class, 'getCategories']);
Route::post('/jobs', [App\Http\Controllers\Api\User\Job\JobController::class, 'getJobs']);
Route::get('/jobs/{jobId}', [App\Http\Controllers\Api\User\Job\JobController::class, 'getJobData']);
Route::post('/bookmarks/add', [App\Http\Controllers\Api\User\Job\JobController::class, 'bookmark']);
