<?php

use App\Http\Controllers\Api\User\MediaPublicationController;
use Illuminate\Support\Facades\Route;

Route::get('/capabilities', [MediaPublicationController::class, 'capabilities']);
Route::get('/', [MediaPublicationController::class, 'index']);
Route::post('/', [MediaPublicationController::class, 'store']);
Route::get('/{id}', [MediaPublicationController::class, 'show']);
Route::delete('/{id}', [MediaPublicationController::class, 'destroy']);
Route::post('/{id}/retry', [MediaPublicationController::class, 'retry']);
Route::post('/{id}/items/{itemId}/resume', [MediaPublicationController::class, 'resume']);
Route::post('/{id}/items/{itemId}/complete', [MediaPublicationController::class, 'complete']);
