@props([
    'iconName' => '',
    'iconType' => 'solar',
    'titleText' => '',
    'captionText' => '',
])

<div class="flex gap-4">
    <div class="shrink-0 size-8 text-lab-tr mt-1.5">
        <x-ui-icon :name="$iconName" :type="$iconType"/>
    </div>
    <div class="flex-1">
        <h5 class="text-par-m font-bold text-lab-pr2 mb-0.5">
            {!! $titleText !!}
        </h5>
        <p class="text-par-m text-lab-sc">
            {!! $captionText !!}
        </p>
    </div>
</div>
