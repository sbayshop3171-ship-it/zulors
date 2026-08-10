<?php

use Illuminate\Support\Facades\Route;

Route::get('/home-feed-seed', [App\Http\Controllers\Api\User\Bootstrap\BootstrapController::class, 'homeFeedSeed']);
