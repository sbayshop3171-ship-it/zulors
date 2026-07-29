@props([
    'asText' => false,
    'hasLabel' => true,
    'labelText' => '',
    'labelTextBrackets' => '',
    'isReadonly' => false,
    'inputType' => 'text',
    'isPassword' => false,
    'placeholder' => '',
    'classes' => '',
    'name' => '',
    'errorKey' => null,
    'value' => ''
])

<div class="block">
    @if ($hasLabel)
        <x-form.label>
            {{ $labelText }}
            @if (!empty($labelTextBrackets))
                <span class="text-lab-sc">({{ $labelTextBrackets }})</span>
            @endif
        </x-form.label>
    @endif

    @if (isset($feedbackInfo))
        <div class="flex justify-between mb-3">
            @if (isset($feedbackIcon))
                <span class="mr-2.5">
                    {{ $feedbackIcon }}
                </span>
            @endif
            <x-form.helper-text>
                {{ $feedbackInfo }}
            </x-form.helper-text>
        </div>
    @endif


    @if ($asText)
        <div class="relative overflow-hidden rounded-xl group border-2 border-transparent hover:border-brand-900 smoothing">
            <textarea
                x-ref="input"
                class="block w-full bg-input-pr field-sizing-content max-h-96 overflow-y-auto read-only:opacity-90 tracking-normal min-h-24 outline-hidden text-par-m text-lab-pr px-4 py-4 {{ $classes }}"
                placeholder="{{ $placeholder }}"
            name="{{ $name }}" {{ $attributes }}>{{ $value }}</textarea>

            <div class="size-4 absolute right-0 bottom-px bg-input-pr text-lab-tr inline-block group-hover:hidden">
                <x-ui-icon name="dots-arrow" type="solid"></x-ui-icon>
            </div>
        </div>
    @else
        <div class="rounded-xl relative overflow-hidden border-2 border-transparent hover:border-brand-900 smoothing" x-data="{ inputType: '{{ $inputType }}' }">
            <input
                x-ref="input"
                x-bind:type="inputType"
                class="block w-full bg-input-pr read-only:opacity-50 read-only:cursor-not-allowed tracking-normal outline-hidden text-par-m text-lab-pr px-4 h-12 {{ $classes }}"
                placeholder="{{ $placeholder }}"
                name="{{ $name }}"
                @if ($isReadonly)
                    readonly
                @endif
            value="{{ $value }}" {{ $attributes }}>

            @if (isset($inputIcon))
                <span class="absolute right-0 top-0">
                    {{ $inputIcon }}
                </span>
            @endif

            @if ($isPassword)
                <span class="absolute right-0 top-0 h-full inline-flex-center px-4 bg-input-pr">
                    <button x-on:click="inputType = (inputType == 'password' ? 'text' : 'password')" class="size-6 cursor-pointer text-bord-sc outline-hidden" type="button">
                        <template x-if="inputType == 'password'">
                            <x-ui-icon name="eye" type="solid" classes="size-full text-bord-sc" />
                        </template>
                        <template x-if="inputType == 'text'">
                            <x-ui-icon name="eye-off" type="solid" classes="size-full text-bord-sc" />
                        </template>
                    </button>
                </span>
            @endif
        </div>
    @endif

    @if($errors->has($errorKey ?? $name))
        @error($errorKey ?? $name)
            <div class="mt-2">
                <x-form.valerr>
                    {{ $message }}
                </x-form.valerr>
            </div>
        @enderror
    @endif
</div>
