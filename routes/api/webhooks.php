<?php

use Illuminate\Support\Facades\Route;

Route::post('/cloudflare/stream', App\Http\Controllers\Api\Webhooks\CloudflareStreamWebhookController::class);
