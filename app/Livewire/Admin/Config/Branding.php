<?php

namespace App\Livewire\Admin\Config;

use App\Settings\BrandSettings;
use App\Support\Views\Flash;
use Exception;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Drivers\Gd\Encoders\PngEncoder;

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

            $faviconSizes = [
                'assets' => [
                    'logos/favicon.png' => 32,
                    'logos/apple-touch-icon.png' => 180,
                ],
                'pwa' => [
                    'icons/192x192.png' => 192,
                    'icons/512x512.png' => 512,
                ],
            ];

            foreach ($faviconSizes as $disk => $files) {
                foreach ($files as $path => $size) {
                    $favicon = (new ImageManager(new Driver()))
                        ->read($this->faviconLogo->getRealPath())
                        ->cover($size, $size)
                        ->encode(new PngEncoder());

                    Storage::disk($disk)->put($path, (string) $favicon);
                }
            }

            $this->refreshPwaIconVersions();

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

    private function refreshPwaIconVersions(): void
    {
        $pwa = Storage::disk('pwa');
        $manifest = json_decode($pwa->get('manifest.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($manifest['icons'] ?? [] as &$icon) {
            $iconPath = parse_url($icon['src'] ?? '', PHP_URL_PATH);

            if (! is_string($iconPath) || ! str_starts_with($iconPath, '/pwa/icons/')) {
                continue;
            }

            $publicPath = public_path(ltrim($iconPath, '/'));

            if (is_file($publicPath)) {
                $icon['src'] = $iconPath.'?v='.hash_file('sha256', $publicPath);
            }
        }

        unset($icon);

        if (! $pwa->put('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))) {
            throw new Exception('Unable to refresh the PWA icon manifest.');
        }
    }
}
