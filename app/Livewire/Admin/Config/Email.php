<?php

namespace App\Livewire\Admin\Config;

use App\Settings\MailSettings;
use App\Support\Views\Flash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class Email extends Component
{
    public array $formData = [];

    public function mount()
    {
        $mailSettings = app(MailSettings::class);

        $this->formData = [
            'transport' => $mailSettings->transport,
            'host' => $mailSettings->host,
            'port' => $mailSettings->port,
            'timeout' => $mailSettings->timeout,
            'username' => $mailSettings->username,
            'password' => $mailSettings->password,
            'encryption' => $mailSettings->encryption,
            'from_address' => $mailSettings->from_address,
            'from_name' => $mailSettings->from_name,
            'local_domain' => $mailSettings->local_domain,
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.transport' => ['required', 'string', Rule::in(['smtp', 'mail', 'log'])],
            'formData.host' => ['required', 'string', 'max:255'],
            'formData.port' => ['required', 'integer', 'min:1', 'max:65535'],
            'formData.timeout' => ['required', 'integer', 'min:1', 'max:600'],
            'formData.username' => ['required', 'string', 'max:255'],
            'formData.password' => ['required', 'string', 'max:255'],
            'formData.encryption' => ['required', 'string', Rule::in(['ssl', 'tls'])],
            'formData.from_address' => ['required', 'email', 'max:255'],
            'formData.from_name' => ['required', 'string', 'max:255'],
            'formData.local_domain' => ['nullable', 'string', 'max:255'],
        ], attributes: [
            'formData.transport' => __('admin/config.form.email_transport'),
            'formData.host' => __('admin/config.form.host'),
            'formData.port' => __('admin/config.form.port'),
            'formData.timeout' => __('admin/config.form.timeout'),
            'formData.username' => __('admin/config.form.username'),
            'formData.password' => __('admin/config.form.password'),
            'formData.encryption' => __('admin/config.form.encryption'),
            'formData.from_address' => __('admin/config.form.from_address'),
            'formData.from_name' => __('admin/config.form.from_name'),
            'formData.local_domain' => __('admin/config.form.local_domain'),
        ]);

        $mailSettings = app(MailSettings::class);

        $mailSettings->transport = $this->formData['transport'];
        $mailSettings->host = $this->formData['host'];
        $mailSettings->port = $this->formData['port'];
        $mailSettings->timeout = $this->formData['timeout'];
        $mailSettings->username = $this->formData['username'];
        $mailSettings->password = $this->formData['password'];
        $mailSettings->encryption = $this->formData['encryption'];
        $mailSettings->from_address = $this->formData['from_address'];
        $mailSettings->from_name = $this->formData['from_name'];
        $mailSettings->local_domain = $this->formData['local_domain'];
        $mailSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())->route('admin.config.email');
    }

    public function render()
    {
        return view('livewire.admin.config.email');
    }
}
