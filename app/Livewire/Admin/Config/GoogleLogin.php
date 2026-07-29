<?php

namespace App\Livewire\Admin\Config;

use App\Settings\GoogleLoginSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class GoogleLogin extends Component
{
    public array $formData = [];
    public array $providerInfo = [];

    public function mount()
    {
        $this->providerInfo = config('social-login.providers.google');

        $this->formData = [
            'status' => $this->providerInfo['enabled'],
            'client_id' => $this->providerInfo['credentials']['client_id'],
            'client_secret' => $this->providerInfo['credentials']['client_secret'],
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.client_id' => ['requiredIf:formData.status,true', 'string', 'max:255'],
            'formData.client_secret' => ['requiredIf:formData.status,true', 'string', 'max:255'],
            'formData.status' => ['required', 'boolean'],
        ], attributes: [
            'formData.client_id' => __('admin/config.form.client_id'),
            'formData.client_secret' => __('admin/config.form.client_secret'),
            'formData.status' => __('admin/config.form.provider_status'),
        ]);

        $googleLoginSettings = app(GoogleLoginSettings::class);
        $googleLoginSettings->enabled = $this->formData['status'];
        $googleLoginSettings->client_id = $this->formData['client_id'];
        $googleLoginSettings->client_secret = $this->formData['client_secret'];
        $googleLoginSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.social-login');
    }

    public function render()
    {
        return view('livewire.admin.config.google-login');
    }
}
