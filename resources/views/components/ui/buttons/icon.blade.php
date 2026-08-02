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

@php
    $wireClickTarget = $attributes->wire('click')->value();
    $existingWireTarget = $attributes->wire('target')->value();
    $wireLoadingTarget = $existingWireTarget ?: $wireClickTarget;
    $hasWireLoadingAttribute = $attributes->whereStartsWith('wire:loading')->isNotEmpty();
    $hasWireLoadingAttrAttribute = $attributes->whereStartsWith('wire:loading.attr')->isNotEmpty();
    $hasLoadingFeedback = $hasWireLoadingAttribute || filled($wireClickTarget);
@endphp

<button
    type="button"
    class="size-8 rounded-full inline-flex-center outline-hidden hover:bg-fill-tr cursor-pointer disabled:opacity-60 disabled:cursor-wait {{ $colors[$color] }}"
    @if($hasLoadingFeedback && ! $hasWireLoadingAttrAttribute) wire:loading.attr="disabled" @endif
    @if($hasLoadingFeedback && $wireLoadingTarget && empty($existingWireTarget)) wire:target="{{ $wireLoadingTarget }}" @endif
    {{ $attributes }}>
    <span
        class="size-icon-normal"
        @if($hasLoadingFeedback) wire:loading.remove @endif
        @if($hasLoadingFeedback && $wireLoadingTarget) wire:target="{{ $wireLoadingTarget }}" @endif>
        <x-ui-icon type="{{ $iconType }}" name="{{ $iconName }}"></x-ui-icon>
    </span>

    @if($hasLoadingFeedback)
        <span
            class="hidden size-icon-normal items-center justify-center"
            wire:loading.flex
            @if($wireLoadingTarget) wire:target="{{ $wireLoadingTarget }}" @endif
            aria-hidden="true">
            <span class="inline-block scale-75 colibri-primary-animation"></span>
        </span>
    @endif
</button>
