@props([
    'iconName' => 'dots-horizontal',
    'iconType' => 'solid',
    'color' => 'default',
    'colors' => [
        'default' => 'text-lab-pr3',
        'strong' => 'text-lab-pr2',
        'muted' => 'text-lab-tr',
        'success' => 'text-green-900',
        'danger' => 'text-red-900',
    ]
])

<button type="button" class="size-8 rounded-full inline-flex-center outline-hidden hover:bg-fill-tr cursor-pointer {{ $colors[$color] }}" {{ $attributes }}>
    <span class="size-icon-normal">
        <x-ui-icon type="{{ $iconType }}" name="{{ $iconName }}"></x-ui-icon>
    </span>
</button>
