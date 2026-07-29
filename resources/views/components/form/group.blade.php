@props([
    'cols' => 'grid-cols-1',
    'mb' => 'mb-6',
])

<div class="{{ $mb }} grid {{ $cols }} gap-6">
    {{ $slot }}
</div>
