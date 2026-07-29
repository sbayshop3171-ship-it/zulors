<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @if ($isTested)
        <x-form.group>
            <div class="py-10">
                <x-page-states.message iconName="check-circle" iconType="line" title="{{ __('admin/config.empty_state.ffmpeg_test_ok.title') }}" desc="{{ __('admin/config.empty_state.ffmpeg_test_ok.desc') }}" />
            </div>
            <div class="flex flex-col gap-8">
                <x-code.dump label="FFMPEG Output" dumpText="{{ $ffmpegOutput }}" />
                <x-code.dump label="FFProbe Output" dumpText="{{ $ffprobeOutput }}" />
            </div>
        </x-form.group>
    @elseif ($error)
        <x-code.dump label="Error" dumpText="{{ $error }}" />
    @endif

    @if (! $isTested)
        <x-form.group>
            <div class="py-10">
                <x-page-states.message iconName="terminal-square" iconType="line" title="{{ __('admin/config.empty_state.ffmpeg_test.title') }}" desc="{{ __('admin/config.empty_state.ffmpeg_test.desc') }}" />
            </div>
        </x-form.group>
    @endif
    <x-ui.buttons.pill wire:loading.attr="disabled" wire:target="submitForm" size="md" width="w-full" type="button" wire:click="submitForm" btnText="{{ __('buttons.run_test') }}"></x-ui.buttons.pill>
</form>
