<?php

namespace App\Livewire\Admin\Config;

use App\Settings\Acquiring\RobokassaSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class RoboKassa extends Component
{
    public array $formData = [];
    public array $providerInfo = [];

    public function mount()
    {
        $rkSettings = app(RobokassaSettings::class);

        $this->formData = [
            'name' => $rkSettings->rk_name,
            'status' => $rkSettings->rk_status,
            'merchant_login' => $rkSettings->rk_merchant_login,
            'pass_one' => $rkSettings->rk_pass_one,
            'pass_two' => $rkSettings->rk_pass_two,
            'currency' => $rkSettings->rk_currency,
        ];

        $this->providerInfo = [
            'name' => $rkSettings->rk_name,
            'logo' => $rkSettings->getLogo(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.config.robo-kassa');
    }

    public function submitForm()
    {
        $this->validate([
            'formData.name' => ['required', 'string', 'max:255'],
            'formData.status' => ['required', 'boolean'],
            'formData.merchant_login' => ['nullable', 'string', 'max:255'],
            'formData.pass_one' => ['nullable', 'string', 'max:255'],
            'formData.pass_two' => ['nullable', 'string', 'max:255'],
            'formData.currency' => ['required', 'string', 'size:3', 'uppercase', 'alpha'],
        ], attributes: [
            'formData.name' => __('admin/config.form.provider_name'),
            'formData.status' => __('admin/config.form.provider_status'),
            'formData.merchant_login' => __('admin/config.form.merchant_login'),
            'formData.pass_one' => __('admin/config.form.pass_one'),
            'formData.pass_two' => __('admin/config.form.pass_two'),
            'formData.currency' => __('admin/config.form.acquiring_currency'),
        ]);

        $rkSettings = app(RobokassaSettings::class);
        $rkSettings->rk_name = $this->formData['name'];
        $rkSettings->rk_status = $this->formData['status'];
        $rkSettings->rk_merchant_login = $this->formData['merchant_login'];
        $rkSettings->rk_pass_one = $this->formData['pass_one'];
        $rkSettings->rk_pass_two = $this->formData['pass_two'];
        $rkSettings->rk_currency = $this->formData['currency'];
        $rkSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.acquiring.edit', $rkSettings->getDriver());
    }
}
