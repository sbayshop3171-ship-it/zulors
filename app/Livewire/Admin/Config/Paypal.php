<?php

namespace App\Livewire\Admin\Config;

use App\Settings\Acquiring\PaypalSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Paypal extends Component
{
    public array $formData = [];
    public array $providerInfo = [];

    public function mount()
    {
        $paypalSettings = app(PaypalSettings::class);

        $this->formData = [
            'name' => $paypalSettings->paypal_name,
            'status' => $paypalSettings->paypal_status,
            'client_id' => $paypalSettings->paypal_client_id,
            'secret_key' => $paypalSettings->paypal_secret_key,
            'mode' => $paypalSettings->paypal_mode,
            'webhook' => $paypalSettings->paypal_webhook,
        ];

        $this->providerInfo = [
            'name' => $paypalSettings->paypal_name,
            'logo' => $paypalSettings->getLogo(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.config.paypal');
    }

    public function submitForm()
    {
        $this->validate([
            'formData.name' => ['required', 'string', 'max:255'],
            'formData.status' => ['required', 'boolean'],
            'formData.client_id' => ['nullable', 'string', 'max:1200'],
            'formData.secret_key' => ['nullable', 'string', 'max:1200'],
            'formData.mode' => ['required', 'string', 'in:sandbox,live'],
        ], attributes: [
            'formData.name' => __('admin/config.form.provider_name'),
            'formData.status' => __('admin/config.form.provider_status'),
            'formData.client_id' => __('admin/config.form.client_id'),
            'formData.secret_key' => __('admin/config.form.secret_key'),
            'formData.mode' => __('admin/config.form.mode'),
        ]);

        $paypalSettings = app(PaypalSettings::class);
        $paypalSettings->paypal_name = $this->formData['name'];
        $paypalSettings->paypal_status = $this->formData['status'];
        $paypalSettings->paypal_client_id = $this->formData['client_id'];
        $paypalSettings->paypal_secret_key = $this->formData['secret_key'];
        $paypalSettings->paypal_mode = $this->formData['mode'];
        $paypalSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.acquiring.edit', $paypalSettings->getDriver());
    }
}
