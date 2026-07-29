<?php

namespace App\Http\Controllers\Payment\Callback;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentCaptureService;
use App\Services\Payment\PaymentProcessService;

class PaypalCallbackController extends Controller
{
    public function handleSuccess(Request $request)
    {
        $redirectStatus = 'pending';

        try {
            $referenceId = $request->get('token');
            $paymentCaptureService = new PaymentCaptureService('paypal');
            $isCaptured = $paymentCaptureService->capturePayment($referenceId);

            if($isCaptured) {
                $paymentProcessService = app(PaymentProcessService::class);
                $paymentProcessService->getHandler($referenceId)->handleSuccess();
                $redirectStatus = 'success';
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
