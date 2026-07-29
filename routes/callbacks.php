<?php

use Illuminate\Support\Facades\Route;

Route::get('/payment/callback/stripe/success', [App\Http\Controllers\Payment\Callback\StripeCallbackController::class, 'handleSuccess'])
	->name('callback.stripe.success');

Route::get('/payment/callback/stripe/cancel', [App\Http\Controllers\Payment\Callback\StripeCallbackController::class, 'handleCancel'])
	->name('callback.stripe.cancel');

Route::get('/payment/callback/paypal/success', [App\Http\Controllers\Payment\Callback\PaypalCallbackController::class, 'handleSuccess'])
	->name('callback.paypal.success');
	
Route::get('/payment/callback/paypal/cancel', [App\Http\Controllers\Payment\Callback\PaypalCallbackController::class, 'handleCancel'])
	->name('callback.paypal.cancel');

Route::get('/payment/callback/yoo_kassa/success', [App\Http\Controllers\Payment\Callback\YooKassaCallbackController::class, 'handleSuccess'])
	->name('callback.yoo_kassa.success');

Route::get('/payment/callback/yoo_kassa/cancel', [App\Http\Controllers\Payment\Callback\YooKassaCallbackController::class, 'handleCancel'])
	->name('callback.yoo_kassa.cancel');

Route::get('/payment/callback/robokassa/success', [App\Http\Controllers\Payment\Callback\RoboKassaCallbackController::class, 'handleSuccess'])
	->name('callback.robokassa.success');

Route::get('/payment/callback/robokassa/cancel', [App\Http\Controllers\Payment\Callback\RoboKassaCallbackController::class, 'handleCancel'])
	->name('callback.robokassa.cancel');
