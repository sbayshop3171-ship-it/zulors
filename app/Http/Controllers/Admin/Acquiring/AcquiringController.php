<?php

namespace App\Http\Controllers\Admin\Acquiring;

use Illuminate\Http\Request;

class AcquiringController
{
    public function index(Request $request)
    {
        $providers = config('payment.providers');

        return view('admin::acquiring.index.index', [
            'acquiringProviders' => $providers,
        ]);
    }

    public function edit(Request $request, string $provider)
    {
        $providerDrivers = collect(config('payment.providers', []))->pluck('driver')->all();

        if(! in_array($provider, $providerDrivers)) {
            abort(404);
        }

        return view('admin::config.acquiring.edit', [
            'provider' => $provider,
        ]);
    }
}
