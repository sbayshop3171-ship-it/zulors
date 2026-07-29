<?php

namespace App\Livewire\Admin\Config;

use App\Settings\CodeSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class CodeInjection extends Component
{
    public array $formData = [];

    public function mount()
    {
        $codeSettings = app(CodeSettings::class);
        $this->formData = [
            'header_code' => $codeSettings->header_code,
            'footer_code' => $codeSettings->footer_code,
            'header_code_enabled' => $codeSettings->header_code_enabled,
            'footer_code_enabled' => $codeSettings->footer_code_enabled,
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.header_code' => ['nullable', 'string'],
            'formData.footer_code' => ['nullable', 'string'],
            'formData.header_code_enabled' => ['required', 'boolean'],
            'formData.footer_code_enabled' => ['required', 'boolean'],
        ], attributes: [
            'formData.header_code' => __('admin/config.form.header_code'),
            'formData.footer_code' => __('admin/config.form.footer_code'),
            'formData.header_code_enabled' => __('admin/config.form.header_code_enabled'),
            'formData.footer_code_enabled' => __('admin/config.form.footer_code_enabled'),
        ]);

        $codeSettings = app(CodeSettings::class);
        $codeSettings->header_code = $this->formData['header_code'];
        $codeSettings->footer_code = $this->formData['footer_code'];
        $codeSettings->header_code_enabled = $this->formData['header_code_enabled'];
        $codeSettings->footer_code_enabled = $this->formData['footer_code_enabled'];
        $codeSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.code-injection');
    }

    public function render()
    {
        return view('livewire.admin.config.code-injection');
    }
}
