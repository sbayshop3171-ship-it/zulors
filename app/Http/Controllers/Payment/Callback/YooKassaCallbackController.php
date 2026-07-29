<?php

namespace App\Http\Controllers\Payment\Callback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class YooKassaCallbackController extends Controller
{
    public function handleSuccess(Request $request)
    {
        return redirect()->to(url('/wallet?payment=pending'));
    }

    public function handleCancel(Request $request)
    {
        return redirect()->to(url('/wallet?payment=cancelled'));
    }
}
