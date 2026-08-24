<a {{ $attributes }} class="auth-social-button rounded-full border border-bord-pr block leading-none">
    <div class="auth-social-button__inner flex relative h-12 items-center">
        @if(isset($iconSlot))
            <div class="auth-social-button__icon absolute left-4 top-1/2 -translate-y-1/2 size-4 md:size-5 block overflow-hidden">
                {{ $iconSlot }}
            </div>
        @endif

        <span class="auth-social-button__label text-center block w-full px-10 md:px-12 text-lab-pr2 text-par-s md:text-par-n font-semibold leading-tight">{{ $slot }}</span>
    </div>
</a>
