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

Route::get('/', [App\Http\Controllers\Admin\Job\JobController::class, 'index'])->name('admin.jobs.index');

Route::middleware('sided.layout')->get('/show/{jobId}', [App\Http\Controllers\Admin\Job\JobController::class, 'show'])
	->name('admin.jobs.show');

Route::post('/destroy/{jobId}', [App\Http\Controllers\Admin\Job\JobController::class, 'destroy'])
	->name('admin.jobs.destroy');

Route::post('/approve/{jobId}', [App\Http\Controllers\Admin\Job\JobController::class, 'approve'])
	->name('admin.jobs.approve');

Route::post('/reject/{jobId}', [App\Http\Controllers\Admin\Job\JobController::class, 'reject'])
	->name('admin.jobs.reject');