@props([
    'hasLabel' => true,
    'labelText' => ''
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
    <div class="bg-transparent border border-edge-tr rounded-xl overflow-hidden inline-flex leading-none">
        {{ $slot }}
    </div>
</div>
