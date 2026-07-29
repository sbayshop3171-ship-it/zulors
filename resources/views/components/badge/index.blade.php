@props([
    'variant' => 'default',
    'variants' => [
        'success' => 'bg-green-400 text-white',
        'danger' => 'bg-red-400 text-white',
        'default' => 'bg-fill-pr text-par-n text-lab-pr3',
        'warning' => 'bg-yellow-400 text-white',
    ],
])

<span class="inline-block text-par-s font-semibold px-2 py-0.5 rounded-md {{ $variants[$variant] }}">
    {{ $slot }}
</span>
