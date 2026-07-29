@props([
    'itemText' => '',
    'tag' => 'div',
    'danger' => false,
])

<{{ $tag }} class=" block last:border-none cursor-pointer {{ $danger ? 'text-red-900' : 'text-lab-pr' }}" {{ $attributes }}>
    <div class="py-3.5 px-4 flex leading-none items-center hover:bg-fill-qt smoothing">
        <span class="mr-auto text-par-m font-medium whitespace-nowrap">
            {{ $itemText }}
        </span>

        @if(isset($itemIcon))
            <span class="shrink-0 size-icon-small">
                {{ $itemIcon }}
            </span>
        @endif
    </div>
</{{ $tag }}>