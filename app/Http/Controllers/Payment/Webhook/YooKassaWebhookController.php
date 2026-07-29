<?php

namespace App\Http\Controllers\Payment\Webhook;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessService;

class YooKassaWebhookController extends Controller
{
    public function handleWebhook(Request $request, PaymentProcessService $paymentProcessService)
    {
        $event = $request->event;
        $eventObject = $request->object;
        
        if ($event === 'payment.succeeded') {
            $paymentProcessService->getHandler($eventObject['id'])->handleSuccess();
        }
        elseif ($event === 'payment.canceled') {
            $paymentProcessService->getHandler($eventObject['id'])->handleFailure();
        }
        elseif ($event === 'payment.refunded') {
            $paymentProcessService->getHandler($eventObject['id'])->handleRefund();
        }
    }
}
