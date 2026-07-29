<header class="fixed top-0 left-0 right-0 bg-bg-pr/80 backdrop-blur-xs" style="min-width: 320px;">
    <div class="h-14 flex justify-between px-4 md:px-8 items-center relative">
        <div class="text-lab-pr text-par-m font-medium hidden md:block">
            @guest
                {{ __('auth.hi_there') }}
            @else
                {{ __('labels.hi_user', ['name' => me()->name]) }}
            @endif
        </div>

        <a class="absolute left-half -translate-x-3.5" href="{{ route('user.desktop.index', '/') }}">
            <img class="h-7" src="{{ $logotypeUrl }}" alt="Image">
        </a>

        <div class="inline-flex gap-5 text-lab-pr font-medium items-center ml-auto">
            <div x-data="{ isOpen: false }" x-on:click.away="isOpen = false" class="relative" x-cloak>
                <button class="block leading-none cursor-pointer" x-on:click="isOpen = !isOpen">
                    <span class="items-center gap-1 hidden md:flex">
                        <span class="text-par-m">
                            {{ $appLanguages->getLocaleName() }}
                        </span>
                        <span class="size-4 shrink-0">
                            <x-ui-icon name="chevron-down"></x-ui-icon>
                        </span>
                    </span>
                    <div class="inline-block md:hidden size-icon-small">
                        <x-ui-icon name="translate-01" type="line"></x-ui-icon>
                    </div>
                </button>
                <div x-show="isOpen" class="absolute top-full right-0 rounded-md overflow-hidden min-w-60 ease-in-out transition-all shadow-md z-40">
                    <div class="block bg-bg-pr/80 backdrop-blur-xs divide-y divide-fill-tr">
                        @foreach ($appLanguages->getLanguages() as $langData)
                            <a href="{{ route('user.language.switch', ['lang' => $langData->alpha_2_code]) }}" rel="nofollow" title="{{ $langData->name }}" class="block px-4 py-2 hover:bg-fill-qt smoothing text-lab-pr2 text-par-s {{ empty($langData->current) ? '' : 'bg-fill-qt' }}">
                                {{ $langData->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>