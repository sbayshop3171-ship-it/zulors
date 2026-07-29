<?php

namespace App\Http\Controllers\Admin\Payment;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with(['user'])->latest()->paginate(10);

        return view('admin::payments.index.index', [
            'payments' => $payments,
        ]);
    }

    public function show(Request $request, $paymentId)
    {
        $paymentData = Payment::with(['user'])->findOrFail($paymentId);

        return view('admin::payments.show.index', [
            'paymentData' => $paymentData,
            'metadata' => $paymentData->metadata,
        ]);
    }
}
