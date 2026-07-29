@if ($paginator->hasPages())
    <nav class="inline-flex gap-1.5 leading-zero font-mono">
        @if ($paginator->onFirstPage())
            <span class="flex flex-center text-lab-pr2 size-10 border border-edge-pr rounded-full opacity-50 cursor-default">
                <span class="size-icon-small">
                    <x-ui-icon name="chevron-left-double" type="line"></x-ui-icon>
                </span>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="flex text-lab-pr2 flex-center size-10 border border-edge-pr rounded-full hover:bg-fill-tr">
                <span class="size-icon-small"><x-ui-icon name="chevron-left-double" type="line"></x-ui-icon></span>
            </a>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="h-10 inline-flex items-center leading-zero text-lab-tr">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <a href="{{ $url }}" class="flex flex-center size-10 bg-bg-pr border border-edge-pr rounded-full hover:bg-lab-pr bg-lab-pr2 text-bg-pr">
                            {{ $page }}    
                        </a>
                    @else
                        <a href="{{ $url }}" class="flex flex-center text-lab-pr2 size-10 border border-edge-pr rounded-full hover:bg-fill-tr">
                            {{ $page }}    
                        </a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="flex text-lab-pr2 flex-center size-10 border border-edge-pr rounded-full hover:bg-fill-tr">
                <span class="size-icon-small">
                    <x-ui-icon name="chevron-right-double" type="line"></x-ui-icon>
                </span>
            </a>
        @else
            <span class="flex flex-center size-10 text-lab-pr2 border border-edge-pr rounded-full opacity-50 cursor-default">
                <span class="size-icon-small">
                    <x-ui-icon name="chevron-right-double" type="line"></x-ui-icon>
                </span>
            </span>
        @endif
    </nav>
@endif