<?php

namespace App\Livewire\Admin\Config;

use App\Settings\Acquiring\YooKassaSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class YooKassa extends Component
{
    public array $formData = [];
    public array $providerInfo = [];

    public function mount()
    {
        $ykSettings = app(YooKassaSettings::class);

        $this->formData = [
            'name' => $ykSettings->yk_name,
            'status' => $ykSettings->yk_status,
            'shop_id' => $ykSettings->yk_shop_id,
            'secret_key' => $ykSettings->yk_secret_key,
            'currency' => $ykSettings->yk_currency,
        ];

        $this->providerInfo = [
            'name' => $ykSettings->yk_name,
            'logo' => $ykSettings->getLogo(),
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.name' => ['required', 'string', 'max:255'],
            'formData.status' => ['required', 'boolean'],
            'formData.shop_id' => ['nullable', 'string', 'max:255'],
            'formData.secret_key' => ['nullable', 'string', 'max:255'],
            'formData.currency' => ['required', 'string', 'size:3', 'uppercase', 'alpha'],
        ], attributes: [
            'formData.name' => __('admin/config.form.provider_name'),
            'formData.status' => __('admin/config.form.provider_status'),
            'formData.shop_id' => __('admin/config.form.shop_id'),
            'formData.secret_key' => __('admin/config.form.secret_key'),
            'formData.currency' => __('admin/config.form.acquiring_currency'),
        ]);

        $ykSettings = app(YooKassaSettings::class);
        $ykSettings->yk_name = $this->formData['name'];
        $ykSettings->yk_status = $this->formData['status'];
        $ykSettings->yk_shop_id = $this->formData['shop_id'];
        $ykSettings->yk_secret_key = $this->formData['secret_key'];
        $ykSettings->yk_currency = $this->formData['currency'];
        $ykSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.acquiring.edit', $ykSettings->getDriver());
    }

    public function render()
    {
        return view('livewire.admin.config.yoo-kassa');
    }
}
