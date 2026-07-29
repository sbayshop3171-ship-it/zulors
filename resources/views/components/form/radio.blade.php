@props([
    'labelText' => '',
    'checked' => '',
    'classes' => '',
    'name' => '',
    'defaultValue' => ''
])

<div class="border-r border-edge-tr last:border-none">
    <input type="radio" id="radio-input-{{ $name }}-{{ $defaultValue }}" name="{{ $name }}" value="{{ $defaultValue }}" class="hidden peer" required @if($checked) checked @endif {{ $attributes }}/>
    <label for="radio-input-{{ $name }}-{{ $defaultValue }}" class="font-medium peer-checked:text-brand-900 text-par-n text-lab-pr2 px-4 peer-checked:bg-input-pr inline-block py-3.5 cursor-pointer">                           
        {{ $labelText }}
    </label>
</div>