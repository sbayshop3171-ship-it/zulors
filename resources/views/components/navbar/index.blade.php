@props([
    'title' => null
])

<x-card>
    <div class="p-2">
        @if ($title)
            <h4 class="text-lab-pr3 font-semibold text-par-l px-4 pt-4">
                {!! $title !!}
            </h4>
        @endif
        <div class="grid grid-cols-4 gap-4 p-4">
            {{ $slot }}
        </div>
    </div>
</x-card>
