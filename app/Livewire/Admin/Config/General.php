<?php

namespace App\Livewire\Admin\Config;

use App\Settings\AppSettings;
use App\Support\Languages;
use App\Support\Views\Flash;
use Illuminate\Validation\Rule;
use Livewire\Component;

class General extends Component
{
    public array $formData = [];

    public array $timezones = [];

    public array $locales = [];

    public function mount()
    {
        $appSettings = app(AppSettings::class);

        $this->timezones = collect(config('timezones'))->map(function($timezone, $key) {
            return [
                'key' => $key,
                'value' => $timezone,
            ];
        })->values()->toArray();

        $this->locales = (new Languages())->getLanguages()->map(function($locale) {
            return [
                'key' => $locale->alpha_2_code,
                'value' => $locale->native_name,
            ];
        })->values()->toArray();

        $this->formData = [
            'name' => $appSettings->name,
            'description' => $appSettings->description,
            'keywords' => $appSettings->keywords,
            'timezone' => $appSettings->timezone,
            'locale' => $appSettings->locale,
            'hide_sensitive_data' => $appSettings->hide_sensitive_data,
        ];
    }

    public function submitForm()
    {
        $this->validate([
            'formData.name' => ['required', 'string', 'max:120'],
            'formData.description' => ['required', 'string', 'max:255'],
            'formData.keywords' => ['required', 'string', 'max:1200'],
            'formData.timezone' => ['required', 'string', 'max:120'],
            'formData.locale' => ['required', 'string', Rule::in(array_column($this->locales, 'key'))],
            'formData.hide_sensitive_data' => [Rule::requiredIf(me()->isRoot()), 'boolean'],
        ], attributes: [
            'formData.name' => __('admin/config.form.app_name'),
            'formData.description' => __('admin/config.form.app_description'),
            'formData.keywords' => __('admin/config.form.app_keywords'),
            'formData.timezone' => __('admin/config.form.app_timezone'),
            'formData.locale' => __('admin/config.form.app_locale'),
            'formData.hide_sensitive_data' => __('admin/config.form.hide_sensitive_data'),
        ]);

        $appSettings = app(AppSettings::class);

        $appSettings->name = $this->formData['name'];
        $appSettings->description = $this->formData['description'];
        $appSettings->keywords = $this->formData['keywords'];
        $appSettings->timezone = $this->formData['timezone'];
        $appSettings->locale = $this->formData['locale'];

        if(me()->isRoot()) {
            $appSettings->hide_sensitive_data = $this->formData['hide_sensitive_data'];
        }

        $appSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.general');
    }

    public function render()
    {
        return view('livewire.admin.config.general');
    }
}
