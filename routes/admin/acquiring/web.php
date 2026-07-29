<?php

use Illuminate\Support\Facades\Route;

Route::get('/', [App\Http\Controllers\Admin\Acquiring\AcquiringController::class, 'index'])
    ->name('admin.acquiring.index');

Route::get('/edit/{provider}', [App\Http\Controllers\Admin\Acquiring\AcquiringController::class, 'edit'])
    ->name('admin.acquiring.edit');
