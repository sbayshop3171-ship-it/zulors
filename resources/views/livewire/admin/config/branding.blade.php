<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf

    <x-form.group mb="mb-12">
        <x-callout.default
            iconName="tag-01"
            titleText="{{ __('admin/brand.callout.logo_favicons.title') }}"
        captionText="{{ __('admin/brand.callout.logo_favicons.caption') }}" />
    </x-form.group>

    <x-form.group>
        <div class="flex gap-4 flex-wrap">
            <x-form.file
                labelText="{{ __('admin/brand.form.upload_light_theme_logo') }}"
                wire:model="lightThemeLogo"
                previewImage="{{ asset('assets/logos/light.png') }}?v={{ time() }}"
                accept="image/png"
                name="lightThemeLogo">
            </x-form.file>
            <x-form.file
                labelText="{{ __('admin/brand.form.upload_dark_theme_logo') }}"
                wire:model="darkThemeLogo"
                previewImage="{{ asset('assets/logos/dark.png') }}?v={{ time() }}"
                accept="image/png"
                name="darkThemeLogo">
            </x-form.file>
            <x-form.file
                labelText="{{ __('admin/brand.form.upload_favicon') }}"
                wire:model="faviconLogo"
                previewImage="{{ asset('assets/logos/favicon.png') }}?v={{ time() }}"
                accept="image/png"
                name="favicon">
            </x-form.file>
        </div>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>
    <x-form.group>
        <div>
            <x-form.radio-group labelText="{{ __('admin/brand.form.default_theme') }}">
                <x-form.radio
                    name="formData.default_theme"
                    wire:model="formData.default_theme"
                    defaultValue="light"
                    labelText="{{ __('admin/brand.form.default_theme_light') }}">
                </x-form.radio>
                <x-form.radio
                    name="formData.default_theme"
                    wire:model="formData.default_theme"
                    defaultValue="dark"
                    labelText="{{ __('admin/brand.form.default_theme_dark') }}">
                </x-form.radio>
            </x-form.radio-group>

            <div class="mt-2">
                <x-form.helper-text>
                    {{ __('admin/brand.form.default_theme_helper') }}
                </x-form.helper-text>
            </div>
        </div>
    </x-form.group>
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/brand.form.dark_theme_status') }}"
            wire:model="formData.dark_theme_enabled"
            name="formData.dark_theme_enabled">
        </x-form.switcher>
    </x-form.group>
    <div class="mb-6">
        <x-div/>
    </div>

    <x-form.group mb="mb-12">
        <x-callout.default
            iconName="sticker-01"
            titleText="{{ __('admin/brand.callout.watermark_status.title') }}"
        captionText="{{ __('admin/brand.callout.watermark_status.caption') }}" />
    </x-form.group>

    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/brand.form.images_watermark_status') }}"
            wire:model="formData.images_watermark_enabled"
            name="formData.images_watermark_enabled">
        </x-form.switcher>
        <x-form.switcher
            labelText="{{ __('admin/brand.form.videos_watermark_status') }}"
            wire:model="formData.videos_watermark_enabled"
            name="formData.videos_watermark_enabled">
        </x-form.switcher>
    </x-form.group>
    <x-form.group>
        <div class="flex">
            <x-form.file
                labelText="{{ __('admin/brand.form.upload_watermark') }}"
                wire:model="watermarkImage"
                accept="image/png"
                previewImage="{{ asset(config('assets.watermark.local_path')) }}?v={{ time() }}"
                name="watermarkImage">
            </x-form.file>
        </div>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
