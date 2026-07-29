@props([
	'title' => '',
	'desc' => '',
    'iconName' => null,
    'iconType' => null,
])

<div class="text-center px-12">
    @if ($iconName && $iconType)
        <div class="flex justify-center mb-4">
            <div class="size-8 text-lab-sc">
                <x-ui-icon name="{{ $iconName }}" type="{{ $iconType }}"></x-ui-icon>
            </div>
        </div>
    @endif
    <h4 class="text-par-l font-bold text-lab-pr2 mb-2">
		{{ $title }}
	</h4>
	<p class="text-par-m text-lab-sc">
		{{ $desc }}
	</p>
</div>
