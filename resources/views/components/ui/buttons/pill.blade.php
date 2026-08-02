@props([
    'btnText' => 'Label',
    'type' => 'button',
    'width' => 'w-auto',
    'size' => 'md',
    'sizeOptions' => [
        'xs' => 'h-10 px-4 text-par-s',
        'sm' => 'h-12 px-5 text-par-n',
        'md' => 'py-3.5 px-6 text-par-n',
        'lg' => 'py-4.5 px-8 text-par-m',
    ],
    'variant' => 'default',
    'variantOptions' => [
        'default' => 'bg-fill-tr text-brand-900 font-semibold hover:bg-fill-sc',
        'danger' => 'bg-fill-tr text-red-900 font-semibold',
        'accent' => 'bg-lab-pr2 text-bg-sc font-semibold',
        'outline' => 'bg-transparent border border-bord-pr hover:bg-fill-fv text-lab-pr hover:text-lab-pr font-semibold',
        'link' => 'bg-transparent text-lab-sc hover:text-brand-900',
    ]
])

@php
    $wireClickTarget = $attributes->wire('click')->value();
    $existingWireTarget = $attributes->wire('target')->value();
    $wireLoadingTarget = $existingWireTarget ?: $wireClickTarget;
    $hasWireLoadingAttribute = $attributes->whereStartsWith('wire:loading')->isNotEmpty();
    $hasWireLoadingAttrAttribute = $attributes->whereStartsWith('wire:loading.attr')->isNotEmpty();
    $hasLoadingFeedback = $hasWireLoadingAttribute || filled($wireClickTarget) || $type === 'submit';
@endphp

<button
    class="{{ $width }} {{ $sizeOptions[$size] }} block {{ $variantOptions[$variant] }} smoothing rounded-full cursor-pointer disabled:opacity-60 disabled:cursor-wait"
    @if($hasLoadingFeedback && ! $hasWireLoadingAttrAttribute) wire:loading.attr="disabled" @endif
    @if($hasLoadingFeedback && $wireLoadingTarget && empty($existingWireTarget)) wire:target="{{ $wireLoadingTarget }}" @endif
    {{ $attributes }}
    type="{{ $type }}">
    <span
        class="inline-flex-center min-h-5 gap-2"
        @if($hasLoadingFeedback) wire:loading.remove @endif
        @if($hasLoadingFeedback && $wireLoadingTarget) wire:target="{{ $wireLoadingTarget }}" @endif>
        {{ $btnText }}
    </span>

    @if($hasLoadingFeedback)
        <span
            class="hidden min-h-5 items-center justify-center gap-2"
            wire:loading.flex
            @if($wireLoadingTarget) wire:target="{{ $wireLoadingTarget }}" @endif
            aria-hidden="true">
            <span class="inline-block px-4">
                <span class="inline-block colibri-primary-animation"></span>
            </span>
        </span>
    @endif
</button>
