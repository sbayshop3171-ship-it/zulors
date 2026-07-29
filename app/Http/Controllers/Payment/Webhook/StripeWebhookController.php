<?php

namespace App\Http\Controllers\Payment\Webhook;

use Throwable;
use Stripe\Webhook;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessService;

class StripeWebhookController extends Controller
{
    public function handleWebhook(Request $request, PaymentProcessService $paymentProcessService)
    {
        try {
            $payload = $request->getContent();
            $sigHeader = $request->header('Stripe-Signature');
            $endpointSecret = config('payment.providers.stripe.webhook.secret');
            $tolerance = config('payment.providers.stripe.webhook.webhook_tolerance');

            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret, $tolerance);
            $eventType = $event->type;
            $eventObject = $event->data->object;

            if ($eventType === 'checkout.session.expired') {
                $paymentProcessService->getHandler($eventObject->id)->handleExpiration();
            }
            elseif ($eventType === 'checkout.session.completed' || $eventType === 'checkout.session.async_payment_succeeded') {
                $paymentProcessService->getHandler($eventObject->id)->handleSuccess();
            }
            elseif ($eventType === 'checkout.session.async_payment_failed') {
                $paymentProcessService->getHandler($eventObject->id)->handleFailure();
            }
        } catch (Throwable $th) {
            payment_log($th->getMessage());
        }
    }
}
