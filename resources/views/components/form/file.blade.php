@props([
    'labelText' => '',
    'name' => '',
    'previewImage' => null,
    'previewImageBg' => '',
])

@php($key = random_int(1000000000, 9999999999))

<div>
    <div class="flex border items-center cursor-pointer border-dashed border-bord-card smoothing hover:border-brand-900 rounded-xl group">
        <div class="flex items-center gap-2 h-12 px-4" x-on:click="$refs['inputFile-{{ $key }}'].click()" x-data>
            <div class="size-6 text-lab-tr group-hover:text-brand-900">
                <x-ui-icon name="upload-01" type="solar"></x-ui-icon>
            </div>
            <span class="text-par-m text-lab-sc text-center leading-none ml-1 font-semibold">
                {{ $labelText }}
            </span>
        </div>

        @if($previewImage)
            <div class="h-7 border-l border-bord-card px-3" title="Preview Image">
                <a href="{{ $previewImage }}" target="_blank">
                    <img src="{{ $previewImage }}" alt="Preview" class="h-full">
                </a>
            </div>
        @endif

        <input type="file" x-ref="inputFile-{{ $key }}" name="{{ $name }}" class="hidden" {{ $attributes }}>
    </div>

    @if($errors->has($name))
        @error($name)
            <div class="mt-2">
                <x-form.valerr>
                    {{ $message }}
                </x-form.valerr>
            </div>
        @enderror
    @endif
</div>
