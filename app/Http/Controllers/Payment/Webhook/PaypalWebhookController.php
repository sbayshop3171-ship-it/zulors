<?php

namespace App\Http\Controllers\Payment\Webhook;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\Drivers\PayPalDriver;

class PaypalWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        payment_log($request->getContent());
    }
}
