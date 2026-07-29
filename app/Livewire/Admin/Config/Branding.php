<?php

namespace App\Livewire\Admin\Config;

use App\Settings\BrandSettings;
use App\Support\Views\Flash;
use Exception;
use Livewire\Component;
use Livewire\WithFileUploads;

class Branding extends Component
{
    use WithFileUploads;

    public array $formData = [];
    public $watermarkImage;
    public $lightThemeLogo;
    public $darkThemeLogo;
    public $faviconLogo;

    public function mount()
    {
        $brandSettings = app(BrandSettings::class);

        $this->formData = [
            'dark_theme_enabled' => $brandSettings->dark_theme_enabled,
            'default_theme' => $brandSettings->default_theme,
            'images_watermark_enabled' => $brandSettings->images_watermark_enabled,
            'videos_watermark_enabled' => $brandSettings->videos_watermark_enabled,
        ];
    }

    public function updatedLightThemeLogo()
    {
        try {
            $this->validate([
                'lightThemeLogo' => ['required', 'image', 'mimes:png', 'mimetypes:image/png', 'max:1024'],
            ], attributes: [
                'lightThemeLogo' => 'Light Theme Logo',
            ]);

            $this->lightThemeLogo->storeAs('logos', 'light.png', 'assets');

        } catch (Exception $e) {
            $this->addError('lightThemeLogo', $e->getMessage());
        }
    }

    public function updatedDarkThemeLogo()
    {
        try {
            $this->validate([
                'darkThemeLogo' => ['required', 'image', 'mimes:png', 'mimetypes:image/png', 'max:1024'],
            ], attributes: [
                'darkThemeLogo' => 'Dark Theme Logo',
            ]);

            $this->darkThemeLogo->storeAs('logos', 'dark.png', 'assets');

        } catch (Exception $e) {
            $this->addError('darkThemeLogo', $e->getMessage());
        }
    }

    public function updatedFaviconLogo()
    {
        try {
            $this->validate([
                'faviconLogo' => ['required', 'image', 'mimes:png', 'mimetypes:image/png', 'max:1024'],
            ], attributes: [
                'faviconLogo' => 'Favicon Logo',
            ]);

            $this->faviconLogo->storeAs('logos', 'favicon.png', 'assets');

        } catch (Exception $e) {
            $this->addError('faviconLogo', $e->getMessage());
        }
    }

    public function updatedWatermarkImage()
    {
        try {
            $this->validate([
                'watermarkImage' => ['required', 'image', 'mimes:png', 'mimetypes:image/png', 'max:1024'],
            ], attributes: [
                'watermarkImage' => 'Watermark Image',
            ]);

            $this->watermarkImage->storeAs('watermarks', 'default.png', 'assets');

        } catch (Exception $e) {
            $this->addError('watermarkImage', $e->getMessage());
        }
    }

    public function submitForm()
    {
        $this->validate([
            'formData.dark_theme_enabled' => ['required', 'boolean'],
            'formData.default_theme' => ['required', 'string', 'max:255'],
            'formData.images_watermark_enabled' => ['required', 'boolean'],
            'formData.videos_watermark_enabled' => ['required', 'boolean'],
        ], attributes: [
            'formData.dark_theme_enabled' => 'Dark Theme',
            'formData.default_theme' => __('admin/brand.form.default_theme'),
            'formData.images_watermark_enabled' => 'Images Watermark',
            'formData.videos_watermark_enabled' => 'Videos Watermark',
        ]);

        $brandSettings = app(BrandSettings::class);

        $brandSettings->dark_theme_enabled = $this->formData['dark_theme_enabled'];
        $brandSettings->default_theme = $this->formData['default_theme'];
        $brandSettings->images_watermark_enabled = $this->formData['images_watermark_enabled'];
        $brandSettings->videos_watermark_enabled = $this->formData['videos_watermark_enabled'];
        $brandSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.branding');
    }

    public function render()
    {
        return view('livewire.admin.config.branding');
    }
}
