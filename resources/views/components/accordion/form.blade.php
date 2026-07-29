@props([
    'title' => '',
    'open' => true
])

<div class="block" x-data="{ open: @json($open) }">
    <div @click="open = !open" x-bind:class="open ? '' : 'border-b border-edge-sc pb-6'" class="flex justify-between select-none mb-6 items-center leading-none cursor-pointer">
        <h2 class="text-par-m text-lab-pr2 font-medium">
            {{ $title }}
        </h2>
        <button type="button" x-bind:class="open ? 'invisible' : 'visible'" class="size-icon-small text-lab-tr transition-all ease-linear">
            <x-ui-icon name="chevron-down" c></x-ui-icon>
        </button>
    </div>
    <div x-show="open" class="block select-none">
        {{ $slot }}
    </div>
</div>