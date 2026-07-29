<a {{ $attributes }} class="rounded-full border border-bord-pr block leading-none">
    <div class="flex relative h-12 items-center">
        @if(isset($iconSlot))
            <div class="absolute left-3 top-3 size-4 md:size-6 block overflow-hidden">
                {{ $iconSlot }}
            </div>
        @endif

        <span class="text-center block w-full text-lab-pr2 text-par-s md:text-par-n font-semibold">{{ $slot }}</span>
    </div>
</a>
