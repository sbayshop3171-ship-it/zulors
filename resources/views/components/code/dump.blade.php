@props([
    'label' => '',
    'dumpText' => ''
])

<div class="block">
    <x-form.label>
        {{ $label }}
    </x-form.label>
    <div class="bg-lab-pr2 p-4 rounded-lg min-h-12 max-h-48 overflow-y-auto">
        <code class="text-par-s text-bg-sc">
            {{ $dumpText }}
        </code>
    </div>
</div>
