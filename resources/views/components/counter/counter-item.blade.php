@props([
    'counterValue' => 0,
    'captionText' => '',
])

<div class="leading-none">
    <h2 class="text-title-1 tracking-tighter font-medium mb-1.5 text-lab-pr2">
        {!! $counterValue !!}
    </h2>
    <span class="block text-par-s text-lab-sc">{!! $captionText !!}</span>
</div>