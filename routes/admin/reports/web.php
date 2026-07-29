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

Route::get('/', [App\Http\Controllers\Admin\Report\ReportController::class, 'index'])->name('admin.reports.index');

Route::middleware('sided.layout')->get('/show/{reportId}', [App\Http\Controllers\Admin\Report\ReportController::class, 'show'])->name('admin.reports.show');

Route::post('/ignore/{reportId}', [App\Http\Controllers\Admin\Report\ReportController::class, 'markAsIgnored'])->name('admin.reports.ignore');

Route::post('/delete/{reportId}', [App\Http\Controllers\Admin\Report\ReportController::class, 'destroy'])->name('admin.reports.delete');

Route::post('/processed/{reportId}', [App\Http\Controllers\Admin\Report\ReportController::class, 'markAsProcessed'])->name('admin.reports.processed');