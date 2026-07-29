@props([
    'href' => '#',
    'icon' => null,
    'iconUrl' => null,
    'iconSize' => 'size-8',
])

<a href="{{ $href }}" class="px-4 py-4 flex flex-col group cursor-pointer relative bg-fill-fv hover:bg-fill-qt smoothing rounded-2xl">
    <div class="flex">
        @if($iconUrl)
            <x-ui-icon-avatar
                size="{{ $iconSize }}"
                name="{{ $icon }}"
                url="{{ $iconUrl }}"></x-ui-icon-avatar>
        @else
            <div class="size-6 text-brand-900/80">
                <x-ui-icon name="{{ $icon }}" type="solar"></x-ui-icon>
            </div>
        @endif
    </div>

    <div class="mt-4">
        <h4 class="font-semibold text-lab-pr2">
            {!! $slot !!}
        </h4>
    </div>

    <div class="size-icon-small text-lab-sc opacity-30 absolute top-4 right-4 group-hover:top-3 group-hover:right-3 smoothing">
        <x-ui-icon name="arrow-up-right" type="line"></x-ui-icon>
    </div>
</a>
