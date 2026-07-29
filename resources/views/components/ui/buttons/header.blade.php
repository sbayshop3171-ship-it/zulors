@props([
    'url' => '#',
    'avatarUrl' => '',
    'iconName' => 'help-01',
    'iconType' => 'solar',
])

<a {{ $attributes }} href="{{ $url }}" class="shrink-0 cursor-pointer items-center">
    <div class="size-[40px] rounded-full overflow-hidden border border-bord-pr">
        @if($avatarUrl)
            <img class="w-full" src="{{ $avatarUrl }}" alt="Image">
        @else
            <div class="size-full flex-center text-lab-pr3 hover:text-brand-900">
                <div class="size-6">
                    <x-ui-icon name="{{ $iconName }}" type="{{ $iconType }}" />
                </div>
            </div>
        @endif
    </div>
</a>
