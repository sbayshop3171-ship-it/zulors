<?php

namespace App\Livewire\Admin\Config;

use App\Settings\AuthSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Auth extends Component
{
    public array $formData;

    public function mount(): void
    {
        $authSettings = app(AuthSettings::class);

        $this->formData = [
            'registration_enabled' => $authSettings->registration_enabled,
            'login_enabled' => $authSettings->login_enabled,
            'reg_verification_enabled' => $authSettings->reg_verification_enabled,
            'switch_account_enabled' => $authSettings->switch_account_enabled,
            'link_accounts_enabled' => $authSettings->link_accounts_enabled,
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.registration_enabled' => ['required', 'boolean'],
            'formData.login_enabled' => ['required', 'boolean'],
            'formData.reg_verification_enabled' => ['required', 'boolean'],
            'formData.switch_account_enabled' => ['required', 'boolean'],
            'formData.link_accounts_enabled' => ['required', 'boolean'],
        ], attributes: [
            'formData.registration_enabled' => __('admin/config.callout.registration_enabled.title'),
            'formData.login_enabled' => __('admin/config.callout.login_enabled.title'),
            'formData.reg_verification_enabled' => __('admin/config.callout.reg_verification_enabled.title'),
            'formData.switch_account_enabled' => __('admin/config.callout.switch_account_enabled.title'),
            'formData.link_accounts_enabled' => __('admin/config.callout.link_accounts_enabled.title'),
        ]);

        $authSettings = app(AuthSettings::class);

        $authSettings->registration_enabled = $this->formData['registration_enabled'];
        $authSettings->login_enabled = $this->formData['login_enabled'];
        $authSettings->reg_verification_enabled = $this->formData['reg_verification_enabled'];
        $authSettings->switch_account_enabled = $this->formData['switch_account_enabled'];
        $authSettings->link_accounts_enabled = $this->formData['link_accounts_enabled'];
        $authSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())->route('admin.config.auth');
    }

    public function render()
    {
        return view('livewire.admin.config.auth');
    }
}
