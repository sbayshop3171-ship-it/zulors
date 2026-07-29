@props([
    'btnText' => 'Label',
    'link' => '',
    'iconName' => 'plus',
    'iconType' => 'solid'
])

<a href="{{ $link }}" class="bg-lab-pr2 rounded-full px-4 py-2 text-bg-pr" {{ $attributes }}>
    <span class="flex items-center h-6 gap-2">
        <span class="size-icon-small">
            <x-ui-icon name="{{ $iconName }}" type="{{ $iconType }}"></x-ui-icon>
        </span>
        <span class="text-par-m leading-none font-medium">{{ $btnText }}</span>
    </span>
</a>