<?php

use App\Http\Controllers\Api\User\DirectAssetUploadController;
use Illuminate\Support\Facades\Route;

Route::post('/create', [DirectAssetUploadController::class, 'create']);
Route::post('/complete', [DirectAssetUploadController::class, 'complete']);
