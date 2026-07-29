@props([
    'copyable' => true
])

<div x-data="colibriUICode">
    <code class="bg-input-pr py-0.5 pl-4 pr-2 rounded-lg text-par-s text-lab-sc flex items-center gap-4 min-h-12" {{ $attributes }}>
        <div class="flex-1 font-medium empty:after:content-['~']" x-ref="code">{{ $slot }}</div>
        @if ($copyable)
            <x-ui.buttons.icon x-show="! copying" iconName="copy-06" iconType="line" color="muted" x-on:click="copy"></x-ui.buttons.icon>
            <x-ui.buttons.icon x-show="copying" iconName="check-circle" iconType="line" color="success"></x-ui.buttons.icon>
        @endif
    </code>
</div>