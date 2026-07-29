<?php

namespace App\Http\Controllers\Payment\Callback;

use Throwable;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessService;

class StripeCallbackController extends Controller
{
    public function handleSuccess(Request $request, PaymentProcessService $paymentProcessService)
    {
        $redirectStatus = 'pending';
        $sessionId = $request->get('session_id');

        try {
            if ($sessionId) {
                $stripeClient = new StripeClient(config('payment.providers.stripe.credentials.secret_key'));
                $session = $stripeClient->checkout->sessions->retrieve($sessionId);

                if ($session->payment_status === 'paid' || $session->status === 'complete') {
                    $paymentProcessService->getHandler($session->id)->handleSuccess();
                    $redirectStatus = 'success';
                }
            }
        } catch (Throwable $th) {
            payment_log($th->getMessage());
            $redirectStatus = 'failed';
        }

        return redirect()->to(url("/wallet?payment={$redirectStatus}"));
    }

    public function handleCancel(Request $request)
    {
        return redirect()->to(url('/wallet?payment=cancelled'));
    }
}
