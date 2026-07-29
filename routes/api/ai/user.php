<?php

use Illuminate\Support\Facades\Route;

Route::get('/greeting-message', [App\Http\Controllers\Api\User\Timeline\AIController::class, 'greetingMessage']);
