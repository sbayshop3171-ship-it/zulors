<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.ffmpeg_path') }}"
            type="text"
            wire:model="formData.ffmpeg_path"
            name="formData.ffmpeg_path">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.ffmpeg_path_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.ffprobe_path') }}"
            type="text"
            wire:model="formData.ffprobe_path"
            name="formData.ffprobe_path">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.ffprobe_path_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group cols="grid-cols-2">
        <x-form.text-input
            labelText="{{ __('admin/config.form.timeout') }}"
            type="number"
            wire:model="formData.timeout"
            name="formData.timeout">
        </x-form.text-input>
        <x-form.text-input
            labelText="{{ __('admin/config.form.threads') }}"
            type="number"
            wire:model="formData.threads"
            name="formData.threads">
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/config.form.temporary_directory') }}"
            type="text"
            wire:model="formData.temporary_directory"
            name="formData.temporary_directory">
            <x-slot:feedbackInfo>
                {{ __('admin/config.form.temporary_directory_helper') }}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-ui.buttons.pill
            type="submit"
            size="sm"
        btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
