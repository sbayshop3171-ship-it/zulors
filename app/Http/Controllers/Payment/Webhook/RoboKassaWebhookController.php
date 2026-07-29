<?php

namespace App\Http\Controllers\Payment\Webhook;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessService;

class RoboKassaWebhookController extends Controller
{
    private $passTwo;

    public function __construct()
    {
        $this->passTwo = config('payment.providers.robokassa.credentials.pass_two');
    }

    public function handleWebhook(Request $request, PaymentProcessService $paymentProcessService)
    {
        try {
            $outSum = $request->get('OutSum', $request->get('out_summ'));
            $invId = $request->get('InvId', $request->get('inv_id'));
            $signature = $request->get('SignatureValue', $request->get('crc'));

            if(empty($outSum) || empty($invId) || empty($signature)) {
                payment_log('RoboKassa webhook missing required payment fields.', $request->all());

                return response('Missing required payment fields.', 422);
            }
            
            $mySignature = strtoupper(md5("{$outSum}:{$invId}:{$this->passTwo}"));
            $signature = strtoupper($signature);

            if ($mySignature !== $signature) {
                payment_log('Invalid signature');

                return response('Invalid signature.', 400);
            }
            else {
                $paymentProcessService->getHandler($invId)->handleSuccess();

                return response("OK{$invId}");
            }
        } catch (Throwable $th) {
            payment_log($th->getMessage());

            return response('Webhook processing failed.', 500);
        }
    }
}
