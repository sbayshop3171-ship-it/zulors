<?php

namespace App\Http\Controllers\Admin\Currency;

use App\Models\Currency;
use App\Http\Controllers\Controller;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::latest()->paginate(10);

        return view('admin::currencies.index.index', [
            'currencies' => $currencies
        ]);
    }

    public function show(int $currencyId)
    {
        $currencyData = Currency::findOrFail($currencyId);

        return view('admin::currencies.show.index', [
            'currencyData' => $currencyData
        ]);
    }
}
