@props([
    'labelText' => ''
])

<div class="flex items-center leading-none select-none">
    <label class="inline-flex items-center cursor-pointer leading-none">
        <input type="checkbox" {{ $attributes }} class="sr-only peer">
        <div class="relative w-10 h-5 bg-[#787880]/20 peer-focus:outline-hidden rounded-full peer peer-checked:after:translate-x-[20px] rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:shadow-lg after:border-bord-card after:rounded-full after:size-4 after:transition-all peer-checked:bg-green-900"></div>
        <span class="ml-3 text-lab-sc text-par-m">
            {{ $labelText }}
        </span>
    </label>
</div>
