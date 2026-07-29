<?php

namespace App\Livewire\Admin\Config;

use App\Settings\AppSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Mobile extends Component
{
    public array $formData = [];

    public function mount()
    {
        $appSettings = app(AppSettings::class);

        $this->formData = [
            'android_link' => $appSettings->mobile_app_android_link,
            'ios_link' => $appSettings->mobile_app_ios_link,
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.android_link' => ['nullable', 'url', 'max:300'],
            'formData.ios_link' => ['nullable', 'url', 'max:300'],
        ], attributes: [
            'formData.android_link' => __('admin/mobile.form.android_link'),
            'formData.ios_link' => __('admin/mobile.form.ios_link'),
        ]);

        $appSettings = app(AppSettings::class);

        $appSettings->mobile_app_android_link = $this->formData['android_link'];
        $appSettings->mobile_app_ios_link = $this->formData['ios_link'];

        $appSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.mobile-apps');
    }

    public function render()
    {
        return view('livewire.admin.config.mobile');
    }
}
