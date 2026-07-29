<form wire:submit.prevent="submitForm" enctype="multipart/form-data">
    @csrf
    <x-form.group>
        <x-form.switcher
            labelText="{{ __('admin/mobile.form.pwa_enabled') }}"
            wire:model="formData.pwa_enabled"
            name="formData.pwa_enabled">
        </x-form.switcher>
        <x-form.helper-text>
            {{ __('admin/mobile.form.pwa_enabled_helper') }}

            <a href="https://www.google.com/search?q=What+is+a+Progressive+web+app?" class="text-brand-900 underline" target="_blank">
                {{ __('links.pw_learn_more') }}
            </a>
        </x-form.helper-text>
    </x-form.group>

    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/mobile.form.service_worker_content') }}"
            :asText="true"
            placeholder="E.g. console.log('Service worker loaded');"
            wire:model="formData.service_worker_content"
            name="formData.service_worker_content">

            <x-slot:feedbackInfo>
                {!! __('admin/mobile.form.service_worker_content_helper') !!}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>
    <x-form.group>
        <x-form.text-input
            labelText="{{ __('admin/mobile.form.manifest_content') }}"
            :asText="true"
            placeholder="E.g. { ... }"
            wire:model="formData.manifest_content"
            name="formData.manifest_content">

            <x-slot:feedbackInfo>
                {!! __('admin/mobile.form.manifest_content_helper') !!}
            </x-slot:feedbackInfo>
        </x-form.text-input>
    </x-form.group>

    @if(count($pwaIcons))
        <x-form.group>
            <div>
                <x-form.label>
                    {{ __('admin/mobile.form.pwa_icons') }}
                </x-form.label>
                <div class="grid grid-cols-1 gap-4 mb-2">
                    @foreach($pwaIcons as $icon)
                        <div class="flex gap-2 items-center border border-bord-pr rounded-2xl p-4">
                            <div class="size-10 shrink-0">
                                <img src="{{ asset($icon['src']) }}" alt="{{ $icon['sizes'] }}" class="w-10 h-10">
                            </div>
                            <div class="flex-1">
                                <h3 class="text-par-m text-lab-pr2 font-medium">
                                    {{ $this->getIconDescription($icon['sizes']) }}
                                </h3>
                                <p class="text-par-s">
                                    {{ $icon['sizes'] }} - {{ $icon['type'] }}
                                </p>
                            </div>
                            <div class="shrink-0">
                                <x-ui.buttons.icon wire:confirm="{{ __('admin/mobile.prompts.delete_pwa_icon.content') }}" wire:click="removeIcon('{{ $icon['sizes'] }}')" iconName="trash-04" color="danger" iconType="line"></x-ui.buttons.icon>
                            </div>
                        </div>
                    @endforeach
                </div>
                <a href="{{ config('app.documentation_url') }}" class="text-par-n block font-semibold text-brand-900 underline" target="_blank">{{ __('labels.learn_more') }} <span class="underline">PWA Icons Documentation</span></a>
            </div>
        </x-form.group>
    @endif

    @if($errors->has('pwaIcon'))
        <div class="mb-4">
            <x-form.valerr>
                {{ $errors->first('pwaIcon') }}
            </x-form.valerr>
        </div>
    @endif

    <x-form.group>
        <div x-data>
            <button x-on:click="$refs.input.click()" type="button" class="border-2 cursor-pointer border-fill-pr border-dashed size-full rounded-2xl px-4 py-8 transition-all ease-linear hover:border-brand-900">
                <span class="flex flex-col items-center justify-center size-full">
                    <span class="size-6 mb-2">
                        <x-ui-icon name="upload-01" type="solar"></x-ui-icon>
                    </span>
                    <h5 class="text-lab-pr2 font-medium text-par-m mb-1">
                        {{ __('admin/mobile.callouts.pwa_icons.title') }}
                    </h5>
                    <p class="text-lab-sc text-par-n tracking-normal mb-4">
                        {{ __('admin/mobile.callouts.pwa_icons.caption') }}
                    </p>
                </span>
            </button>
            <input x-ref="input" wire:model="pwaIcon" type="file" class="hidden" accept="image/*">
        </div>
    </x-form.group>

    <x-ui.buttons.pill size="sm" type="submit" btnText="{{ __('buttons.save_changes') }}"></x-ui.buttons.pill>
</form>
