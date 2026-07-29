<?php

namespace App\Livewire\Admin\Config;

use App\Settings\AppSettings;
use App\Support\Views\Flash;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PWA extends Component
{
    use WithFileUploads;

    public array $formData = [];
    public array $pwaIcons = [];
    public $pwaIcon;


    public function mount()
    {
        $appSettings = app(AppSettings::class);

        $this->pwaIcons = $this->getPwaIcons();

        $this->formData = [
            'pwa_enabled' => $appSettings->pwa_enabled,
            'manifest_content' => $this->getManifestContent(),
            'service_worker_content' => $this->getServiceWorkerContent(),
        ];
    }

    public function updatedPwaIcon()
    {
        $this->validate([
            'pwaIcon' => ['required', 'image', 'mimes:png', 'mimetypes:image/png', 'max:1024'],
        ], attributes: [
            'pwaIcon' => 'PWA App Icon',
        ]);

        $this->pwaIcon->storeAs('icons', $this->pwaIcon->getClientOriginalName(), 'pwa');

        $this->formData['manifest_content'] = $this->getManifestContent();

        $this->pwaIcons = $this->getPwaIcons();
    }

    public function submitForm()
    {
        $this->validate([
            'formData.pwa_enabled' => ['required', 'boolean'],
            'formData.manifest_content' => ['nullable', 'string', 'max:10000'],
            'formData.service_worker_content' => ['nullable', 'string', 'max:10000'],
        ], attributes: [
            'formData.pwa_enabled' => __('admin/mobile.form.pwa_enabled'),
            'formData.manifest_content' => __('admin/mobile.form.manifest_content'),
            'formData.service_worker_content' => __('admin/mobile.form.service_worker_content'),
        ]);

        $appSettings = app(AppSettings::class);

        $appSettings->pwa_enabled = $this->formData['pwa_enabled'];
        $appSettings->save();

        $this->setManifestContent($this->formData['manifest_content']);
        $this->setServiceWorkerContent($this->formData['service_worker_content']);

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.config.mobile-apps', ['tab' => 'pwa']);
    }

    private function getServiceWorkerContent()
    {
        return file_get_contents(public_path('pwa/service-worker.js'));
    }

    private function getManifestContent()
    {
        $manifestContent = file_get_contents(public_path('pwa/manifest.json'));
        $manifestArray = json_decode($manifestContent, true);

        $manifestArray['icons'] = $this->getPwaIcons();

        return json_encode($manifestArray, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    private function getPwaIcons()
    {
        return collect(Storage::disk('pwa')->files('icons'))
            ->filter(fn ($file) => preg_match('/\.(png)$/i', $file))
            ->map(function ($file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);

                return [
                    'src' => "/pwa/{$file}",
                    'sizes' => $filename,
                    'type' => 'image/png',
                ];
            })
            ->values()
            ->toArray();
    }

    private function setManifestContent($manifestContent)
    {
        file_put_contents(public_path('pwa/manifest.json'), $manifestContent);
    }

    private function setServiceWorkerContent($serviceWorkerContent)
    {
        file_put_contents(public_path('pwa/service-worker.js'), $serviceWorkerContent);
    }

    public function render()
    {
        return view('livewire.admin.config.pwa');
    }

    public function removeIcon(string $iconSize)
    {
        $iconSrc = "icons/{$iconSize}.png";

        if(Storage::disk('pwa')->exists($iconSrc)) {
            Storage::disk('pwa')->delete($iconSrc);

            $this->formData['manifest_content'] = $this->getManifestContent();

            $this->pwaIcons = $this->getPwaIcons();
        }
        else {
            $this->addError('pwaIcon', __('admin/mobile.validation.pwa_icon_not_found'));
        }
    }

    public function getIconDescription(string $iconSize)
    {
        return match($iconSize) {
            '72x72' => 'Older devices, low-res screens',
            '96x96' => 'Desktop shortcuts, Android small',
            '128x128' => 'Chrome Web Store, Windows',
            '144x144' => 'Windows tiles, older iPads',
            '152x152' => 'iPad Retina',
            '192x192' => 'Home screen (required)',
            '384x384' => 'Android splash screen',
            '512x512' => 'Splash screen & install prompt (required)',
            '512x512-maskable' => 'Adaptive icon for Android shapes',
            default => 'Unknown icon',
        };
    }
}
