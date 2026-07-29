<?php

use Illuminate\Support\Facades\Route;

Route::post('/payment/stripe/webhook', [App\Http\Controllers\Payment\Webhook\StripeWebhookController::class, 'handleWebhook']);
Route::post('/payment/paypal/webhook', [App\Http\Controllers\Payment\Webhook\PaypalWebhookController::class, 'handleWebhook']);
Route::post('/payment/yoo_kassa/webhook', [App\Http\Controllers\Payment\Webhook\YooKassaWebhookController::class, 'handleWebhook']);
Route::post('/payment/robokassa/webhook', [App\Http\Controllers\Payment\Webhook\RoboKassaWebhookController::class, 'handleWebhook']);
